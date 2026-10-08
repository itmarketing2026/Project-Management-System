<?php
session_start();

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

if (!isset($_SESSION['user_id'])) {
  header("Location: /auth/login.php");
  exit();
}
?>