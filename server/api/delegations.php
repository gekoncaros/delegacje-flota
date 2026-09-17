<?php
require dirname(__DIR__).'/bootstrap.php';$u=requireUser();
if($_SERVER['REQUEST_METHOD']==='GET'){
 if(in_array($u['role'],['manager','accounting','super_admin'],true)){$s=$pdo->query('SELECT d.*,u.name employee FROM delegations d JOIN users u ON u.id=d.user_id ORDER BY d.id DESC LIMIT 500');}
 else{$s=$pdo->prepare('SELECT d.*,u.name employee FROM delegations d JOIN users u ON u.id=d.user_id WHERE d.user_id=? ORDER BY d.id DESC LIMIT 500');$s->execute([$u['id']]);}
 jsonOut(['items'=>$s->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $in=json_decode(file_get_contents('php://input'),true)?:[];$destination=trim((string)($in['destination']??''));$purpose=trim((string)($in['purpose']??''));$from=(string)($in['from']??'');$to=(string)($in['to']??'');$transport=trim((string)($in['transport']??''));
 if(!$destination||!$purpose||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from)jsonOut(['error'=>'validation'],422);
 $pdo->beginTransaction();try{$number='DEL/'.date('Y').'/'.bin2hex(random_bytes(4));$s=$pdo->prepare('INSERT INTO delegations(number,user_id,destination,purpose,date_from,date_to,transport) VALUES(?,?,?,?,?,?,?)');$s->execute([$number,$u['id'],$destination,$purpose,$from,$to,$transport]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$u['id'],'delegation.create','delegation',$id);$pdo->commit();jsonOut(['id'=>$id,'number'=>$number],201);}catch(Throwable $e){$pdo->rollBack();jsonOut(['error'=>'server_error'],500);}
}
jsonOut(['error'=>'method_not_allowed'],405);
