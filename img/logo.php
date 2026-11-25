<?php
// Serves the voucher logo of a router from the customer's data folder.
require_once(__DIR__ . '/../include/bootstrap.php');
error_reporting(0);

$mklfile = mkl_logo_file(isset($_GET['session']) ? $_GET['session'] : '');
if (!is_file($mklfile)) {
	$mklfile = __DIR__ . '/logo.png';
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($mklfile));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($mklfile);
