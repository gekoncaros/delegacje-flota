<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Application/DelegationWorkflowService.php';

use Delegacje\Application\DelegationWorkflowService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$data = json_input();
$id = (int)($data['id'] ?? 0);
$action = trim((string)($data['action'] ?? ''));
if ($id < 1 || $action === '') api_error('Brak delegacji lub operacji.', 422);

$service = new DelegationWorkflowService($pdo);
try {
    $item = $service->transition($id, $user, $action, $data);
    api_audit($pdo, (int) $user['id'], 'delegation.'.$action, 'delegation', $id);
    api_response(['ok' => true, 'item' => $item]);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
