<?php
/*
 *  MikhmonLive customer portal - core (database, accounts, orders).
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The portal is a separate website (document root: portal/public) for the
 *  bare domain. Customers register here, choose a subdomain, pay for a yearly
 *  subscription and get the VPN script for their routers. It provisions the
 *  per-customer data folders through lib/mkl_store.php.
 */

if (!defined('MKP_ROOT')) {

	define('MKP_ROOT', dirname(__DIR__));
	require_once(MKP_ROOT . '/../lib/mkl_store.php');
	require_once(__DIR__ . '/lang.php');
	require_once(__DIR__ . '/vpn.php');
	require_once(__DIR__ . '/paystation.php');
	require_once(__DIR__ . '/mail.php');

	/* ------------------------------------------------------------------ */
	/*  Database (SQLite)                                                  */
	/* ------------------------------------------------------------------ */

	function mkp_data_root()
	{
		return dirname(rtrim(mkl_site('tenant_root'), '/'));
	}

	function mkp_db()
	{
		static $db = null;
		if ($db !== null) {
			return $db;
		}
		$file = mkl_site('portal_db') != '' ? mkl_site('portal_db') : mkp_data_root() . '/portal.sqlite';
		$db = new PDO('sqlite:' . $file);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$db->exec('PRAGMA journal_mode = WAL');
		$db->exec('PRAGMA busy_timeout = 5000');
		$db->exec('PRAGMA foreign_keys = ON');
		$db->exec('CREATE TABLE IF NOT EXISTS users (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			email TEXT NOT NULL UNIQUE,
			name TEXT NOT NULL,
			phone TEXT NOT NULL DEFAULT "",
			pass_hash TEXT NOT NULL,
			tenant TEXT NOT NULL UNIQUE,
			is_admin INTEGER NOT NULL DEFAULT 0,
			created_at TEXT NOT NULL)');
		$db->exec('CREATE TABLE IF NOT EXISTS orders (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id),
			invoice TEXT NOT NULL UNIQUE,
			routers INTEGER NOT NULL,
			years INTEGER NOT NULL,
			amount INTEGER NOT NULL,
			method TEXT NOT NULL,
			status TEXT NOT NULL DEFAULT "pending",
			trx_id TEXT NOT NULL DEFAULT "",
			note TEXT NOT NULL DEFAULT "",
			created_at TEXT NOT NULL,
			paid_at TEXT NOT NULL DEFAULT "")');
		$db->exec('CREATE TABLE IF NOT EXISTS vpn_peers (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id),
			tenant TEXT NOT NULL,
			name TEXT NOT NULL,
			type TEXT NOT NULL,
			ip TEXT NOT NULL UNIQUE,
			wg_pub TEXT NOT NULL DEFAULT "",
			secret TEXT NOT NULL DEFAULT "",
			ppp_user TEXT NOT NULL DEFAULT "",
			api_user TEXT NOT NULL DEFAULT "",
			api_pass TEXT NOT NULL DEFAULT "",
			created_at TEXT NOT NULL)');
		$db->exec('CREATE TABLE IF NOT EXISTS throttle (
			k TEXT PRIMARY KEY, n INTEGER NOT NULL, ts INTEGER NOT NULL)');
		$db->exec('CREATE TABLE IF NOT EXISTS tokens (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			user_id INTEGER NOT NULL REFERENCES users(id),
			kind TEXT NOT NULL, hash TEXT NOT NULL UNIQUE, expires INTEGER NOT NULL)');
		$db->exec('CREATE TABLE IF NOT EXISTS mail_log (
			user_id INTEGER NOT NULL, kind TEXT NOT NULL, ref TEXT NOT NULL, sent_at TEXT NOT NULL,
			PRIMARY KEY (user_id, kind, ref))');
		// columns added after the first release
		$cols = array();
		foreach ($db->query('PRAGMA table_info(users)') as $c) {
			$cols[$c['name']] = true;
		}
		if (!isset($cols['email_verified'])) {
			$db->exec('ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0');
		}
		if (!isset($cols['lang'])) {
			$db->exec('ALTER TABLE users ADD COLUMN lang TEXT NOT NULL DEFAULT "en"');
		}
		return $db;
	}

	function mkp_q($sql, $args = array())
	{
		$st = mkp_db()->prepare($sql);
		$st->execute($args);
		return $st;
	}

	function mkp_row($sql, $args = array())
	{
		$r = mkp_q($sql, $args)->fetch();
		return $r === false ? null : $r;
	}

	function mkp_now()
	{
		return date('Y-m-d H:i:s');
	}

	/* ------------------------------------------------------------------ */
	/*  Request helpers                                                    */
	/* ------------------------------------------------------------------ */

	function e($s)
	{
		return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
	}

	function mkp_post($key, $max = 200)
	{
		$v = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
		return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
	}

	function mkp_url($page = '', $args = array())
	{
		$args = $page == '' ? $args : array_merge(array('p' => $page), $args);
		return './' . ($args ? '?' . http_build_query($args) : '');
	}

	/** Absolute URL of the portal (for payment callbacks). */
	function mkp_abs($page, $args = array())
	{
		$base = rtrim((string)mkl_site('portal_url'), '/');
		return $base . '/?' . http_build_query(array_merge(array('p' => $page), $args));
	}

	function mkp_redirect($page = '', $args = array())
	{
		header('Location: ' . mkp_url($page, $args));
		exit;
	}

	function mkp_flash($msg = null, $type = 'ok')
	{
		if ($msg !== null) {
			$_SESSION['flash'] = array($type, $msg);
			return null;
		}
		$f = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
		unset($_SESSION['flash']);
		return $f;
	}

	function mkp_csrf()
	{
		if (empty($_SESSION['csrf'])) {
			$_SESSION['csrf'] = bin2hex(random_bytes(16));
		}
		return $_SESSION['csrf'];
	}

	function mkp_csrf_field()
	{
		return '<input type="hidden" name="csrf" value="' . e(mkp_csrf()) . '">';
	}

	/** Every POST must carry the session's token. */
	function mkp_csrf_check()
	{
		$t = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
		if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
			http_response_code(400);
			exit('Bad request (form expired). Please go back and reload the page.');
		}
	}

	/** Simple counter: true while fewer than $max hits happened in $window seconds ($count = false only looks). */
	function mkp_throttle($key, $max, $window, $count = true)
	{
		$k = hash('sha256', $key);
		$r = mkp_row('SELECT n, ts FROM throttle WHERE k = ?', array($k));
		$now = time();
		if (!$r || $now - (int)$r['ts'] > $window) {
			if ($count) {
				mkp_q('REPLACE INTO throttle (k, n, ts) VALUES (?, 1, ?)', array($k, $now));
			}
			return true;
		}
		if ((int)$r['n'] >= $max) {
			return false;
		}
		if ($count) {
			mkp_q('UPDATE throttle SET n = n + 1 WHERE k = ?', array($k));
		}
		return true;
	}

	function mkp_ip()
	{
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	/* ------------------------------------------------------------------ */
	/*  Accounts                                                           */
	/* ------------------------------------------------------------------ */

	function mkp_user()
	{
		static $u = false;
		if ($u === false) {
			$u = empty($_SESSION['uid']) ? null : mkp_row('SELECT * FROM users WHERE id = ?', array($_SESSION['uid']));
		}
		return $u;
	}

	function mkp_tenant_url($tenant)
	{
		return 'https://' . $tenant . '.' . mkl_site('base_domain');
	}

	/**
	 * Register a customer and create the Mikhmon account behind the subdomain.
	 * @return array array(user id, '') or array(0, error text)
	 */
	function mkp_register($name, $email, $phone, $tenant, $pass)
	{
		$email = strtolower(trim((string)$email));
		$tenant = strtolower(trim((string)$tenant));
		if (trim((string)$name) == '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return array(0, t('err_name_email'));
		}
		if (strlen((string)$pass) < 8) {
			return array(0, t('err_password'));
		}
		if (!mkl_valid_tenant($tenant)) {
			return array(0, t('err_subdomain'));
		}
		if (mkp_row('SELECT id FROM users WHERE email = ?', array($email))) {
			return array(0, t('err_email_taken'));
		}
		if (mkp_row('SELECT id FROM users WHERE tenant = ?', array($tenant)) || is_dir(mkl_tenant_dir($tenant))) {
			return array(0, t('err_subdomain_taken'));
		}
		$hash = password_hash((string)$pass, PASSWORD_DEFAULT);
		$trial = (int)mkl_site('trial_days');
		$err = mkl_tenant_create($tenant, $tenant, (string)$pass, array(
			'max_routers' => max(1, (int)mkl_site('trial_routers')),
			// without a trial the account exists but stays closed until the first payment
			'expires' => $trial > 0 ? date('Y-m-d', strtotime("+$trial day")) : date('Y-m-d', strtotime('-1 day')),
			'plan' => $trial > 0 ? 'trial' : 'unpaid',
		));
		if ($err != '') {
			return array(0, $err);
		}
		mkl_admin_set_hash($tenant, $hash, mkl_tenant_dir($tenant));
		mkp_q('INSERT INTO users (email, name, phone, pass_hash, tenant, lang, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
			array($email, trim((string)$name), trim((string)$phone), $hash, $tenant, mkp_lang(), mkp_now()));
		return array((int)mkp_db()->lastInsertId(), '');
	}

	function mkp_login($email, $pass)
	{
		$u = mkp_row('SELECT * FROM users WHERE email = ?', array(strtolower(trim((string)$email))));
		return ($u && password_verify((string)$pass, $u['pass_hash'])) ? $u : null;
	}

	/** Change the portal password; the Mikhmon login of the subdomain follows. */
	function mkp_set_password($user, $pass)
	{
		$hash = password_hash((string)$pass, PASSWORD_DEFAULT);
		mkp_q('UPDATE users SET pass_hash = ? WHERE id = ?', array($hash, $user['id']));
		// links from older reset mails stop working
		mkp_q('DELETE FROM tokens WHERE user_id = ? AND kind = "reset"', array($user['id']));
		if (is_dir(mkl_tenant_dir($user['tenant']))) {
			mkl_admin_set_hash('', $hash, mkl_tenant_dir($user['tenant']));
		}
	}

	/** Subscription of a customer, with a computed 'state'. */
	function mkp_subscription($user)
	{
		$t = mkl_tenant_info(mkl_tenant_dir($user['tenant']));
		$cfg = mkl_store_read(mkl_tenant_dir($user['tenant']));
		$t['routers_used'] = count($cfg['routers']);
		$t['days_left'] = $t['expires'] == '' ? 99999 : (int)floor((strtotime($t['expires'] . ' 23:59:59') - time()) / 86400);
		if ($t['status'] != 'active') {
			$t['state'] = 'suspended';
		} elseif ($t['expires'] != '' && $t['expires'] < date('Y-m-d')) {
			$t['state'] = 'expired';
		} else {
			$t['state'] = $t['plan'] == 'trial' ? 'trial' : 'active';
		}
		return $t;
	}

	/* ------------------------------------------------------------------ */
	/*  Orders                                                             */
	/* ------------------------------------------------------------------ */

	function mkp_price($routers, $years)
	{
		return max(1, (int)$routers) * max(1, (int)$years) * (int)mkl_site('price_per_router');
	}

	function mkp_money($amount)
	{
		return number_format((int)$amount) . ' ' . mkl_site('currency');
	}

	/** @return array array(order id, '') or array(0, error text) */
	function mkp_order_create($user, $routers, $years, $method, $note = '', $trx = '')
	{
		$routers = (int)$routers;
		$years = (int)$years;
		if ($routers < 1 || $routers > (int)mkl_site('max_routers_order') || $years < 1 || $years > 5) {
			return array(0, t('err_order'));
		}
		if (!in_array($method, array('paystation', 'manual', 'admin'), true)) {
			return array(0, t('err_order'));
		}
		// a customer cannot shrink the plan below the VPN connections in use
		$peers = (int)mkp_row('SELECT COUNT(*) c FROM vpn_peers WHERE user_id = ?', array($user['id']))['c'];
		if ($routers < $peers) {
			return array(0, t('err_order_peers', array($peers)));
		}
		$invoice = 'ML' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
		mkp_q('INSERT INTO orders (user_id, invoice, routers, years, amount, method, note, trx_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array($user['id'], $invoice, $routers, $years, mkp_price($routers, $years), $method, (string)$note, (string)$trx, mkp_now()));
		return array((int)mkp_db()->lastInsertId(), '');
	}

	/**
	 * Mark an order as paid and extend the subscription. Safe to call twice:
	 * only the call that moves the order from "pending" to "paid" applies it.
	 */
	function mkp_order_paid($orderId, $trx = '')
	{
		$db = mkp_db();
		$st = $db->prepare('UPDATE orders SET status = "paid", paid_at = ?, trx_id = CASE WHEN ? != "" THEN ? ELSE trx_id END WHERE id = ? AND status = "pending"');
		$st->execute(array(mkp_now(), (string)$trx, (string)$trx, (int)$orderId));
		if ($st->rowCount() != 1) {
			return false;
		}
		$o = mkp_row('SELECT o.*, u.tenant FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', array((int)$orderId));
		$t = mkl_tenant_info(mkl_tenant_dir($o['tenant']));
		// a paid period starts today, or when the running paid period ends
		$from = ($t['plan'] != 'trial' && $t['plan'] != 'unpaid' && $t['expires'] > date('Y-m-d')) ? $t['expires'] : date('Y-m-d');
		mkl_tenant_write($o['tenant'], array(
			'expires' => date('Y-m-d', strtotime($from . ' +' . (int)$o['years'] . ' year')),
			'max_routers' => (int)$o['routers'],
			'plan' => 'yearly',
			'status' => 'active',
		));
		mkp_vpn_write_state(); // tunnels of a renewed account come back
		if ($o['method'] != 'admin') {
			mkp_mail_order($o, 'paid');
		}
		return true;
	}

	function mkp_order_set($orderId, $status, $from = 'pending')
	{
		$st = mkp_db()->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
		$st->execute(array($status, (int)$orderId, $from));
		return $st->rowCount() == 1;
	}
}
