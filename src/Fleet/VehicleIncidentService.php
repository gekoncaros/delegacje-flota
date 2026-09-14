<?php
declare(strict_types=1);

namespace Delegacje\Fleet;

use PDO;
use RuntimeException;

final class VehicleIncidentService
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(array $user): array
    {
        $roles=$user['roles']??[];
        $all=in_array('fleet_admin',$roles,true)||in_array('super_admin',$roles,true);
        $sql='SELECT i.*,v.make,v.model,v.registration_number,u.display_name AS reporter_name,a.display_name AS assigned_name
              FROM vehicle_incidents i
              JOIN vehicles v ON v.id=i.vehicle_id
              JOIN auth_users u ON u.id=i.user_id
              LEFT JOIN auth_users a ON a.id=i.assigned_to_user_id';
        $params=[];
        if(!$all){$sql.=' WHERE i.user_id=:uid';$params['uid']=(int)$user['id'];}
        $sql.=' ORDER BY FIELD(i.status,\'open\',\'in_progress\',\'resolved\'),i.created_at DESC LIMIT 250';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transition(array $user,int $id,string $action,?int $assignee,string $note): void
    {
        $roles=$user['roles']??[];
        if(!in_array('fleet_admin',$roles,true)&&!in_array('super_admin',$roles,true)) throw new RuntimeException('Brak uprawnień administratora floty.');
        $stmt=$this->pdo->prepare('SELECT * FROM vehicle_incidents WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$incident=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$incident) throw new RuntimeException('Nie znaleziono zgłoszenia.');
        if($action==='assign'){
            if(!$assignee) throw new RuntimeException('Wskaż osobę odpowiedzialną.');
            $q=$this->pdo->prepare("UPDATE vehicle_incidents SET assigned_to_user_id=:a,status='in_progress',updated_at=NOW() WHERE id=:id");$q->execute(['a'=>$assignee,'id'=>$id]);return;
        }
        if($action==='start'){
            $q=$this->pdo->prepare("UPDATE vehicle_incidents SET status='in_progress',assigned_to_user_id=COALESCE(:a,assigned_to_user_id),updated_at=NOW() WHERE id=:id");$q->execute(['a'=>$assignee,'id'=>$id]);return;
        }
        if($action==='resolve'){
            if(trim($note)==='') throw new RuntimeException('Dodaj opis wykonanej naprawy.');
            $q=$this->pdo->prepare("UPDATE vehicle_incidents SET status='resolved',resolution_note=:note,resolved_by=:uid,resolved_at=NOW(),updated_at=NOW() WHERE id=:id");$q->execute(['note'=>trim($note),'uid'=>(int)$user['id'],'id'=>$id]);return;
        }
        if($action==='reopen'){
            $q=$this->pdo->prepare("UPDATE vehicle_incidents SET status='open',resolution_note=NULL,resolved_by=NULL,resolved_at=NULL,updated_at=NOW() WHERE id=:id");$q->execute(['id'=>$id]);return;
        }
        throw new RuntimeException('Nieobsługiwana operacja.');
    }
}
