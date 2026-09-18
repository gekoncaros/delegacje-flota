<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleReservationService.php';

use Delegacje\Fleet\VehicleReservationService;

require_method('POST');
require_csrf();
$user = current_api_user($auth);
$payload = json_input();
$id = (int)($payload['id'] ?? 0);
if ($id < 1) api_error('Brak identyfikatora rezerwacji.', 422);
$service = new VehicleReservationService($pdo);
try {
    $service->cancel($user, $id);
    api_audit($pdo, (int) $user['id'], 'vehicle_reservation.cancel', 'vehicle_reservation', $id);
    api_response(['ok'=>true]);
} catch (RuntimeException $e) {
    api_exception($e, 422);
}
