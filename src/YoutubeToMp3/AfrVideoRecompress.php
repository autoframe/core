<?php

namespace Autoframe\Core\YoutubeToMp3;


use InvalidArgumentException;
use RuntimeException;


/**
 * AfrVideoRecompress
 *
 * Fast, percent-based video re-compressor for Windows/PHP 7.4+.
 * - Constructor takes input & output directories
 * - Compression driven by a quality percent (30..90). Example: 50 => ~50% of original total bitrate
 * - Single-pass ABR for speed; optional preset tuning
 * - Audio policy: copy if MP4-friendly and >= 192 kbps; otherwise transcode to AAC at >= 192 kbps
 * - If output becomes larger than input, it is deleted and reported as "skipped (no gain)"
 * - Input file size is obtained via file_get_contents() by default (with safe fallbacks)
 *
 */
class AfrVideoRecompress
{
	private string $inputDir;
	private string $outputDir;
	private bool   $deleteInputAfter = false;

	private array  $allowedExt = ['mp4','mkv','webm','mov','avi'];
	private string $ffmpegBin  = 'ffmpeg';
	private string $ffprobeBin = 'ffprobe';

	// Video knobs
	private string  $preset        = 'veryfast'; // speed-first; change to 'fast'/'medium' for better compression
	private int     $minVideoKbps  = 600;        // floor to avoid starving video
	private ?string $scale         = null;       // e.g. '=-2:720' to cap height at 720p; null keeps source
	private bool    $useHevc       = false;      // H.265 can be smaller but slower; leave false for speed

	// Audio knobs
	private int     $minAudioKbps        = 192;  // ensure audio >= 192 kbps
	private bool    $preferAudioCopy     = true; // copy if codec is MP4-friendly and >= minAudioKbps
	private array   $mp4FriendlyAudio    = ['aac','mp3','ac3','eac3','alac'];


	// Debug
	private bool    $debug = false;

	public function __construct(string $inputDir, string $outputDir)
	{
		$this->setInputDir($inputDir);
		$this->setOutputDir($outputDir);
	}

	public function setInputDir(string $dir): self
	{
		$dir = rtrim($dir, "\\/");
		if (!is_dir($dir))      throw new InvalidArgumentException("Input directory does not exist: {$dir}");
		if (!is_readable($dir)) throw new InvalidArgumentException("Input directory is not readable: {$dir}");
		$this->inputDir = $dir;
		return $this;
	}

