<?php
declare(strict_types=1);
$config=require __DIR__.'/config.php';
session_name($config['app']['session_name']??'delegacje_session');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Lax']);
session_start();
$pdo=new PDO($config['db']['dsn'],$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function jsonOut(array $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function requireUser():array{if(empty($_SESSION['user']))jsonOut(['error'=>'unauthorized'],401);return $_SESSION['user'];}
function requireRole(array $roles):array{$u=requireUser();if(!in_array($u['role'],$roles,true))jsonOut(['error'=>'forbidden'],403);return $u;}
function audit(PDO $pdo,?int $uid,string $action,?string $type=null,?int $id=null,array $meta=[]):void{$s=$pdo->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,ip,meta) VALUES(?,?,?,?,?,?)');$s->execute([$uid,$action,$type,$id,$_SERVER['REMOTE_ADDR']??null,$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null]);}
