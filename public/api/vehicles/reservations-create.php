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
    $payload = json_input();
    $id = $service->create($user, $payload);
    api_audit($pdo, (int) $user['id'], 'vehicle_reservation.create', 'vehicle_reservation', $id, [
        'vehicle_id' => (int) ($payload['vehicle_id'] ?? 0),
        'delegation_id' => isset($payload['delegation_id']) ? (int) $payload['delegation_id'] : null,
    ]);
    api_response(['ok'=>true,'id'=>$id], 201);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
