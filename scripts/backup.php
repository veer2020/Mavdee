#!/usr/bin/env php
<?php
/**
 * scripts/backup.php
 * Creates a timestamped mysqldump backup and prunes backups older than 30 days.
 * Usage: php scripts/backup.php
 */
require_once dirname(__DIR__) . '/config/config.php';

$backupDir = dirname(__DIR__) . '/private/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

$tmpDir = dirname(__DIR__) . '/private/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0700, true);
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

if (!$dbUser || !$dbPass || !$dbName) {
    echo "❌ Database credentials not set in environment.\n";
    exit(1);
}

$cnfFile = $tmpDir . '/.my.cnf.' . uniqid();
umask(0077);
file_put_contents($cnfFile, "[client]\npassword=\"" . addcslashes($dbPass, '\\"') . "\"\n");
chmod($cnfFile, 0600);

$filename = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
$command = sprintf(
    'mysqldump --defaults-extra-file=%s -h%s -u%s %s > %s 2>&1',
    escapeshellarg($cnfFile),
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbName),
    escapeshellarg($filename)
);

system($command, $exitCode);
unlink($cnfFile);

if ($exitCode === 0) {
    echo "✅ Backup created: {$filename}\n";
    foreach (glob($backupDir . '/*.sql') as $file) {
        if (filemtime($file) < strtotime('-30 days')) {
            unlink($file);
        }
    }
} else {
    echo "❌ Backup failed (exit code {$exitCode})!\n";
    exit(1);
}
