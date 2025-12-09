<?php
session_start();

// 세션 변수 모두 제거
$_SESSION = array();

// 세션 완전히 파괴
session_destroy();

// 세션 파괴로 인해 auth.html로 강제 이동 
header("Location: auth.html");

exit;
?>