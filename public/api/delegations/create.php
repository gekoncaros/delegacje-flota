<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Application/DelegationWorkflowService.php';

use Delegacje\Application\DelegationWorkflowService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$service = new DelegationWorkflowService($pdo);

try {
    $item = $service->create($user, json_input());
    api_response(['ok' => true, 'item' => $item], 201);
} catch (RuntimeException $e) {
    api_error($e->getMessage(), 422);
}
