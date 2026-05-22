<?php
/**
 * Logout handler — Papelería Admin System
 */
require_once __DIR__ . '/includes/auth.php';
Auth::logout();
header('Location: index.php');
exit;
