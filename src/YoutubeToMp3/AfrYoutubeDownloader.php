<?php
namespace Autoframe\Core\YoutubeToMp3;

use RuntimeException;

/**
 * AfrYoutubeDownloader
 *
 * Windows (XAMPP / PHP 8.1):
 *
 * Download the binary:
 * 👉 https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe
 *
 * Save it to: C:\ffmpeg\yt-dlp.exe
 * Ensure ffmpeg.exe is also in: C:\ffmpeg\ffmpeg.exe
 * Add C:\ffmpeg\ to your system PATH.
 *
 * Download individual YouTube videos or playlists to `.mp4` using `yt-dlp`.
 * Assumes:
 * - yt-dlp is installed and available in PATH (or set via setYtDlpBinary)
 * - ffmpeg is also available in PATH for post-processing
 *
 * This class does NOT use YouTube APIs or keys.
 *
 * Example:
 *   $dl = new AfrYoutubeDownloader(__DIR__ . '/videos');
 *   $dl->download('https://www.youtube.com/watch?v=...');
 */
class AfrYoutubeDownloader
{
	private string $outputDir;
	private string $ytDlpBin = 'yt-dlp';
	private int $maxRetries = 2;

	public function __construct(string $outputDir)
	{
		$this->setOutputDir($outputDir);
	}

