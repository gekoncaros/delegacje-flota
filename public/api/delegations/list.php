<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Application/DelegationWorkflowService.php';

use Delegacje\Application\DelegationWorkflowService;

require_method('GET');
$user = current_api_user($auth);
$service = new DelegationWorkflowService($pdo);
api_response(['ok' => true, 'items' => $service->listForUser($user)]);
