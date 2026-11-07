<?php
define('APP_INIT', true);
require_once __DIR__ . '/includes/bootstrap.php';
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
redirect('templates' . $query, 301);

