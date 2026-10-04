<?php
require_once __DIR__ . '/includes/auth.php';

logout_user();

// Start a fresh session purely to carry the goodbye message.
session_start();
flash('info', 'You have been logged out.');

redirect('login.php');
