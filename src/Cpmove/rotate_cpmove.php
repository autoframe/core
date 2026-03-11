#!/usr/bin/env php
<?php
// And here’s a compact PHP rotation script you can drop at /root/rotate_cpmove.php:
// rotate_cpmove.php — timestamp + rotate cpmove archives for one user
// Usage for rotation: php /root/rotate_cpmove.php --dir=/backup --user=allviewera --keep=8 --src=/home/allviewera

/*
 SETUP:
mkdir -p /backup #Create the destination (only once):
chmod 700 /root/rotate_cpmove.php #Save the rotation script at /root/rotate_cpmove.php, make it executable:

On a WHM/root server you can cron pkgacct to create a cpmove archive of allviewera.
Use ionice/nice reduce impact on a busy server.

A simple weekly Cron job to /backup plus rotation is:

0 3 * * 0 root (ionice -c2 -n7 nice -n19 /usr/local/cpanel/scripts/pkgacct allviewera /backup \
  && /usr/local/bin/php /root/rotate_cpmove.php --dir=/backup --user=allviewera --keep=8 --src=/home/allviewera) \
  >> /backup/pkgacct-allviewera.log 2>&1


SSH add cron in whm:
cat /etc/cron.d/pkgacct-allviewera

SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
MAILTO=root

0 3 * * 0 root (ionice -c2 -n7 nice -n19 /usr/local/cpanel/scripts/pkgacct allviewera /backup && /usr/local/bin/php /root/rotate_cpmove.php --dir=/backup --user=allviewera --keep=8 --src=/home/allviewera) >> /backup/pkgacct-allviewera.log 2>&1


 */

$opt = getopt('', ['dir:', 'user:', 'keep::', 'src::', 'log::']);
$dir  = rtrim($opt['dir']  ?? '/backup', '/');
$user =        $opt['user'] ?? '';
$keep = max(1, (int)($opt['keep'] ?? 8));
$src  = rtrim($opt['src']  ?? '', '/');
$log  =        $opt['log']  ?? "/var/log/pkgacct-{$user}.log";

if ($user === '') { fwrite(STDERR, "Missing --user\n"); exit(2); }
if (!is_dir($dir)) { fwrite(STDERR, "Backup dir $dir not found\n"); exit(2); }

$lock = fopen("$dir/.rotate_$user.lock", 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);
$logf = function($m) use ($log){ file_put_contents($log, '['.date('c')."] $m\n", FILE_APPEND); };

$base = "$dir/cpmove-$user.tar.gz";
if (!file_exists($base) && $src) {
	$alt = "$src/cpmove-$user.tar.gz";
	if (file_exists($alt)) {
		if (!@rename($alt, $base)) { @copy($alt, $base) && @unlink($alt); }
	}
}
if (!file_exists($base)) { $logf("No new cpmove at $base; nothing to rotate."); exit(0); }

$ts   = date('Ymd-His', filemtime($base));
$dest = "$dir/cpmove-$user-$ts.tar.gz";
$i=0; $cand=$dest; while (file_exists($cand)) { $i++; $cand="$dest.$i"; }
$dest=$cand;

if (@rename($base, $dest) || (@copy($base,$dest) && @unlink($base))) {
	$logf("Archived: ".basename($dest));
} else { $logf("ERROR: cannot move/copy $base to $dest"); exit(1); }

// keep newest N
$files = glob("$dir/cpmove-$user-*.tar.gz") ?: [];
usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
foreach (array_slice($files, $keep) as $f) {
	@unlink($f) ? $logf("Deleted old: ".basename($f)) : $logf("WARN: cannot delete ".basename($f));
}
flock($lock, LOCK_UN);
