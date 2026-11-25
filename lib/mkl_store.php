<?php
/*
 *  MikhmonLive storage and tenant layer.
 *  Based on MIKHMON, Copyright (C) 2018 Laksamadi Guko.
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
 * ---------------------------------------------------------------------------
 *  One code folder, one data folder per customer.
 *
 *  Single mode (default, like classic Mikhmon):
 *      data lives in  <app>/data/
 *
 *  Tenant mode (include/site.php sets 'tenant_root' and 'base_domain'):
 *      https://shop1.example.com  ->  <tenant_root>/shop1/
 *      Keep tenant_root OUTSIDE the web root.
 *
 *  A data folder holds:
 *      config.json.php   admin login, preferences, routers (no PHP code runs:
 *                        the first line stops execution, the rest is JSON)
 *      tenant.json       subscription: status, expires, max_routers (tenant mode)
 *      tpl-*.tpl         voucher templates edited by the customer
 *      logo-*.png        uploaded logos
 *
 *  Nothing a customer can type is ever written into an executable PHP file.
 */

if (!defined('MKL_ROOT')) {

	define('MKL_ROOT', dirname(__DIR__));
	define('MKL_GUARD', "<?php http_response_code(404); exit; ?>\n");

	/* ------------------------------------------------------------------ */
	/*  Site configuration                                                 */
	/* ------------------------------------------------------------------ */

	function mkl_site($key = null)
	{
		static $site = null;
		if ($site === null) {
			$site = array(
				'tenant_root' => '',   // e.g. /www/mikhmon-data/tenants  ('' = single mode)
				'base_domain' => '',   // e.g. mikhmonlive.com
				'key_file' => '',      // secret key file, default: next to the data
				'portal_url' => '',    // where customers register / renew
				'private_nets' => array(), // private networks routers may be reached on, e.g. the VPN: array('10.99.0.0/16')
				'brand' => 'MikhmonLive',

				// ---- customer portal (portal/public) ----
				'portal_db' => '',            // SQLite file, default: <parent of tenant_root>/portal.sqlite
				'currency' => 'BDT',
				// Packages sold in the portal: routers included and price for one year.
				// 'support' is a free text line shown on the pricing card.
				'packages' => array(
					'starter' => array('routers' => 1, 'price' => 1200),
					'business' => array('routers' => 3, 'price' => 3000, 'popular' => true),
					'pro' => array('routers' => 10, 'price' => 7500),
				),
				'year_discount' => array(2 => 10, 3 => 15), // percent off when paying 2 or 3 years at once
				'trial_days' => 7,            // free trial after registration (0 = none)
				'trial_routers' => 1,
				'require_verified' => true,   // with e-mail on: the trial starts when the address is confirmed
				'manual_payment' => '',       // instructions shown for manual payment (bKash/Nagad number ...)
				'paystation_merchant' => '',  // PayStation merchantId ('' = online payment off)
				'paystation_password' => '',
				'paystation_sandbox' => false,
				'paystation_url' => '',       // override the API base URL (tests)
				'support_contact' => '',
				'google_site_verification' => '', // content of the Google Search Console meta tag
				'bing_site_verification' => '',   // content of the Bing Webmaster msvalidate.01 tag

				// ---- e-mail (portal) ----
				'smtp_host' => '',            // '' = no e-mail
				'smtp_port' => 0,             // 0 = 465 (ssl), 587 (tls) or 25
				'smtp_secure' => 'ssl',       // ssl | tls | ''
				'smtp_user' => '',
				'smtp_pass' => '',
				'mail_from' => '',            // e.g. noreply@mikhmonlive.com
				'mail_from_name' => '',       // default: brand
				'admin_email' => '',          // gets a notice for every manual payment

				// ---- VPN onboarding (tools/vpn/install.sh) ----
				'vpn_host' => '',             // public name of the VPN server, e.g. vpn.mikhmonlive.com ('' = VPN off)
				'vpn_server_ip' => '10.99.0.1',
				'vpn_wg_net' => '10.99.0.0/17',     // WireGuard clients (RouterOS v7)
				'vpn_l2tp_net' => '10.99.128.0/17', // L2TP/IPsec clients (RouterOS v6)
				'vpn_wg_port' => 51820,
				'vpn_state_dir' => '',        // default: <parent of tenant_root>/vpn
			);
			$file = MKL_ROOT . '/include/site.php';
			if (is_file($file)) {
				$custom = include($file);
				if (is_array($custom)) {
					$site = array_merge($site, $custom);
				}
			}
		}
		return $key === null ? $site : (isset($site[$key]) ? $site[$key] : null);
	}

	function mkl_tenant_mode()
	{
		return mkl_site('tenant_root') != '' && mkl_site('base_domain') != '';
	}

	/** A valid subdomain label: 3-32 chars, a-z 0-9 and inner dashes. */
	function mkl_valid_tenant($name)
	{
		return is_string($name) && preg_match('/^[a-z0-9](?:[a-z0-9-]{1,30})[a-z0-9]$/', $name) === 1;
	}

	/** Tenant name from the request host, '' when the host is not a tenant. */
	function mkl_tenant_from_host($host)
	{
		$host = strtolower(preg_replace('/:\d+$/', '', trim((string)$host)));
		$base = strtolower(mkl_site('base_domain'));
		$suffix = '.' . $base;
		if ($base == '' || substr($host, -strlen($suffix)) !== $suffix) {
			return '';
		}
		$name = substr($host, 0, -strlen($suffix));
		return mkl_valid_tenant($name) ? $name : '';
	}

	function mkl_tenant_dir($name)
	{
		return rtrim(mkl_site('tenant_root'), '/') . '/' . $name;
	}

	/** Stop the request with a small standalone page. */
	function mkl_stop($code, $title, $text)
	{
		if (!headers_sent()) {
			http_response_code($code);
			header('Content-Type: text/html; charset=utf-8');
		}
		$portal = mkl_site('portal_url');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . htmlspecialchars($title) . '</title></head>'
			. '<body style="font-family:Helvetica,Arial,sans-serif;background:#2f353a;color:#f0f3f5;text-align:center;padding:12% 16px;">'
			. '<h2>' . htmlspecialchars($title) . '</h2><p>' . htmlspecialchars($text) . '</p>'
			. ($portal != '' ? '<p><a style="color:#20a8d8" href="' . htmlspecialchars($portal) . '">' . htmlspecialchars($portal) . '</a></p>' : '')
			. '</body></html>';
		exit;
	}

	/**
	 * Resolve the data folder of this request. Defines MKL_TENANT and MKL_DATA.
	 * In tenant mode an unknown, suspended or expired account stops here.
	 */
	function mkl_boot()
	{
		if (defined('MKL_DATA')) {
			return;
		}
		if (!mkl_tenant_mode()) {
			define('MKL_TENANT', '');
			define('MKL_DATA', MKL_ROOT . '/data');
			if (!is_dir(MKL_DATA)) {
				@mkdir(MKL_DATA, 0750, true);
			}
			if (!is_file(MKL_DATA . '/index.php')) {
				@file_put_contents(MKL_DATA . '/index.php', MKL_GUARD);
				@file_put_contents(MKL_DATA . '/.htaccess', "Require all denied\nDeny from all\n");
			}
			return;
		}

		// customer subdomains are private, also their error pages: keep them out of search engines
		if (PHP_SAPI !== 'cli' && !headers_sent()) {
			header('X-Robots-Tag: noindex, nofollow');
		}
		$tenant = PHP_SAPI === 'cli' ? (string)getenv('MKL_TENANT') : mkl_tenant_from_host(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		if (!mkl_valid_tenant($tenant) || !is_dir(mkl_tenant_dir($tenant))) {
			mkl_stop(404, 'Account not found', 'There is no account at this address.');
		}
		define('MKL_TENANT', $tenant);
		define('MKL_DATA', mkl_tenant_dir($tenant));

		$t = mkl_tenant_info();
		if ($t['status'] != 'active') {
			mkl_stop(403, 'Account suspended', 'This account is not active. Please contact support.');
		}
		if ($t['plan'] == 'unverified') {
			mkl_stop(403, 'Email not confirmed', 'This account opens as soon as its owner confirms the email address. Please use the link in the welcome email.');
		}
		if ($t['expires'] != '' && $t['expires'] < date('Y-m-d')) {
			mkl_stop(403, 'Subscription expired', 'The subscription of this account ended on ' . $t['expires'] . '. Please renew to continue.');
		}
	}

	/** Subscription record of a tenant (current tenant when $dir is null). */
	function mkl_tenant_info($dir = null)
	{
		$info = array('status' => 'active', 'expires' => '', 'max_routers' => 0, 'plan' => '', 'created' => '');
		$dir = $dir === null ? (defined('MKL_DATA') ? MKL_DATA : '') : $dir;
		if ($dir != '' && is_file($dir . '/tenant.json')) {
			$j = json_decode((string)file_get_contents($dir . '/tenant.json'), true);
			if (is_array($j)) {
				$info = array_merge($info, $j);
			}
		}
		$info['max_routers'] = (int)$info['max_routers'];
		return $info;
	}

	/* ------------------------------------------------------------------ */
	/*  Provisioning (used by tools/tenant.php and by the customer portal) */
	/* ------------------------------------------------------------------ */

	/** Write the subscription record of a tenant. Unknown keys are kept. */
	function mkl_tenant_write($name, $values)
	{
		if (!mkl_valid_tenant($name) || !is_dir(mkl_tenant_dir($name))) {
			return false;
		}
		$info = array_merge(mkl_tenant_info(mkl_tenant_dir($name)), $values);
		$info['max_routers'] = max(0, (int)$info['max_routers']);
		$info['status'] = $info['status'] == 'suspended' ? 'suspended' : 'active';
		if ($info['expires'] != '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $info['expires'])) {
			return false;
		}
		$file = mkl_tenant_dir($name) . '/tenant.json';
		return file_put_contents($file, json_encode($info, JSON_PRETTY_PRINT), LOCK_EX) !== false;
	}

	/**
	 * Create the data folder of a new customer.
	 * @return string '' on success, otherwise the reason
	 */
	function mkl_tenant_create($name, $user, $pass, $values = array())
	{
		if (!mkl_tenant_mode()) {
			return 'Tenant mode is not configured (include/site.php).';
		}
		if (!mkl_valid_tenant($name)) {
			return 'Invalid name: use 3-32 characters a-z, 0-9 and dashes.';
		}
		if (in_array($name, array('www', 'mail', 'admin', 'api', 'app', 'portal', 'ftp', 'ns1', 'ns2', 'smtp', 'vpn', 'support', 'billing'), true)) {
			return 'This name is reserved.';
		}
		$dir = mkl_tenant_dir($name);
		if (is_dir($dir)) {
			return 'This name is already taken.';
		}
		if (trim((string)$user) == '' || strlen((string)$pass) < 6) {
			return 'A user name and a password of at least 6 characters are required.';
		}
		if (!@mkdir($dir, 0750, true)) {
			return 'Cannot create the data folder.';
		}
		$values = array_merge(array('status' => 'active', 'expires' => '', 'max_routers' => 1, 'plan' => '', 'created' => date('Y-m-d')), $values);
		if (!mkl_tenant_write($name, $values) || !mkl_admin_set($user, $pass, $dir)) {
			return 'Cannot write the account files.';
		}
		return '';
	}

	/** Names of all tenants on this server. */
	function mkl_tenant_list()
	{
		$out = array();
		foreach ((array)glob(rtrim(mkl_site('tenant_root'), '/') . '/*', GLOB_ONLYDIR) as $d) {
			if (mkl_valid_tenant(basename($d))) {
				$out[] = basename($d);
			}
		}
		sort($out);
		return $out;
	}

	/**
	 * Read the text of a classic Mikhmon include/config.php (it is parsed,
	 * never executed) and store its admin user and routers in $dir.
	 * @return int number of routers imported, -1 when nothing was recognised
	 */
	function mkl_import_legacy($text, $dir = null)
	{
		$routers = array();
		$admin = array();
		foreach (preg_split('/\R/', (string)$text) as $line) {
			if (!preg_match('/^\s*\$data\[\'([^\']+)\'\]\s*=\s*array\s*\((.*)\);\s*$/', $line, $m)) {
				continue;
			}
			preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[2], $v);
			$vals = $v[1];
			if ($m[1] == 'mikhmon') {
				foreach ($vals as $x) {
					if (strpos($x, 'mikhmon<|<') === 0) {
						$admin['user'] = substr($x, 10);
					} elseif (strpos($x, 'mikhmon>|>') === 0) {
						$admin['pass'] = decrypt(substr($x, 10));
					}
				}
				continue;
			}
			$n = $m[1];
			$r = array();
			$seps = array('!' => 'host', '@|@' => 'user', '#|#' => 'pass_plain', '%' => 'hotspotname', '^' => 'dnsname', '&' => 'currency',
				'*' => 'areload', '(' => 'iface', ')' => 'infolp', '=' => 'idleto', '@!@' => 'livereport');
			foreach ($vals as $x) {
				if (strpos($x, $n) !== 0) {
					continue;
				}
				$rest = substr($x, strlen($n));
				foreach (array('@|@', '#|#', '@!@', '!', '%', '^', '&', '*', '(', ')', '=') as $sep) {
					if (strpos($rest, $sep) === 0 && !isset($r[$seps[$sep]])) {
						$r[$seps[$sep]] = substr($rest, strlen($sep));
						break;
					}
				}
			}
			if (isset($r['pass_plain'])) {
				$r['pass_plain'] = decrypt($r['pass_plain']);
			}
			if ($r) {
				$routers[mkl_router_name($n)] = $r;
			}
		}
		if (!$routers && !$admin) {
			return -1;
		}
		mkl_store_update(function ($cfg) use ($routers, $admin) {
			if (isset($admin['user']) && $admin['user'] != '') {
				$cfg['admin']['user'] = $admin['user'];
			}
			if (isset($admin['pass']) && $admin['pass'] != '') {
				$cfg['admin']['hash'] = password_hash($admin['pass'], PASSWORD_DEFAULT);
			}
			foreach ($routers as $n => $r) {
				if ($n == '') {
					continue;
				}
				$cur = isset($cfg['routers'][$n]) ? $cfg['routers'][$n] : mkl_router_defaults();
				foreach (mkl_router_defaults() as $k => $def) {
					if ($k != 'pass' && isset($r[$k])) {
						$cur[$k] = (string)$r[$k];
					}
				}
				if (isset($r['pass_plain'])) {
					$cur['pass'] = mkl_seal($r['pass_plain']);
				}
				$cfg['routers'][$n] = $cur;
			}
			return $cfg;
		}, $dir);
		return count($routers);
	}

	/* ------------------------------------------------------------------ */
	/*  Secrets                                                            */
	/* ------------------------------------------------------------------ */

	/** 32 byte key used to encrypt router passwords at rest. */
	function mkl_key()
	{
		static $key = null;
		if ($key !== null) {
			return $key;
		}
		$file = mkl_site('key_file');
		if ($file == '') {
			$file = mkl_tenant_mode() ? rtrim(mkl_site('tenant_root'), '/') . '/.mkl-key.php' : MKL_DATA . '/key.php';
		}
		if (!is_file($file)) {
			@file_put_contents($file, MKL_GUARD . bin2hex(random_bytes(32)), LOCK_EX);
			@chmod($file, 0600);
		}
		$raw = (string)@file_get_contents($file);
		$hex = trim(substr($raw, strlen(MKL_GUARD)));
		if (strlen($hex) != 64 || !ctype_xdigit($hex)) {
			mkl_stop(500, 'Configuration error', 'The secret key file is missing or not writable.');
		}
		$key = hex2bin($hex);
		return $key;
	}

	/** Encrypt a secret for storage (AES-256-GCM). */
	function mkl_seal($plain)
	{
		$iv = random_bytes(12);
		$tag = '';
		$ct = openssl_encrypt((string)$plain, 'aes-256-gcm', mkl_key(), OPENSSL_RAW_DATA, $iv, $tag);
		return 'v1:' . base64_encode($iv . $tag . $ct);
	}

	/** Decrypt a stored secret, '' when it cannot be read. */
	function mkl_open($sealed)
	{
		$sealed = (string)$sealed;
		if (substr($sealed, 0, 3) != 'v1:') {
			return '';
		}
		$raw = base64_decode(substr($sealed, 3), true);
		if ($raw === false || strlen($raw) < 28) {
			return '';
		}
		$plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', mkl_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
		return $plain === false ? '' : $plain;
	}

	/* ------------------------------------------------------------------ */
	/*  Config store                                                       */
	/* ------------------------------------------------------------------ */

	function mkl_store_defaults()
	{
		return array(
			'version' => 1,
			// classic Mikhmon default login: mikhmon / 1234 (single mode only)
			'admin' => array('user' => 'mikhmon', 'hash' => ''),
			'prefs' => array('theme' => 'dark', 'themecolor' => '#3a4149', 'lang' => 'en', 'qrbt' => 'disable', 'genu' => ''),
			'routers' => array(),
		);
	}

	function mkl_router_defaults()
	{
		return array(
			'host' => '', 'user' => '', 'pass' => '', 'hotspotname' => '', 'dnsname' => '',
			'currency' => 'Rp', 'areload' => '10', 'iface' => '1', 'infolp' => '', 'idleto' => '10',
			'livereport' => 'disable',
		);
	}

	function mkl_store_file($dir = null)
	{
		return ($dir === null ? MKL_DATA : $dir) . '/config.json.php';
	}

	function mkl_store_read($dir = null)
	{
		$cfg = mkl_store_defaults();
		$file = mkl_store_file($dir);
		if (is_file($file)) {
			$raw = (string)file_get_contents($file);
			$j = json_decode(substr($raw, strlen(MKL_GUARD)), true);
			if (is_array($j)) {
				foreach (array('admin', 'prefs') as $k) {
					if (isset($j[$k]) && is_array($j[$k])) {
						$cfg[$k] = array_merge($cfg[$k], $j[$k]);
					}
				}
				if (isset($j['routers']) && is_array($j['routers'])) {
					foreach ($j['routers'] as $name => $r) {
						if (is_array($r)) {
							$cfg['routers'][(string)$name] = array_merge(mkl_router_defaults(), $r);
						}
					}
				}
			}
		}
		return $cfg;
	}

	/** Cached config of the current request. */
	function mkl_store($reload = false)
	{
		static $cfg = null;
		if ($cfg === null || $reload) {
			$cfg = mkl_store_read();
		}
		return $cfg;
	}

	function mkl_store_write($cfg, $dir = null)
	{
		$file = mkl_store_file($dir);
		$tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		$json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false || file_put_contents($tmp, MKL_GUARD . $json, LOCK_EX) === false) {
			@unlink($tmp);
			return false;
		}
		@chmod($tmp, 0640);
		return rename($tmp, $file);
	}

	/**
	 * Change the config safely: lock, read fresh, apply $fn, write atomically.
	 * $fn receives the config array and returns the changed array.
	 */
	function mkl_store_update($fn, $dir = null)
	{
		$d = $dir === null ? MKL_DATA : $dir;
		$lock = @fopen($d . '/.lock', 'c');
		if ($lock) {
			flock($lock, LOCK_EX);
		}
		$cfg = $fn(mkl_store_read($dir));
		$ok = is_array($cfg) ? mkl_store_write($cfg, $dir) : false;
		if ($lock) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		if ($dir === null) {
			mkl_store(true);
		}
		return $ok;
	}

	/* ------------------------------------------------------------------ */
	/*  Admin login                                                        */
	/* ------------------------------------------------------------------ */

	function mkl_admin_user()
	{
		$cfg = mkl_store();
		return $cfg['admin']['user'];
	}

	function mkl_admin_verify($user, $pass)
	{
		$cfg = mkl_store();
		$hash = $cfg['admin']['hash'];
		if ($hash == '') {
			// fresh single-mode install: classic default password
			$ok = !mkl_tenant_mode() && hash_equals('1234', (string)$pass);
		} else {
			$ok = password_verify((string)$pass, $hash);
		}
		return hash_equals((string)$cfg['admin']['user'], (string)$user) && $ok;
	}

	/** Change the admin login. An empty password keeps the current one. */
	function mkl_admin_set($user, $pass = '', $dir = null)
	{
		$user = trim((string)$user);
		return mkl_store_update(function ($cfg) use ($user, $pass) {
			if ($user != '') {
				$cfg['admin']['user'] = $user;
			}
			if ((string)$pass != '') {
				$cfg['admin']['hash'] = password_hash((string)$pass, PASSWORD_DEFAULT);
			}
			return $cfg;
		}, $dir);
	}

	/** Store an existing password hash (the portal reuses the customer's hash). */
	function mkl_admin_set_hash($user, $hash, $dir = null)
	{
		$user = trim((string)$user);
		return mkl_store_update(function ($cfg) use ($user, $hash) {
			if ($user != '') {
				$cfg['admin']['user'] = $user;
			}
			if ((string)$hash != '') {
				$cfg['admin']['hash'] = (string)$hash;
			}
			return $cfg;
		}, $dir);
	}

	/* ------------------------------------------------------------------ */
	/*  Single sign-on from the portal                                     */
	/* ------------------------------------------------------------------ */

	function mkl_b64u($bin)
	{
		return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
	}

	/** One-time login token for a tenant, valid for one minute. */
	function mkl_sso_make($tenant)
	{
		$body = mkl_b64u(json_encode(array('t' => (string)$tenant, 'e' => time() + 60, 'n' => bin2hex(random_bytes(12)))));
		return $body . '.' . mkl_b64u(hash_hmac('sha256', 'sso|' . $body, mkl_key(), true));
	}

	/** True when the token was issued for the current tenant and not used before. */
	function mkl_sso_check($token)
	{
		$parts = explode('.', (string)$token);
		if (count($parts) != 2 || !hash_equals(mkl_b64u(hash_hmac('sha256', 'sso|' . $parts[0], mkl_key(), true)), $parts[1])) {
			return false;
		}
		$d = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/')), true);
		if (!is_array($d) || !isset($d['t'], $d['e'], $d['n']) || $d['t'] !== MKL_TENANT || MKL_TENANT === '' || (int)$d['e'] < time()) {
			return false;
		}
		if (!preg_match('/^[0-9a-f]{24}$/', (string)$d['n'])) {
			return false;
		}
		foreach ((array)glob(MKL_DATA . '/.sso-*') as $old) {
			if (filemtime($old) < time() - 300) {
				@unlink($old);
			}
		}
		// "x" mode fails when the file exists: each token works once
		$f = @fopen(MKL_DATA . '/.sso-' . $d['n'], 'x');
		if (!$f) {
			return false;
		}
		fclose($f);
		return true;
	}

	/** Seconds the client still has to wait after too many failed logins. */
	function mkl_login_wait()
	{
		$f = MKL_DATA . '/.login-' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
		if (!is_file($f)) {
			return 0;
		}
		$d = explode(' ', (string)file_get_contents($f));
		$count = (int)$d[0];
		$last = isset($d[1]) ? (int)$d[1] : 0;
		if ($count >= 5 && time() - $last < 300) {
			return 300 - (time() - $last);
		}
		return 0;
	}

	function mkl_login_note($success)
	{
		$f = MKL_DATA . '/.login-' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
		if ($success) {
			@unlink($f);
			return;
		}
		$count = 0;
		if (is_file($f)) {
			$d = explode(' ', (string)file_get_contents($f));
			$count = (isset($d[1]) && time() - (int)$d[1] < 300) ? (int)$d[0] : 0;
		}
		@file_put_contents($f, ($count + 1) . ' ' . time(), LOCK_EX);
	}

	/* ------------------------------------------------------------------ */
	/*  Preferences                                                        */
	/* ------------------------------------------------------------------ */

	function mkl_pref($key)
	{
		$cfg = mkl_store();
		return isset($cfg['prefs'][$key]) ? $cfg['prefs'][$key] : '';
	}

	function mkl_pref_set($values)
	{
		return mkl_store_update(function ($cfg) use ($values) {
			foreach ($values as $k => $v) {
				$cfg['prefs'][$k] = (string)$v;
			}
			return $cfg;
		});
	}

	/* ------------------------------------------------------------------ */
	/*  Routers (Mikhmon calls them sessions)                              */
	/* ------------------------------------------------------------------ */

	/** Letters, digits, dash, dot and underscore only. */
	function mkl_router_name($name)
	{
		return substr(preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$name), 0, 40);
	}

	function mkl_routers()
	{
		$cfg = mkl_store();
		return $cfg['routers'];
	}

	/** Router settings with the password decrypted in 'pass_plain'. */
	function mkl_router($name)
	{
		$cfg = mkl_store();
		$name = (string)$name;
		$r = isset($cfg['routers'][$name]) ? $cfg['routers'][$name] : mkl_router_defaults();
		$r['exists'] = isset($cfg['routers'][$name]);
		$r['pass_plain'] = mkl_open($r['pass']);
		return $r;
	}

	/** How many more routers this account may add (large number = unlimited). */
	function mkl_router_slots()
	{
		$max = mkl_tenant_info();
		$max = $max['max_routers'];
		return $max > 0 ? max(0, $max - count(mkl_routers())) : 9999;
	}

	/**
	 * Create or update a router.
	 * @param string $name     current name
	 * @param array  $values   fields from mkl_router_defaults(); 'pass_plain' sets the password
	 * @param string $newname  rename target ('' = keep)
	 * @return string          'ok' | 'limit' | 'exists' | 'name' | 'error'
	 */
	function mkl_router_save($name, $values, $newname = '')
	{
		$name = mkl_router_name($name);
		$newname = $newname == '' ? $name : mkl_router_name($newname);
		if ($name == '' || $newname == '') {
			return 'name';
		}
		$result = 'ok';
		$max = mkl_tenant_info();
		$max = $max['max_routers'];
		mkl_store_update(function ($cfg) use ($name, $newname, $values, $max, &$result) {
			$isnew = !isset($cfg['routers'][$name]);
			if ($isnew && $max > 0 && count($cfg['routers']) >= $max) {
				$result = 'limit';
				return $cfg;
			}
			if ($newname != $name && isset($cfg['routers'][$newname])) {
				$result = 'exists';
				return $cfg;
			}
			$r = $isnew ? mkl_router_defaults() : $cfg['routers'][$name];
			foreach (mkl_router_defaults() as $k => $def) {
				if ($k != 'pass' && array_key_exists($k, $values)) {
					$r[$k] = (string)$values[$k];
				}
			}
			if (array_key_exists('pass_plain', $values)) {
				$r['pass'] = mkl_seal($values['pass_plain']);
			}
			if ($newname != $name) {
				// keep the list order when a router is renamed
				$routers = array();
				foreach ($cfg['routers'] as $k => $v) {
					$routers[$k == $name ? $newname : $k] = ($k == $name ? $r : $v);
				}
				if ($isnew) {
					$routers[$newname] = $r;
				}
				$cfg['routers'] = $routers;
			} else {
				$cfg['routers'][$name] = $r;
			}
			return $cfg;
		});
		if ($result == 'ok' && $newname != $name && is_file(mkl_logo_file($name))) {
			@rename(mkl_logo_file($name), mkl_logo_file($newname));
		}
		return $result;
	}

	function mkl_router_remove($name)
	{
		$name = (string)$name;
		mkl_store_update(function ($cfg) use ($name) {
			unset($cfg['routers'][$name]);
			return $cfg;
		});
		if (is_file(mkl_logo_file($name))) {
			@unlink(mkl_logo_file($name));
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Router address policy                                              */
	/* ------------------------------------------------------------------ */

	function mkl_ip_in_cidr($ip, $cidr)
	{
		$parts = explode('/', $cidr);
		$net = ip2long($parts[0]);
		$bits = isset($parts[1]) ? (int)$parts[1] : 32;
		$ipl = ip2long($ip);
		if ($net === false || $ipl === false || $bits < 0 || $bits > 32) {
			return false;
		}
		$mask = $bits == 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
		return ($ipl & $mask) === ($net & $mask);
	}

	/**
	 * Address the server may connect to for a router host, '' when refused.
	 *
	 * On a shared server a customer must not be able to reach the server
	 * itself or its internal network through the "router IP" field. Private
	 * ranges are only allowed when listed in 'private_nets' of include/site.php
	 * (for example the VPN network the routers dial in to).
	 * Single mode has no restriction (the owner runs it on the own network).
	 */
	function mkl_host_check($host)
	{
		$host = trim((string)$host);
		if (!mkl_tenant_mode()) {
			return $host;
		}
		$ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $host : gethostbyname($host);
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return '';
		}
		// VPN addresses that belong to this customer's own routers
		$t = mkl_tenant_info();
		if (isset($t['vpn_ips']) && is_array($t['vpn_ips']) && in_array($ip, $t['vpn_ips'], true)) {
			return $ip;
		}
		foreach ((array)mkl_site('private_nets') as $cidr) {
			if (mkl_ip_in_cidr($ip, $cidr)) {
				return $ip;
			}
		}
		foreach (array('0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
			'192.168.0.0/16', '224.0.0.0/3') as $cidr) {
			if (mkl_ip_in_cidr($ip, $cidr)) {
				return '';
			}
		}
		return $ip;
	}

	/* ------------------------------------------------------------------ */
	/*  Logos                                                              */
	/* ------------------------------------------------------------------ */

	function mkl_logo_file($router)
	{
		return MKL_DATA . '/logo-' . mkl_router_name($router) . '.png';
	}

	/** URL of the voucher logo of a router, relative to the app root. */
	function mkl_logo_url($router, $prefix = './')
	{
		$f = mkl_logo_file($router);
		if (is_file($f)) {
			return $prefix . 'img/logo.php?session=' . rawurlencode(mkl_router_name($router)) . '&t=' . filemtime($f);
		}
		return $prefix . 'img/logo.png';
	}
}
