<?php
/*
 *  MikhmonLive customer portal - front controller.
 *  Document root of the portal website is this folder (portal/public).
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 */
error_reporting(0);
require_once(__DIR__ . '/../src/app.php');
require_once(__DIR__ . '/../src/views.php');

if (!mkl_tenant_mode()) {
	mkl_stop(500, 'Not configured', 'Copy include/site.sample.php to include/site.php and set tenant_root and base_domain.');
}

// the portal answers on the bare domain only, never on a customer subdomain
$mkphost = strtolower(preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
$mkpbase = strtolower(mkl_site('base_domain'));
$mkpportal = strtolower((string)parse_url((string)mkl_site('portal_url'), PHP_URL_HOST));
if (!in_array($mkphost, array($mkpbase, 'www.' . $mkpbase, $mkpportal), true)) {
	mkl_stop(404, 'Not found', 'This page does not exist.');
}

$mkpsecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
	|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
session_name('MKLPORTAL');
// Lax: the session must survive the return from the payment page
session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $mkpsecure, 'httponly' => true, 'samesite' => 'Lax'));
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

try {
	mkp_db();
} catch (Throwable $ex) {
	mkl_stop(500, 'Database error', 'The portal database cannot be opened. Check that the data folder is writable and pdo_sqlite is enabled.');
}

$p = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$u = mkp_user();

// PayStation server-to-server notice: no session, no CSRF token; it is only a
// hint to go and ask PayStation ourselves.
if ($p == 'pay-ipn') {
	$inv = isset($_REQUEST['invoice_number']) && is_string($_REQUEST['invoice_number']) ? $_REQUEST['invoice_number'] : '';
	$o = $inv != '' ? mkp_row('SELECT * FROM orders WHERE invoice = ?', array($inv)) : null;
	if ($o) {
		mkp_ps_verify($o);
	}
	header('Content-Type: application/json');
	echo '{"status":"success"}';
	exit;
}

if ($post) {
	mkp_csrf_check();
}

$public = array('', 'login', 'register', 'lang', 'pay-return', 'verify', 'forgot', 'reset');
if (!$u && !in_array($p, $public, true)) {
	mkp_redirect('login');
}
if ($u && in_array($p, array('', 'login', 'register', 'forgot', 'reset'), true)) {
	mkp_redirect('dashboard');
}
if (substr($p, 0, 5) == 'admin' && (!$u || !$u['is_admin'])) {
	mkp_redirect('dashboard');
}

