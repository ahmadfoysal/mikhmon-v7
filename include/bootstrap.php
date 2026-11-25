<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
// Loaded first by every entry page: finds the data folder of this
// customer (tenant) and starts an isolated session.

require_once(__DIR__ . '/../lib/mkl_store.php');
require_once(__DIR__ . '/../lib/routeros_api.class.php');

mkl_boot();

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
	$mklsecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
	// One cookie name per customer, bound to this host only. SameSite=Strict
	// keeps other sites from triggering actions with the admin's session.
	session_name('MKL' . substr(md5(MKL_TENANT . '|' . MKL_ROOT), 0, 10));
	session_set_cookie_params(array(
		'lifetime' => 0,
		'path' => '/',
		'secure' => $mklsecure,
		'httponly' => true,
		'samesite' => 'Strict',
	));
	session_start();
}

// a session never crosses customers
if (isset($_SESSION['mkl_tenant']) && $_SESSION['mkl_tenant'] !== MKL_TENANT) {
	$_SESSION = array();
}
$_SESSION['mkl_tenant'] = MKL_TENANT;
