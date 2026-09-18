<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Accounting/ExpenseService.php';

use Delegacje\Accounting\ExpenseService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$payload = json_input();
$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = trim((string) ($payload['action'] ?? ''));
if ($id === false || $id === null || $action === '') {
    api_error('Brak kosztu lub operacji.', 422);
}
try {
    (new ExpenseService($pdo))->transition($user, $id, $action, $payload['note'] ?? null);
    api_audit($pdo, (int) $user['id'], 'expense.'.$action, 'expense', $id);
    api_response(['ok' => true]);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
