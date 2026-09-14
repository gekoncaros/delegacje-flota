<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleReservationService.php';

use Delegacje\Fleet\VehicleReservationService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$service = new VehicleReservationService($pdo);
try {
    $id = $service->create($user, json_input());
    api_response(['ok'=>true,'id'=>$id], 201);
} catch (RuntimeException $e) {
    api_error($e->getMessage(), 422);
}
