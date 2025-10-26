<?php

namespace Autoframe\Core\YoutubeToMp3;


use InvalidArgumentException;
use RuntimeException;

/**
 * Class AfrYoutubeToMp3
 *
 *
 * Converts supported video files (mp4, mkv, webm, mov, avi) from a given input
 * directory into MP3 files at a specified bitrate (default 192 kbps).
 * Uses `ffmpeg` via PHP's `exec()` function.
 *
 * ──────────────────────────────
 * ⚙️ Requirements:
 * - PHP 7.4+ (tested on PHP 8.1)
 * - FFmpeg installed and available in your system PATH
 *
 * ──────────────────────────────
 * 📥 Installing FFmpeg:
 *
 * Windows:
 *   1. Download from: https://www.gyan.dev/ffmpeg/builds/
 *   2. Extract to: C:\ffmpeg\
 *   3. Add to PATH:
 *        - Press Win+R → type `sysdm.cpl`
 *        - Go to "Advanced" → "Environment Variables"
 *        - Edit "Path" under System variables
 *        - Add: C:\ffmpeg\bin
 *   4. Restart PowerShell/Command Prompt
 *   5. Verify:
 *        ffmpeg -version
 *
 * Linux (Debian/Ubuntu):
 *   sudo apt update
 *   sudo apt install ffmpeg
 *   ffmpeg -version
 *
 * Linux (CentOS/RHEL/Fedora):
 *   sudo dnf install ffmpeg ffmpeg-devel
 *   ffmpeg -version
 *
 * macOS (via Homebrew):
 *   brew install ffmpeg
 *   ffmpeg -version
 *
 * ──────────────────────────────
 * 📌 Usage Example:
 *
 *   require 'AfrYoutubeToMp3.php';
 *
 *   $conv = new AfrYoutubeToMp3(__DIR__ . '/playlist_in', __DIR__ . '/playlist_out');
 *   $conv->keepInputFiles(true)     // true = keep sources, false = delete after success
 *        ->setBitrate(192);         // optional, defaults to 192 kbps
 *
 *   $result = $conv->convertAll();
 *   print_r($result);
 *
 * ──────────────────────────────
 * ✅ Returns an array with:
 *   - converted: list of MP3 files created
 *   - skipped:   files skipped (already converted)
 *   - errors:    failed conversions with details
 *
 * EXTRA YOUTUBE DOWNLOAD:
 * Step-by-Step Setup
 * 🔹 1. Install yt-dlp (no need for Python separately anymore)
 * Windows (XAMPP / PHP 8.1):
 *
 * Download the binary:
 * 👉 https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe
 *
 * Save it to:
 *
 * C:\ffmpeg\yt-dlp.exe
 *
 */

class AfrVideoToMp3
{
	private string $inputDir;
	private string $outputDir;
	private bool   $deleteInputAfter = false;
	private int    $bitrateKbps = 192;
	private array  $allowedExt = ['mp4','mkv','webm','mov','avi'];
	private string $ffmpegBin = 'ffmpeg';
	private string $ffprobeBin = 'ffprobe';
	private int    $coverSize = 400; // default cover size (px)


	/**
	 * Constructor requires input and output directories.
	 */
	public function __construct(string $inputDir, string $outputDir)
	{
		$this->setInputDir($inputDir);
		$this->setOutputDir($outputDir);
	}

	public function setCoverSize(int $size): self
	{
		if ($size < 100 || $size > 2000) {
			throw new InvalidArgumentException("Cover size must be between 100 and 2000 px");
		}
		$this->coverSize = $size;
		return $this;
	}

	public function setInputDir(string $dir): self
	{
		$dir = rtrim($dir, "\\/");
		if (!is_dir($dir))            throw new InvalidArgumentException("Input directory does not exist: {$dir}");
		if (!is_readable($dir))       throw new InvalidArgumentException("Input directory is not readable: {$dir}");
		$this->inputDir = $dir;
		return $this;
	}

