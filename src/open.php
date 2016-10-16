<?php
declare(strict_types=1);

namespace Phocate;

use PDO;

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../vendor/autoload.php';
$in = $argv[1];
$like = '%';
for($i = 0; isset($in[$i]); $i += 1) {
    $like .= $in[$i] . '%';
}
$pdo = new PDO('sqlite:phocate.db');
$pdo->exec('PRAGMA case_sensitive_like=ON;');
$stmt = $pdo->prepare(
    "SELECT class_path FROM classes WHERE FQN LIKE ? ORDER BY LENGTH(FQN)"
);
$stmt->execute([$like]);
$results = $stmt->fetchAll();
if (empty($results)) {
    echo "No Class matched\n";
} else {
    $path = $results[0]['class_path'];
    $cmd = preg_split('/\s+/', getenv('EDITOR'));
    $editor = array_shift($cmd);
    if ($editor[0] !== '/') {
        $which = popen('which $EDITOR', 'r');
        $editor = trim(stream_get_contents($which));
        pclose($which);
    }
    pcntl_exec($editor, array_merge($cmd, [$path]));
}
