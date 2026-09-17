<?php
require dirname(__DIR__).'/bootstrap.php';$u=requireRole(['manager','super_admin']);if($_SERVER['REQUEST_METHOD']!=='POST')jsonOut(['error'=>'method_not_allowed'],405);
$in=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($in['id']??0);$decision=(string)($in['decision']??'');$note=trim((string)($in['note']??''));
if(!$id||!in_array($decision,['approved','rejected'],true))jsonOut(['error'=>'validation'],422);
$s=$pdo->prepare("UPDATE delegations SET status=?,manager_id=?,manager_note=? WHERE id=? AND status='pending'");$s->execute([$decision,$u['id'],$note,$id]);if(!$s->rowCount())jsonOut(['error'=>'conflict_or_not_found'],409);audit($pdo,(int)$u['id'],'delegation.'.$decision,'delegation',$id,['note'=>$note]);jsonOut(['ok'=>true]);
