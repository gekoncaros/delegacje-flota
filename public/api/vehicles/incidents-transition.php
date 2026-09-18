<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_api.php';
require_once dirname(__DIR__, 3) . '/src/Fleet/VehicleIncidentService.php';

use Delegacje\Fleet\VehicleIncidentService;

require_method('POST');
require_csrf();
$user=current_api_user($auth);
$payload=json_input();
$id=(int)($payload['id']??0);
$action=trim((string)($payload['action']??''));
$assignee=isset($payload['assigned_to_user_id'])?(int)$payload['assigned_to_user_id']:null;
$note=(string)($payload['note']??'');
if($id<1||$action==='') api_error('Brak danych operacji.',422);
$service=new VehicleIncidentService($pdo);
try{
    $service->transition($user,$id,$action,$assignee,$note);
    api_audit($pdo, (int) $user['id'], 'vehicle_incident.'.$action, 'vehicle_incident', $id, ['assigned_to_user_id' => $assignee]);
    api_response(['ok'=>true]);
}
catch(RuntimeException $e){api_exception($e,422);}