	public function setOutputDir(string $dir): self
	{
		$dir = rtrim($dir, "\\/");
		if (!is_dir($dir)) {
			if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
				throw new RuntimeException("Cannot create output directory: $dir");
			}
		}
		if (!is_writable($dir)) {
			throw new RuntimeException("Output directory is not writable: $dir");
		}
		$this->outputDir = $dir;
		return $this;
	}

	public function setYtDlpBinary(string $path): self
	{
		$this->ytDlpBin = $path;
		return $this;
	}

	public function setMaxRetries(int $retries): self
	{
		$this->maxRetries = max(0, min(5, $retries));
		return $this;
	}

	/**
	 * @return array{success: bool, command: string, output: string[], error?: string, info: string}
	 */
	public function downloadMp4ConvertMp3(string $url): array
	{
		if (!filter_var($url, FILTER_VALIDATE_URL) || !str_contains($url, 'youtube.com')) {
			return ['success' => false, 'command' => '', 'output' => [], 'error' => 'Invalid YouTube URL'];
		}

		$ffmpeg_location = $this->detectFfmpegDirectory();

		$cmd = sprintf(
			'%s --no-playlist --retries %d %s --extract-audio --audio-format mp3 --audio-quality 0 --output "%s'.DIRECTORY_SEPARATOR.'%%(title)s.%%(ext)s" %s 2>&1',
			escapeshellcmd($this->ytDlpBin),
			$this->maxRetries,
			$ffmpeg_location !== '.' ? '--ffmpeg-location ' . escapeshellarg($ffmpeg_location) : '',
			$this->outputDir,
			escapeshellarg($url)
		);

		return $this->downloadExec($cmd, $url);
	}

	/**
	 * Use for full playlist (videos as MP4)
	 * @return array{success: bool, command: string, output: string[], error?: string, info: string}
	 */
	public function downloadPlaylist(string $url, string $quality = '720p'): array
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return ['success' => false, 'command' => '', 'output' => [], 'error' => 'Invalid URL'];
		}

		$ffmpeg_location = $this->detectFfmpegDirectory();

		// choose format selector based on quality
		$formats = [
			'720p'   => "bestvideo[ext=mp4][height<=720]+bestaudio[ext=m4a]/best[ext=mp4][height<=720]",
			'1080p'  => "bestvideo[ext=mp4][height<=1080]+bestaudio[ext=m4a]/best[ext=mp4][height<=1080]",
			'480p'   => "bestvideo[ext=mp4][height<=480]+bestaudio[ext=m4a]/best[ext=mp4][height<=480]",
			'best'=> "best[ext=mp4]",
		];
		$format = $formats[$quality] ?? $formats['best'];

		$cmd = sprintf(
			'%s --yes-playlist --format %s --retries %d %s --output "%s'.DIRECTORY_SEPARATOR.'%%(playlist_index)s - %%(title)s.%%(ext)s" %s 2>&1',
			escapeshellcmd($this->ytDlpBin),
			escapeshellarg($format),
			$this->maxRetries,
			$ffmpeg_location !== '.' ? '--ffmpeg-location ' . escapeshellarg($ffmpeg_location) : '',
			$this->outputDir,
			escapeshellarg($url)
		);

		return $this->downloadExec($cmd, $url);
	}

	/**
	 * @param string $cmd
	 * @param string $url
	 * @return array{success: bool, command: string, output: string[], error?: string, info: string}
	 */
	protected function downloadExec(string $cmd, string $url): array
	{
		$output = [];
		$retVal = 0;
		exec($cmd, $output, $retVal);

		$bSuccess = $retVal === 0;
		return [
			'success' => $bSuccess,
			'command' => $cmd,
			'output' => $output,
			'error' => $retVal === 0 ? null : 'Download failed',
			'info' => $bSuccess ?
				"✅ Downloaded successfully: `$url`\n" :
				"❌ Error: {$url}\n" .
				"👉 Command: {$cmd}\n" .
				implode("\n", $output)
		];
	}

	/**
	 * @return string
	 */
	public function detectFfmpegDirectory(): string
	{
		static $cached = null;
		if ($cached !== null) {
			return $cached;
		}

		// 1. Check environment variable
		if (!empty($_SERVER['FFMPEG_PATH']) && is_executable($_SERVER['FFMPEG_PATH'])) {
			return $cached = dirname($_SERVER['FFMPEG_PATH']);
		}

		// 2. Probe via shell
		$probeCmd = stripos(PHP_OS, 'WIN') === 0 ? 'where ffmpeg' : 'which ffmpeg';
		$probeOut = [];
		$probeRet = 1;
		@exec($probeCmd . ' 2>&1', $probeOut, $probeRet);

		if ($probeRet === 0 && !empty($probeOut[0]) && is_executable($probeOut[0])) {
			return $cached = dirname($probeOut[0]);
		}

		// 3. Common Windows fallback
		if (stripos(PHP_OS, 'WIN') === 0) {
			$default = 'C:\\ffmpeg\\bin';
			if (is_dir($default)) {
				return $cached = $default;
			}
		}

		// 4. Fallback: no directory found
		return $cached = '.';
	}
	/**
	 * Download a single YouTube video as MP4 (not a playlist).
	 *
	 * @param string $url     YouTube watch URL
	 * @param string $quality One of: '1080p','720p','480p','best' (default '720p')
	 * @param bool   $preferH264 If true, prefer H.264/AVC video (better Windows compatibility). Falls back gracefully.
	 * @return array{success: bool, command: string, output: string[], error?: string, info: string}
	 */
	public function downloadMp4(string $url, string $quality = '720p', bool $preferH264 = true): array
	{
		if (!filter_var($url, FILTER_VALIDATE_URL) || !str_contains($url, 'youtube.')) {
			return ['success' => false, 'command' => '', 'output' => [], 'error' => 'Invalid YouTube URL'];
		}

		$ffmpeg_location = $this->detectFfmpegDirectory();

		// Quality → yt-dlp format selectors (prefer mp4 container + m4a audio; graceful fallbacks)
		// We optionally bias for H.264/AVC (avc1) to avoid HEVC/VP9 playback issues on older systems.
		$base = $preferH264
			? "bestvideo[ext=mp4][vcodec~='^avc1|h264'][height<=%d]+bestaudio[ext=m4a]/best[ext=mp4][height<=%d]/bestvideo[height<=%d]+bestaudio/best"
			: "bestvideo[ext=mp4][height<=%d]+bestaudio[ext=m4a]/best[ext=mp4][height<=%d]/bestvideo[height<=%d]+bestaudio/best";

		$map = [
			'1080p' => sprintf($base, 1080, 1080, 1080),
			'720p'  => sprintf($base, 720, 720, 720),
			'480p'  => sprintf($base, 480, 480, 480),
			'best'  => $preferH264
				? "bestvideo[ext=mp4][vcodec~='^avc1|h264']+bestaudio[ext=m4a]/best[ext=mp4]/bestvideo+bestaudio/best"
				: "bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/bestvideo+bestaudio/best",
		];
		$format = $map[$quality] ?? $map['best'];

		// --merge-output-format mp4 forces the final container to mp4 when merging separate streams.
		// If only webm is available, yt-dlp may remux to mp4 where possible; if codecs are incompatible,
		// yt-dlp/ffmpeg will still emit a playable result or fail cleanly.
		$cmd = sprintf(
			'%s --no-playlist --format %s --merge-output-format mp4 --retries %d %s ' .
			'--output "%s' . DIRECTORY_SEPARATOR . '%%(title)s.%%(ext)s" %s 2>&1',
			escapeshellcmd($this->ytDlpBin),
			escapeshellarg($format),
			$this->maxRetries,
			$ffmpeg_location !== '.' ? '--ffmpeg-location ' . escapeshellarg($ffmpeg_location) : '',
			$this->outputDir,
			escapeshellarg($url)
		);

		return $this->downloadExec($cmd, $url);
	}
}
