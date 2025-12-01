<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'quiz_app1');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Theme handling
if (isset($_POST['toggle_theme'])) {
    $_SESSION['theme'] = $_SESSION['theme'] === 'dark' ? 'light' : 'dark';
}

if (!isset($_SESSION['theme']) && isset($_COOKIE['theme'])) {
    $_SESSION['theme'] = $_COOKIE['theme'];
} elseif (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}

setcookie('theme', $_SESSION['theme'], time() + (86400 * 30), "/");
?>