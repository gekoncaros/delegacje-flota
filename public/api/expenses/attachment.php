<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Accounting/ExpenseService.php';

use Delegacje\Accounting\ExpenseService;

require_method('GET');
$user = current_api_user($auth);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    api_error('Brak dokumentu.', 422);
}
$attachment = (new ExpenseService($pdo))->attachmentForUser($user, $id);
if ($attachment === null) {
    api_error('Nie znaleziono dokumentu.', 404);
}
$path = dirname(__DIR__, 3) . '/storage/expenses/' . basename((string) $attachment['stored_name']);
if (!is_file($path) || !is_readable($path)) {
    api_error('Dokument nie jest dostępny.', 404);
}

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="dokument-kosztu.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
