<?php
/**
 * AI Design Studio - User Logout
 */

define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

logout_user();

redirect('/');
