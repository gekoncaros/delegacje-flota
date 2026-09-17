<?php
require dirname(__DIR__).'/bootstrap.php';
$u=$_SESSION['user']??null;if($u)audit($pdo,(int)$u['id'],'auth.logout');$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();jsonOut(['ok'=>true]);