switch ($p) {
	case '':
		echo v_home();
		break;

	case 'lang':
		$_SESSION['lang'] = (isset($_GET['l']) && $_GET['l'] == 'bn') ? 'bn' : 'en';
		if ($u) {
			// e-mails follow the language the customer uses
			mkp_q('UPDATE users SET lang = ? WHERE id = ?', array($_SESSION['lang'], $u['id']));
		}
		mkp_redirect($u ? 'dashboard' : '');
		break;

	case 'register':
		if ($post) {
			// at most 5 new accounts per hour from one address (only created accounts count)
			if (!mkp_throttle('reg|' . mkp_ip(), 5, 3600, false)) {
				echo v_register($_POST, t('err_wait'));
				break;
			}
			list($id, $err) = mkp_register(mkp_post('name', 80), mkp_post('email', 120), mkp_post('phone', 30), mkp_post('tenant', 40), isset($_POST['pass']) ? (string)$_POST['pass'] : '');
			if ($err != '') {
				echo v_register($_POST, $err);
				break;
			}
			mkp_throttle('reg|' . mkp_ip(), 5, 3600);
			mkp_mail_verify(mkp_row('SELECT * FROM users WHERE id = ?', array($id)));
			session_regenerate_id(true);
			$_SESSION['uid'] = $id;
			mkp_redirect('dashboard');
		}
		echo v_register();
		break;

	case 'login':
		if ($post) {
			$email = mkp_post('email', 120);
			// 10 failed logins in 10 minutes lock the address / the account for a while
			if (!mkp_throttle('login|' . mkp_ip(), 10, 600, false) || !mkp_throttle('login|' . strtolower($email), 10, 600, false)) {
				echo v_login($email, t('err_wait'));
				break;
			}
			$found = mkp_login($email, isset($_POST['pass']) ? (string)$_POST['pass'] : '');
			if (!$found) {
				mkp_throttle('login|' . mkp_ip(), 10, 600);
				mkp_throttle('login|' . strtolower($email), 10, 600);
				echo v_login($email, t('err_login'));
				break;
			}
			session_regenerate_id(true);
			$_SESSION['uid'] = $found['id'];
			$_SESSION['lang'] = $found['lang'] == 'bn' ? 'bn' : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en');
			mkp_redirect('dashboard');
		}
		echo v_login();
		break;

	case 'verify':
		// link from the welcome mail
		$v = mkp_token_user(isset($_GET['token']) ? $_GET['token'] : '', 'verify', true);
		if ($v) {
			$waiting = mkp_subscription($v)['state'] == 'unverified';
			mkp_mark_verified($v);
			mkp_flash(t($waiting && (int)mkl_site('trial_days') > 0 ? 'verify_ok_trial' : 'verify_ok', array((int)mkl_site('trial_days'))));
		} else {
			mkp_flash(t('link_bad'), 'bad');
		}
		mkp_redirect($u ? 'dashboard' : 'login');
		break;

	case 'verify-resend':
		if ($post && !$u['email_verified'] && mkp_throttle('verify|' . $u['id'], 3, 3600)) {
			mkp_flash(mkp_mail_verify($u) ? t('verify_sent', array($u['email'])) : t('mail_failed'), 'warn');
		}
		mkp_redirect('dashboard');
		break;

	case 'forgot':
		if (!mkp_mail_enabled()) {
			mkp_redirect('login');
		}
		if ($post) {
			$email = strtolower(mkp_post('email', 120));
			// same answer whether the address is known or not
			if (mkp_throttle('forgot|' . mkp_ip(), 5, 3600) && mkp_throttle('forgot|' . $email, 3, 3600)) {
				$f = mkp_row('SELECT * FROM users WHERE email = ?', array($email));
				if ($f) {
					mkp_mail_reset($f);
				}
			}
			mkp_flash(t('forgot_sent'));
			mkp_redirect('login');
		}
		echo v_forgot();
		break;

	case 'reset':
		$token = isset($_REQUEST['token']) && is_string($_REQUEST['token']) ? $_REQUEST['token'] : '';
		if (!mkp_token_user($token, 'reset', false)) {
			mkp_flash(t('link_bad'), 'bad');
			mkp_redirect('login');
		}
		if ($post) {
			$new = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
			if (strlen($new) < 8) {
				echo v_reset($token, t('err_password'));
				break;
			}
			$r = mkp_token_user($token, 'reset', true);
			if ($r) {
				mkp_set_password($r, $new);
				// whoever opens the link owns the mailbox
				mkp_mark_verified($r);
				mkp_flash(t('reset_ok'));
			}
			mkp_redirect('login');
		}
		echo v_reset($token);
		break;

	case 'logout':
		$_SESSION = array();
		session_destroy();
		header('Location: ./');
		exit;

	case 'dashboard':
		echo v_dashboard($u);
		break;

	case 'open':
		// one-time login link into the customer's own Mikhmon
		header('Location: ' . mkp_tenant_url($u['tenant']) . '/admin.php?id=sso&token=' . rawurlencode(mkl_sso_make($u['tenant'])));
		exit;

	case 'billing':
		echo v_billing($u);
		break;

	case 'order':
		if (!$post) {
			mkp_redirect('billing');
		}
		$method = mkp_post('method', 20);
		if ($method == 'manual') {
			if (mkl_site('manual_payment') == '' || mkp_post('manual_trx', 60) == '') {
				echo v_billing($u, t('err_trx'));
				break;
			}
			$note = mkp_post('manual_method', 30) . ' ' . mkp_post('manual_sender', 30);
			list($oid, $err) = mkp_order_create($u, mkp_post('routers', 5), mkp_post('years', 2), 'manual', trim($note), mkp_post('manual_trx', 60));
			if ($err != '') {
				echo v_billing($u, $err);
				break;
			}
			mkp_mail_admin_order(mkp_row('SELECT * FROM orders WHERE id = ?', array($oid)), $u);
			mkp_flash(t('manual_wait'));
			mkp_redirect('billing');
		}
		if ($method != 'paystation' || !mkp_ps_enabled()) {
			mkp_redirect('billing');
		}
		list($oid, $err) = mkp_order_create($u, mkp_post('routers', 5), mkp_post('years', 2), 'paystation');
		if ($err != '') {
			echo v_billing($u, $err);
			break;
		}
		list($url, $err) = mkp_ps_start(mkp_row('SELECT * FROM orders WHERE id = ?', array($oid)), $u);
		if ($err != '') {
			mkp_order_set($oid, 'cancelled');
			echo v_billing($u, t('err_pay_start', array($err)));
			break;
		}
		header('Location: ' . $url);
		exit;

	case 'pay-return':
		// the browser returns from PayStation; what it says is not trusted
		$inv = isset($_GET['invoice']) && is_string($_GET['invoice']) ? $_GET['invoice'] : '';
		$o = $inv != '' ? mkp_row('SELECT * FROM orders WHERE invoice = ?', array($inv)) : null;
		$res = $o ? mkp_ps_verify($o) : 'failed';
		mkp_flash(t('pay_' . ($res == 'paid' ? 'ok' : $res)), $res == 'paid' ? 'ok' : ($res == 'failed' ? 'bad' : 'warn'));
		mkp_redirect($u ? 'billing' : 'login');
		break;

	case 'pay-check':
		$o = mkp_row('SELECT * FROM orders WHERE id = ? AND user_id = ?', array((int)mkp_post('id', 12), $u['id']));
		if ($post && $o) {
			$res = mkp_ps_verify($o);
			mkp_flash(t('pay_' . ($res == 'paid' ? 'ok' : $res)), $res == 'paid' ? 'ok' : ($res == 'failed' ? 'bad' : 'warn'));
		}
		mkp_redirect('billing');
		break;

	case 'order-cancel':
		$o = mkp_row('SELECT * FROM orders WHERE id = ? AND user_id = ?', array((int)mkp_post('id', 12), $u['id']));
		if ($post && $o) {
			// an online order may have been paid in the meantime: ask first
			if ($o['method'] != 'paystation' || !mkp_ps_enabled() || mkp_ps_verify($o) != 'paid') {
				mkp_order_set($o['id'], 'cancelled');
			}
		}
		mkp_redirect('billing');
		break;

	case 'vpn':
		$show = isset($_GET['show']) ? mkp_row('SELECT * FROM vpn_peers WHERE id = ? AND user_id = ?', array((int)$_GET['show'], $u['id'])) : null;
		echo v_vpn($u, $show);
		break;

	case 'vpn-add':
		if (!$post) {
			mkp_redirect('vpn');
		}
		$sub = mkp_subscription($u);
		if ($sub['state'] == 'unverified') {
			mkp_flash(t('verify_first'), 'warn');
			mkp_redirect('dashboard');
		}
		if (!in_array($sub['state'], array('trial', 'active'), true)) {
			mkp_redirect('billing');
		}
		list($pid, $err) = mkp_vpn_create($u, mkp_post('name', 40), mkp_post('type', 10));
		if ($err != '') {
			echo v_vpn($u, null, $err);
			break;
		}
		mkp_flash(t('vpn_created'));
		mkp_redirect('vpn', array('show' => $pid));
		break;

	case 'vpn-del':
		if ($post && mkp_vpn_delete($u, (int)mkp_post('id', 12))) {
			mkp_flash(t('vpn_deleted'));
		}
		mkp_redirect('vpn');
		break;

	case 'account':
		if ($post && mkp_post('what', 20) == 'profile') {
			if (mkp_post('name', 80) != '') {
				mkp_q('UPDATE users SET name = ?, phone = ? WHERE id = ?', array(mkp_post('name', 80), mkp_post('phone', 30), $u['id']));
				mkp_flash(t('saved'));
			}
			mkp_redirect('account');
		}
		if ($post && mkp_post('what', 20) == 'password') {
			$new = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
			if (!password_verify(isset($_POST['current']) ? (string)$_POST['current'] : '', $u['pass_hash'])) {
				echo v_account($u, t('err_current_password'));
				break;
			}
			if (strlen($new) < 8) {
				echo v_account($u, t('err_password'));
				break;
			}
			mkp_set_password($u, $new);
			mkp_flash(t('password_changed'));
			mkp_redirect('account');
		}
		echo v_account($u);
		break;

	case 'admin':
		echo v_admin();
		break;

	case 'admin-order':
		$o = mkp_row('SELECT * FROM orders WHERE id = ?', array((int)mkp_post('id', 12)));
		if ($post && $o && $o['status'] == 'pending') {
			if (mkp_post('do', 10) == 'approve') {
				mkp_order_paid($o['id']);
				mkp_flash('Order ' . $o['invoice'] . ' approved.');
			} elseif (mkp_post('do', 10) == 'reject') {
				mkp_order_set($o['id'], 'rejected');
				mkp_mail_order($o, 'rejected');
				mkp_flash('Order ' . $o['invoice'] . ' rejected.', 'warn');
			}
		}
		mkp_redirect('admin');
		break;

	case 'admin-user':
		$c = mkp_row('SELECT * FROM users WHERE id = ?', array(isset($_GET['id']) ? (int)$_GET['id'] : 0));
		if (!$c) {
			mkp_redirect('admin');
		}
		if ($post && mkp_post('what', 10) == 'sub') {
			$exp = mkp_post('expires', 10);
			$ok = mkl_tenant_write($c['tenant'], array(
				'max_routers' => (int)mkp_post('max_routers', 6),
				'expires' => $exp,
				'status' => mkp_post('status', 12) == 'suspended' ? 'suspended' : 'active',
				'plan' => 'yearly',
			));
			mkp_vpn_write_state();
			mkp_flash($ok ? 'Saved.' : 'Invalid date.', $ok ? 'ok' : 'bad');
			mkp_redirect('admin-user', array('id' => $c['id']));
		}
		if ($post && mkp_post('what', 10) == 'verify') {
			mkp_mark_verified($c);
			mkp_flash('Marked as confirmed.');
			mkp_redirect('admin-user', array('id' => $c['id']));
		}
		if ($post && mkp_post('what', 10) == 'pass') {
			$new = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
			if (strlen($new) >= 8) {
				mkp_set_password($c, $new);
				mkp_flash('Password set.');
			} else {
				mkp_flash('The password needs at least 8 characters.', 'bad');
			}
			mkp_redirect('admin-user', array('id' => $c['id']));
		}
		echo v_admin_user($c);
		break;

	default:
		mkp_redirect($u ? 'dashboard' : '');
}
