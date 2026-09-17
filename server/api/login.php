<?php
require dirname(__DIR__).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')jsonOut(['error'=>'method_not_allowed'],405);
$in=json_decode(file_get_contents('php://input'),true)?:[];
$email=mb_strtolower(trim((string)($in['email']??'')));
$password=(string)($in['password']??'');
if(!$email||!$password)jsonOut(['error'=>'invalid_credentials'],422);
$s=$pdo->prepare('SELECT id,email,name,password_hash,role,active FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$u=$s->fetch();
if(!$u||!$u['active']||!password_verify($password,$u['password_hash'])){usleep(250000);jsonOut(['error'=>'invalid_credentials'],401);}
session_regenerate_id(true);unset($u['password_hash']);$_SESSION['user']=$u;audit($pdo,(int)$u['id'],'auth.login');jsonOut(['user'=>$u]);
