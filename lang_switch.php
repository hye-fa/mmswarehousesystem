<?php
// lang_switch.php
// Server-side language switcher — saves lang preference to session
// Called via AJAX from translations.js

if (session_status() === PHP_SESSION_NONE) session_start();

$lang = $_GET['lang'] ?? 'en';
$allowed = ['en', 'ms'];

if (in_array($lang, $allowed)) {
    $_SESSION['mms_lang'] = $lang;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'lang' => $_SESSION['mms_lang'] ?? 'en']);
