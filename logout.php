<?php
/**
 * AI Design Studio - User Logout
 */

define('APP_INIT', true);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

send_security_headers();
init_secure_session();

logout_user();

header('Location: index.php');
exit;