	public function setOutputDir(string $dir): self
	{
		$dir = rtrim($dir, "\\/");
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new RuntimeException("Failed to create output directory: {$dir}");
		}
		if (!is_writable($dir)) throw new InvalidArgumentException("Output directory is not writable: {$dir}");
		$this->outputDir = $dir;
		return $this;
	}

	public function keepInputFiles(bool $keep): self
	{
		$this->deleteInputAfter = !$keep;
		return $this;
	}

	public function setFfmpegBinary(string $path): self { $this->ffmpegBin  = $path; return $this; }
	public function setFfprobeBinary(string $path): self { $this->ffprobeBin = $path; return $this; }

	public function setPreset(string $preset): self { $this->preset = $preset; return $this; }
	public function setScale(?string $scale): self { $this->scale = $scale; return $this; }
	public function setMinAudioKbps(int $kbps): self { $this->minAudioKbps = max(64, $kbps); return $this; }
	public function setMinVideoKbps(int $kbps): self { $this->minVideoKbps = max(200, $kbps); return $this; }
	public function setUseHevc(bool $on): self { $this->useHevc = $on; return $this; }
	public function setPreferAudioCopy(bool $on): self { $this->preferAudioCopy = $on; return $this; }
	public function setDebug(bool $on): self { $this->debug = $on; return $this; }

	/**
	 * Convert all supported files from input -> output.
	 * @param int $qualityPercent Desired total bitrate percent of original (30..90). Example: 50 => ~half.
	 * @return array{converted:array<int,string>, skipped:array<int,string>, errors:array<int, array{file:string, message:string, output?:string[]}>}
	 */
	public function convertAll(int $qualityPercent = 50): array
	{
		$qualityPercent = max(30, min(90, $qualityPercent)); // clamp
		$converted = [];
		$skipped   = [];
		$errors    = [];

		$files = @scandir($this->inputDir) ?: [];
		foreach ($files as $file) {
			$inPath = $this->inputDir . DIRECTORY_SEPARATOR . $file;
			if (!is_file($inPath)) continue;

			$ext = strtolower(pathinfo($inPath, PATHINFO_EXTENSION));
			if (!in_array($ext, $this->allowedExt, true)) continue;

			$baseName = pathinfo($inPath, PATHINFO_FILENAME);
			$outPath  = $this->outputDir . DIRECTORY_SEPARATOR . $baseName . '.mp4';
			if (is_file($outPath)) { $skipped[] = $outPath; continue; }

			// Probe and size
			$probe     = $this->probe($inPath);
			$duration  = $probe['duration'] ?? 0.0;
			$origBytes = $this->getFileSizeBytes($inPath);

			if ($duration <= 0.0 || $origBytes <= 0) {
				$errors[] = ['file'=>$inPath, 'message'=>'Invalid duration or size'];
				continue;
			}

			// Original total bitrate (kbps); prefer format.bit_rate; else from bytes/duration
			$origTotalKbps = $this->estimateTotalKbps($probe, $origBytes);
			if ($origTotalKbps <= 0) {
				// Fallback to conservative estimate
				$origTotalKbps = max(1500, (int)round((($origBytes * 8) / $duration) / 1000));
			}

			// Audio policy: copy if friendly and >= 192k; else encode AAC >= 192k
			$aCodec = strtolower($probe['audio_codec'] ?? '');
			$aKbps  = (int)($probe['audio_kbps'] ?? 0);
			$audioCopy = false;
			$audioKbps = max($this->minAudioKbps, $aKbps ?: $this->minAudioKbps);
			if ($this->preferAudioCopy && $aKbps >= $this->minAudioKbps && in_array($aCodec, $this->mp4FriendlyAudio, true)) {
				$audioCopy = true;
				$audioKbps = $aKbps;
			}

			// Percent target
			$targetTotalKbps = (int)floor($origTotalKbps * ($qualityPercent / 100.0));

			// Allocate video kbps with guardrails
			$videoKbps = $targetTotalKbps - $audioKbps;
			$videoKbps = max($this->minVideoKbps, $videoKbps);
			// Never exceed original total bitrate
			$videoKbps = min($videoKbps, max(300, $origTotalKbps - $audioKbps));

			// If percent >= 100 (shouldn’t, due to clamp) or computed target >= original, skip to avoid bloat
			if (($videoKbps + $audioKbps) >= $origTotalKbps) {
				$skipped[] = $inPath . ' (skipped: target >= original bitrate)';
				continue;
			}

			if ($this->debug) {
				$dbg = [
					'file' => $file,
					'quality_percent' => $qualityPercent,
					'orig_total_kbps' => $origTotalKbps,
					'audio' => ['codec'=>$aCodec, 'src_kbps'=>$aKbps, 'mode'=>$audioCopy?'copy':'aac '.$audioKbps.'k'],
					'computed' => ['target_total_kbps' => $targetTotalKbps, 'video_kbps' => $videoKbps],
				];
				echo '[PLAN] ' . json_encode($dbg, JSON_UNESCAPED_SLASHES) . PHP_EOL;
			}

			// Build single-pass ABR command (fast)
			$cmd = $this->buildSinglePassCmd($inPath, $outPath, $videoKbps, $audioCopy, $audioKbps);
			$output = []; $returnVar = 0;
			// echo PHP_EOL.$cmd.PHP_EOL;
			exec($cmd, $output, $returnVar);

			if ($returnVar !== 0 || !is_file($outPath)) {
				$errors[] = ['file'=>$inPath, 'message'=>'FFmpeg conversion failed', 'output'=>$output];
				if (is_file($outPath)) @unlink($outPath);
				continue;
			}

			// If output larger than input, remove and mark as skipped (no gain)
			$outBytes = @filesize($outPath) ?: 0;
			if ($outBytes <= 0 || $outBytes >= $origBytes) {
				@unlink($outPath);
				$skipped[] = $inPath . ' (skipped: output >= input)';
				continue;
			}

			if ($this->deleteInputAfter) @unlink($inPath);
			$converted[] = $outPath;
		}

		return compact('converted','skipped','errors');
	}

	private function buildSinglePassCmd(string $in, string $out, int $videoKbps, bool $audioCopy, int $audioKbps): string
	{
		$ffmpeg = escapeshellcmd($this->ffmpegBin);
		$inEsc  = escapeshellarg($in);
		$outEsc = escapeshellarg($out);

		$vf = $this->scale ? ['-vf', 'scale=' . $this->scale] : [];

		$vCodec = $this->useHevc ? 'libx265' : 'libx264';
		$vTag   = $this->useHevc ? ['-tag:v', 'hvc1'] : [];
		$preset = $this->preset;

		// VBV to stabilize ABR and avoid spikes; speed-friendly values
		$maxrate = (int)round($videoKbps * 1.45);
		$bufsize = (int)round($videoKbps * 3.0);

		$aPart = $audioCopy ? ['-c:a','copy'] : ['-c:a','aac','-b:a', $audioKbps.'k'];

		$parts = array_merge(
			[$ffmpeg, '-y', '-hide_banner', '-loglevel', 'error', '-i', $inEsc],
			$vf,
			['-map','0:v:0','-map','0:a?','-map_metadata','0',
				'-c:v', $vCodec, '-b:v', $videoKbps.'k', '-maxrate', $maxrate.'k', '-bufsize', $bufsize.'k',
				'-preset', $preset],
			$vTag,
			['-pix_fmt','yuv420p'],
			$aPart,
			['-movflags', '+faststart', $outEsc]
		);

		return $this->joinCmd($parts);
	}

	/**
	 * Try to get file size by reading contents (per request). Falls back to filesize().
	 */
	private function getFileSizeBytes(string $path): int
	{
		return filesize($path) ?: 0;
	}

	/**
	 * Estimate original total bitrate (kbps) robustly.
	 */
	private function estimateTotalKbps(array $probe, int $origBytes): int
	{
		if (!empty($probe['format_kbps']) && $probe['format_kbps'] > 0) {
			return (int)$probe['format_kbps'];
		}
		$duration = $probe['duration'] ?? 0.0;
		if ($duration > 0 && $origBytes > 0) {
			return (int)round((($origBytes * 8) / $duration) / 1000);
		}
		return 0;
	}

	/**
	 * Probe using ffprobe for duration, audio/video codecs, container bit rate, and audio bitrate.
	 * @return array{duration?: float, audio_codec?: string, audio_kbps?: int, video_codec?: string, format_kbps?: int}
	 */
	private function probe(string $in): array
	{
		$cmd = sprintf(
			'%s -v error -print_format json -show_format -show_streams %s',
			escapeshellcmd($this->ffprobeBin),
			escapeshellarg($in)
		);
		$out = []; $ret = 0;
		exec($cmd, $out, $ret);
		if ($ret !== 0) return [];

		$json = @json_decode(implode("\n", $out), true);
		if (!is_array($json)) return [];

		$duration = isset($json['format']['duration']) ? (float)$json['format']['duration'] : null;
		$formatBitrate = isset($json['format']['bit_rate']) ? (int)$json['format']['bit_rate'] : null;

		$aCodec = null; $aBit = null; $vCodec = null;
		if (!empty($json['streams']) && is_array($json['streams'])) {
			foreach ($json['streams'] as $st) {
				$type = $st['codec_type'] ?? '';
				if ($type === 'audio' && $aCodec === null) {
					$aCodec = $st['codec_name'] ?? null;
					if (!empty($st['bit_rate'])) $aBit = (int)$st['bit_rate'];
				}
				if ($type === 'video' && $vCodec === null) {
					$vCodec = $st['codec_name'] ?? null;
				}
			}
		}

		return [
			'duration'     => $duration ?? 0.0,
			'audio_codec'  => $aCodec,
			'audio_kbps'   => $aBit ? (int)round($aBit / 1000) : null,
			'video_codec'  => $vCodec,
			'format_kbps'  => $formatBitrate ? (int)round($formatBitrate / 1000) : null,
		];
	}

	/**
	 * Join command parts safely, removing empty pieces but preserving compound tokens.
	 */
	private function joinCmd(array $parts): string
	{
		$parts = array_values(array_filter($parts, static function($p) { return $p !== '' && $p !== null; }));
		return implode(' ', $parts);
	}
}
