<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleFleetService.php';

use Delegacje\Fleet\VehicleFleetService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);

try {
    $payload = json_input();
    $service = new VehicleFleetService($pdo);
    $id = $service->createIncident($user, $payload);
    api_audit($pdo, (int) $user['id'], 'vehicle_incident.create', 'vehicle_incident', $id, [
        'vehicle_id' => (int) ($payload['vehicle_id'] ?? 0),
        'unsafe_to_drive' => filter_var($payload['unsafe_to_drive'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ]);
    api_response(['ok' => true, 'id' => $id], 201);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
