<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Accounting/ExpenseService.php';

use Delegacje\Accounting\ExpenseService;

require_method('GET');
$user = current_api_user($auth);
api_response(['ok' => true, 'items' => (new ExpenseService($pdo))->listForUser($user)]);
