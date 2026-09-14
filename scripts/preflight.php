<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = [];
$checks[] = ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>=')];
foreach (['pdo', 'pdo_mysql', 'json', 'fileinfo', 'mbstring'] as $ext) {
    $checks[] = ["Extension: {$ext}", extension_loaded($ext)];
}

$root = dirname(__DIR__);
$checks[] = ['config/app.php exists', is_file($root . '/config/app.php')];
$checks[] = ['public directory exists', is_dir($root . '/public')];
$checks[] = ['storage directory writable', is_dir($root . '/storage') && is_writable($root . '/storage')];
$checks[] = ['uploads directory writable', is_dir($root . '/uploads') && is_writable($root . '/uploads')];

$allOk = true;
foreach ($checks as [$label, $ok]) {
    $allOk = $allOk && $ok;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
}

echo PHP_EOL;
echo $allOk
    ? "Preflight passed. Environment is ready for integration tests." . PHP_EOL
    : "Preflight failed. Fix the items marked FAIL before production deployment." . PHP_EOL;

exit($allOk ? 0 : 1);
