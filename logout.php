<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) log_activity('logout', 'auth', 'User logged out');
logout_user();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
