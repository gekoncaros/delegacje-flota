<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleFleetService.php';

use Delegacje\Fleet\VehicleFleetService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$payload = json_input();
try {
    $id = (new VehicleFleetService($pdo))->saveVehicle($user, $payload);
    api_audit($pdo, (int) $user['id'], empty($payload['id']) ? 'vehicle.create' : 'vehicle.update', 'vehicle', $id, [
        'status' => (string) ($payload['status'] ?? ''),
        'assigned_user_id' => empty($payload['assigned_user_id']) ? null : (int) $payload['assigned_user_id'],
    ]);
    api_response(['ok' => true, 'id' => $id], empty($payload['id']) ? 201 : 200);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