	public function setOutputDir(string $dir): self
	{
		$dir = rtrim($dir, "\\/");
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new RuntimeException("Failed to create output directory: {$dir}");
		}
		if (!is_writable($dir)) {
			throw new InvalidArgumentException("Output directory is not writable: {$dir}");
		}
		$this->outputDir = $dir;
		return $this;
	}

	public function keepInputFiles(bool $keep): self
	{
		$this->deleteInputAfter = !$keep;
		return $this;
	}

	public function setBitrate(int $kbps): self
	{
		if ($kbps < 64 || $kbps > 320) {
			throw new InvalidArgumentException("Bitrate out of reasonable range: {$kbps} kbps");
		}
		$this->bitrateKbps = $kbps;
		return $this;
	}

	public function setFfmpegBinary(string $path): self { $this->ffmpegBin = $path; return $this; }
	public function setFfprobeBinary(string $path): self { $this->ffprobeBin = $path; return $this; }

	/**
	 * Convert all supported files from input -> output.
	 * @return array{converted:array<int, string>, skipped:array<int, string>, errors:array<int, array{file:string, message:string, output?:string[]}>}
	 */
	public function convertAll(): array
	{
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
			$outPath  = $this->outputDir . DIRECTORY_SEPARATOR . $baseName . '.mp3';

			if (is_file($outPath)) { $skipped[] = $outPath; continue; }

			// 1) Extract a good cover PNG from the middle (with fallbacks)
			$tmpCover = $this->outputDir . DIRECTORY_SEPARATOR . $baseName . '_cover.jpg';
			$okCover  = $this->extractCoverJpg($inPath, $tmpCover);

			if (!$okCover) {
				$errors[] = [
					'file'    => $inPath,
					'message' => 'Failed to extract cover image (tried multiple midpoints)',
				];
				// Continue without cover? If you prefer to skip the track entirely, `continue;`
				// For now, continue without cover by setting $tmpCover = ''.
				$tmpCover = '';
			}

			// 2) Convert to MP3 and embed cover (if we have one)
			$cmd = $this->buildMp3Command($inPath, $outPath, $tmpCover);
			$output = []; $returnVar = 0;
			echo PHP_EOL.$cmd.PHP_EOL;
			exec($cmd, $output, $returnVar);

			// cleanup tmp cover file
			if ($tmpCover !== '' && is_file($tmpCover)) @unlink($tmpCover);

			if ($returnVar !== 0 || !is_file($outPath)) {
				$errors[] = ['file'=>$inPath, 'message'=>'FFmpeg conversion failed', 'output'=>$output];
				if (is_file($outPath)) @unlink($outPath);
				continue;
			}

			if ($this->deleteInputAfter) @unlink($inPath);

			$converted[] = $outPath;
		}

		return compact('converted', 'skipped', 'errors');
	}

	/**
	 * Extract a JPG cover from the middle of the video (with fallbacks).
	 * Returns true on success.
	 */
	private function extractCoverJpg(string $input, string $coverJpg): bool
	{
		$duration = $this->probeDurationSeconds($input);
		$candidates = [];

		if ($duration !== null && $duration > 8) {
			$candidates[] = (int)round($duration * 0.50);
			$candidates[] = (int)round($duration * 0.33);
			$candidates[] = (int)round($duration * 0.66);
		} else {
			$candidates = [5, 2, 8];
		}

		$sCoverSize = $this->coverSize.':'.$this->coverSize;

		foreach ($candidates as $ss) {
			// Extract JPEG 400x400
			$cmd = sprintf(
				'%s -y -i %s -ss %d -frames:v 1 -vf "scale=%s:force_original_aspect_ratio=decrease,pad=%s:(ow-iw)/2:(oh-ih)/2:black" -q:v 3 -f image2 %s 2>&1',
				escapeshellcmd($this->ffmpegBin),
				escapeshellarg($input),
				$ss,
				$sCoverSize,
				$sCoverSize,
				escapeshellarg($coverJpg)
			);
			$out = []; $ret = 0;
			exec($cmd, $out, $ret);

			if ($ret === 0 && is_file($coverJpg) && filesize($coverJpg) > 1024) {
				return true;
			}
			if (is_file($coverJpg)) @unlink($coverJpg);
		}

		// Final fallback: frame at 1s
		$cmd = sprintf(
			'%s -y -i %s -ss 1 -frames:v 1 -vf "scale=400:400:force_original_aspect_ratio=decrease,pad=400:400:(ow-iw)/2:(oh-ih)/2:black" -q:v 3 -f image2 %s 2>&1',
			escapeshellcmd($this->ffmpegBin),
			escapeshellarg($input),
			escapeshellarg($coverJpg)
		);
		$out = []; $ret = 0;
		exec($cmd, $out, $ret);

		if ($ret === 0 && is_file($coverJpg) && filesize($coverJpg) > 1024) {
			return true;
		}
		if (is_file($coverJpg)) @unlink($coverJpg);
		return false;
	}

	/**
	 * Build ffmpeg command that converts to MP3 and embeds cover (JPG) as attached picture if provided.
	 */
	private function buildMp3Command(string $input, string $output, string $coverJpg): string
	{
		if ($coverJpg !== '' && is_file($coverJpg)) {
			return sprintf(
				'%s -y -i %s -i %s -map 0:a -map 1:v ' .
				'-c:a libmp3lame -b:a %dk -id3v2_version 3 -write_id3v1 1 ' .
				'-c:v:1 mjpeg -disposition:v:0 attached_pic ' .
				'-metadata:s:v title="cover" -metadata:s:v comment="Cover (front)" %s 2>&1',
				escapeshellcmd($this->ffmpegBin),
				escapeshellarg($input),
				escapeshellarg($coverJpg),
				$this->bitrateKbps,
				escapeshellarg($output)
			);
		}

		return sprintf(
			'%s -y -i %s -vn -ar 44100 -ac 2 -c:a libmp3lame -b:a %dk -id3v2_version 3 -write_id3v1 1 %s 2>&1',
			escapeshellcmd($this->ffmpegBin),
			escapeshellarg($input),
			$this->bitrateKbps,
			escapeshellarg($output)
		);
	}


	/**
	 * Return duration in seconds (float) or null on failure.
	 */
	private function probeDurationSeconds(string $input): ?float
	{
		$cmd = sprintf(
			'%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
			escapeshellcmd($this->ffprobeBin),
			escapeshellarg($input)
		);
		$out = []; $ret = 0;
		exec($cmd, $out, $ret);
		if ($ret === 0 && isset($out[0]) && is_numeric($out[0])) {
			return (float)$out[0];
		}
		return null;
	}
}
