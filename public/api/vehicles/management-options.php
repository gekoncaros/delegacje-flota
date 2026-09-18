<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleFleetService.php';

use Delegacje\Fleet\VehicleFleetService;

require_method('GET');
$user = current_api_user($auth);
try {
    api_response(['ok' => true, 'users' => (new VehicleFleetService($pdo))->managementOptions($user)]);
} catch (RuntimeException $e) {
    api_exception($e, 403);
}
