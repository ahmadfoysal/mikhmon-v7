<?php
/*
 *  MikhmonLive portal - e-mail.
 *
 *  Mail is sent through an SMTP account set in include/site.php
 *  (smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, mail_from).
 *  Without smtp_host nothing is sent and the portal works as before:
 *  no verification, a lost password is reset by the admin.
 *
 *  Messages are plain text, in the customer's language (English or Bangla).
 *  A failed send never breaks a page; it is written to mail-errors.log in
 *  the data folder.
 */

if (!function_exists('mkp_mail_enabled')) {

	function mkp_mail_enabled()
	{
		return mkl_site('smtp_host') != '' && mkl_site('mail_from') != '';
	}

	/** One line header value: no line breaks, no control characters. */
	function mkp_mail_clean($s)
	{
		return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$s));
	}

	function mkp_mail_encode($s)
	{
		$s = mkp_mail_clean($s);
		return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
	}

	function mkp_mail_error($msg)
	{
		@file_put_contents(mkp_data_root() . '/mail-errors.log', date('Y-m-d H:i:s') . ' ' . mkp_mail_clean($msg) . "\n", FILE_APPEND | LOCK_EX);
		return false;
	}

	/** Read one SMTP reply (possibly several lines). Returns array(code, text). */
	function mkp_smtp_read($fp)
	{
		$text = '';
		while (($line = fgets($fp, 1024)) !== false) {
			$text .= $line;
			if (strlen($line) < 4 || $line[3] != '-') {
				break;
			}
		}
		return array((int)substr($text, 0, 3), trim($text));
	}

	function mkp_smtp_cmd($fp, $cmd, $expect)
	{
		if ($cmd !== null) {
			fwrite($fp, $cmd . "\r\n");
		}
		$r = mkp_smtp_read($fp);
		if (!in_array($r[0], (array)$expect, true)) {
			throw new RuntimeException('SMTP: expected ' . implode('/', (array)$expect) . ' got: ' . $r[1]);
		}
		return $r;
	}

	/**
	 * Send one plain text message.
	 * @return bool true when the server accepted it
	 */
	function mkp_mail_send($to, $subject, $body)
	{
		if (!mkp_mail_enabled()) {
			return false;
		}
		$to = mkp_mail_clean($to);
		$from = mkp_mail_clean(mkl_site('mail_from'));
		if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
			return mkp_mail_error("bad address: to=$to from=$from");
		}
		$host = mkl_site('smtp_host');
		$secure = strtolower((string)mkl_site('smtp_secure')); // ssl | tls | ''
		$port = (int)mkl_site('smtp_port');
		if ($port == 0) {
			$port = $secure == 'ssl' ? 465 : ($secure == 'tls' ? 587 : 25);
		}
		$name = mkl_site('mail_from_name') != '' ? mkl_site('mail_from_name') : mkl_site('brand');
		$domain = substr(strrchr($from, '@'), 1);

		$headers = array(
			'Date: ' . date('r'),
			'From: ' . mkp_mail_encode($name) . ' <' . $from . '>',
			'To: <' . $to . '>',
			'Subject: ' . mkp_mail_encode($subject),
			'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
			'MIME-Version: 1.0',
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: base64',
			'Auto-Submitted: auto-generated',
		);
		$data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode(str_replace(array("\r\n", "\r"), "\n", (string)$body)), 76, "\r\n");

		$fp = @stream_socket_client(($secure == 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
		if (!$fp) {
			return mkp_mail_error("connect $host:$port failed: $errstr");
		}
		stream_set_timeout($fp, 20);
		try {
			$me = mkl_site('base_domain') != '' ? mkl_site('base_domain') : 'localhost';
			mkp_smtp_cmd($fp, null, 220);
			$ehlo = mkp_smtp_cmd($fp, 'EHLO ' . $me, 250);
			if ($secure == 'tls') {
				mkp_smtp_cmd($fp, 'STARTTLS', 220);
				if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					throw new RuntimeException('SMTP: TLS could not be started');
				}
				$ehlo = mkp_smtp_cmd($fp, 'EHLO ' . $me, 250);
			}
			if (mkl_site('smtp_user') != '') {
				if ($secure == '' && !in_array($host, array('127.0.0.1', 'localhost'), true)) {
					throw new RuntimeException('SMTP: refusing to send the password without encryption (set smtp_secure)');
				}
				mkp_smtp_cmd($fp, 'AUTH LOGIN', 334);
				mkp_smtp_cmd($fp, base64_encode(mkl_site('smtp_user')), 334);
				mkp_smtp_cmd($fp, base64_encode(mkl_site('smtp_pass')), 235);
			}
			mkp_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', 250);
			mkp_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', array(250, 251));
			mkp_smtp_cmd($fp, 'DATA', 354);
			mkp_smtp_cmd($fp, $data . "\r\n.", 250);
			fwrite($fp, "QUIT\r\n");
			fclose($fp);
			return true;
		} catch (Throwable $ex) {
			@fclose($fp);
			return mkp_mail_error("to=$to " . $ex->getMessage());
		}
	}

	/* ------------------------------------------------------------------ */
	/*  One-time links (verification, password reset)                      */
	/* ------------------------------------------------------------------ */

	/** New token for a user; older unused tokens of the same kind stop working. */
	function mkp_token_make($userId, $kind, $ttl)
	{
		$token = bin2hex(random_bytes(24));
		mkp_q('DELETE FROM tokens WHERE (user_id = ? AND kind = ?) OR expires < ?', array((int)$userId, $kind, time()));
		mkp_q('INSERT INTO tokens (user_id, kind, hash, expires) VALUES (?, ?, ?, ?)', array((int)$userId, $kind, hash('sha256', $token), time() + (int)$ttl));
		return $token;
	}

	/** User row the token belongs to, or null. $consume removes the token. */
	function mkp_token_user($token, $kind, $consume)
	{
		if (!is_string($token) || !preg_match('/^[0-9a-f]{48}$/', $token)) {
			return null;
		}
		$r = mkp_row('SELECT * FROM tokens WHERE hash = ? AND kind = ? AND expires >= ?', array(hash('sha256', $token), $kind, time()));
		if (!$r) {
			return null;
		}
		if ($consume) {
			$st = mkp_q('DELETE FROM tokens WHERE id = ?', array($r['id']));
			if ($st->rowCount() != 1) {
				return null;
			}
		}
		return mkp_row('SELECT * FROM users WHERE id = ?', array($r['user_id']));
	}

	/* ------------------------------------------------------------------ */
	/*  Messages                                                           */
	/* ------------------------------------------------------------------ */

	/** array(subject, body) of a message in English or Bangla. */
	function mkp_mail_text($kind, $lang, $v)
	{
		$brand = mkl_site('brand');
		$m = array(
			'verify' => array(
				'en' => array('Confirm your email address', "Hello {name},\n\nwelcome to {brand}. Your Mikhmon address is:\n{url}\n\nPlease confirm your email address by opening this link:\n{link}\n\nThe link is valid for 3 days."),
				'bn' => array('আপনার ইমেইল ঠিকানা নিশ্চিত করুন', "প্রিয় {name},\n\n{brand}-এ স্বাগতম। আপনার Mikhmon-এর ঠিকানা:\n{url}\n\nনিচের লিংকটি খুলে আপনার ইমেইল ঠিকানা নিশ্চিত করুন:\n{link}\n\nলিংকটি ৩ দিন কার্যকর থাকবে।"),
			),
			'reset' => array(
				'en' => array('Reset your password', "Hello {name},\n\nopen this link to choose a new password:\n{link}\n\nThe link is valid for 1 hour and works once. If you did not ask for it, ignore this message; your password stays unchanged."),
				'bn' => array('পাসওয়ার্ড রিসেট', "প্রিয় {name},\n\nনতুন পাসওয়ার্ড বেছে নিতে এই লিংকটি খুলুন:\n{link}\n\nলিংকটি ১ ঘণ্টা কার্যকর থাকবে এবং একবারই কাজ করবে। আপনি এটি না চেয়ে থাকলে এই মেসেজ উপেক্ষা করুন; আপনার পাসওয়ার্ড অপরিবর্তিত থাকবে।"),
			),
			'paid' => array(
				'en' => array('Payment received - invoice {invoice}', "Hello {name},\n\nwe received your payment.\n\nInvoice: {invoice}\nAmount: {amount}\nRouters: {routers}\nSubscription valid until: {expires}\n\nYour Mikhmon: {url}\n\nThank you."),
				'bn' => array('পেমেন্ট পাওয়া গেছে - ইনভয়েস {invoice}', "প্রিয় {name},\n\nআপনার পেমেন্ট পাওয়া গেছে।\n\nইনভয়েস: {invoice}\nপরিমাণ: {amount}\nরাউটার: {routers}\nসাবস্ক্রিপশনের মেয়াদ: {expires} পর্যন্ত\n\nআপনার Mikhmon: {url}\n\nধন্যবাদ।"),
			),
			'rejected' => array(
				'en' => array('Payment could not be confirmed - invoice {invoice}', "Hello {name},\n\nwe could not confirm the payment for invoice {invoice} ({amount}).\nPlease check the transaction ID and submit the order again, or contact us: {support}"),
				'bn' => array('পেমেন্ট নিশ্চিত করা যায়নি - ইনভয়েস {invoice}', "প্রিয় {name},\n\nইনভয়েস {invoice} ({amount})-এর পেমেন্ট নিশ্চিত করা যায়নি।\nTransaction ID মিলিয়ে দেখে আবার অর্ডার জমা দিন, অথবা আমাদের সাথে যোগাযোগ করুন: {support}"),
			),
			'expiry' => array(
				'en' => array('Your subscription ends on {expires}', "Hello {name},\n\nyour {brand} subscription for {url} ends on {expires}.\nAfter that date your Mikhmon and the router tunnels are switched off until you renew.\n\nRenew here: {link}"),
				'bn' => array('আপনার সাবস্ক্রিপশন {expires} তারিখে শেষ হবে', "প্রিয় {name},\n\n{url}-এর জন্য আপনার {brand} সাবস্ক্রিপশন {expires} তারিখে শেষ হবে।\nএরপর নবায়ন না করা পর্যন্ত আপনার Mikhmon ও রাউটারের tunnel বন্ধ থাকবে।\n\nনবায়ন করুন: {link}"),
			),
			'expired' => array(
				'en' => array('Your subscription has ended', "Hello {name},\n\nyour {brand} subscription for {url} ended on {expires}. Your Mikhmon and the router tunnels are switched off.\nYour settings are kept; everything comes back as soon as you renew.\n\nRenew here: {link}"),
				'bn' => array('আপনার সাবস্ক্রিপশনের মেয়াদ শেষ হয়েছে', "প্রিয় {name},\n\n{url}-এর জন্য আপনার {brand} সাবস্ক্রিপশন {expires} তারিখে শেষ হয়েছে। আপনার Mikhmon ও রাউটারের tunnel বন্ধ আছে।\nআপনার সেটিংস সংরক্ষিত আছে; নবায়ন করলেই সব আবার চালু হবে।\n\nনবায়ন করুন: {link}"),
			),
			'admin_order' => array(
				'en' => array('Manual payment waiting - {invoice}', "A manual payment is waiting for approval.\n\nCustomer: {name} ({tenant})\nInvoice: {invoice}\nAmount: {amount}\nRouters: {routers}, years: {years}\nPayment details: {note}\nTransaction ID: {trx}\n\nApprove or reject: {link}"),
			),
		);
		$t = isset($m[$kind][$lang]) ? $m[$kind][$lang] : $m[$kind]['en'];
		$v = array_merge(array('brand' => $brand, 'support' => mkl_site('support_contact')), $v);
		$rep = array();
		foreach ($v as $k => $val) {
			$rep['{' . $k . '}'] = (string)$val;
		}
		return array('[' . $brand . '] ' . strtr($t[0], $rep), strtr($t[1], $rep) . "\n\n-- \n" . $brand . "\n" . rtrim((string)mkl_site('portal_url'), '/') . "\n");
	}

	/** Send a message of a kind to a customer, in the customer's language. */
	function mkp_mail_user($user, $kind, $v = array())
	{
		if (!mkp_mail_enabled()) {
			return false;
		}
		$v = array_merge(array('name' => $user['name'], 'tenant' => $user['tenant'], 'url' => mkp_tenant_url($user['tenant'])), $v);
		list($subject, $body) = mkp_mail_text($kind, isset($user['lang']) && $user['lang'] == 'bn' ? 'bn' : 'en', $v);
		return mkp_mail_send($user['email'], $subject, $body);
	}

	function mkp_mail_verify($user)
	{
		if (!mkp_mail_enabled() || !empty($user['email_verified'])) {
			return false;
		}
		return mkp_mail_user($user, 'verify', array('link' => mkp_abs('verify', array('token' => mkp_token_make($user['id'], 'verify', 3 * 86400)))));
	}

	function mkp_mail_reset($user)
	{
		return mkp_mail_user($user, 'reset', array('link' => mkp_abs('reset', array('token' => mkp_token_make($user['id'], 'reset', 3600)))));
	}

	function mkp_mail_order($order, $kind)
	{
		$user = mkp_row('SELECT * FROM users WHERE id = ?', array($order['user_id']));
		if (!$user) {
			return false;
		}
		$t = mkl_tenant_info(mkl_tenant_dir($user['tenant']));
		return mkp_mail_user($user, $kind, array('invoice' => $order['invoice'], 'amount' => mkp_money($order['amount']),
			'routers' => (int)$order['routers'], 'expires' => $t['expires']));
	}

	/** Tell the owner that a manual payment waits for approval. */
	function mkp_mail_admin_order($order, $user)
	{
		$to = mkl_site('admin_email');
		if ($to == '' || !mkp_mail_enabled()) {
			return false;
		}
		list($subject, $body) = mkp_mail_text('admin_order', 'en', array('name' => $user['name'], 'tenant' => $user['tenant'],
			'invoice' => $order['invoice'], 'amount' => mkp_money($order['amount']), 'routers' => (int)$order['routers'], 'years' => (int)$order['years'],
			'note' => $order['note'], 'trx' => $order['trx_id'], 'link' => mkp_abs('admin')));
		return mkp_mail_send($to, $subject, $body);
	}

	/**
	 * Expiry reminders: 7 days and 1 day before the end, and once after it.
	 * Run once a day (tools/portal.php reminders). Each reminder is sent once
	 * per subscription period.
	 * @return int number of messages sent
	 */
	function mkp_mail_reminders()
	{
		if (!mkp_mail_enabled()) {
			return 0;
		}
		$sent = 0;
		$today = date('Y-m-d');
		foreach (mkp_q('SELECT * FROM users ORDER BY id')->fetchAll() as $u) {
			$t = mkl_tenant_info(mkl_tenant_dir($u['tenant']));
			if ($t['status'] != 'active' || $t['expires'] == '' || $t['plan'] == 'unpaid') {
				continue;
			}
			$days = (int)round((strtotime($t['expires']) - strtotime($today)) / 86400);
			if ($days < -3) {
				continue; // long gone: do not write to old accounts again and again
			}
			$kind = $days < 0 ? 'expired' : ($days <= 1 ? 'expiry-1' : ($days <= 7 ? 'expiry-7' : ''));
			if ($kind == '') {
				continue;
			}
			// one row per customer, reminder and period: a second run sends nothing
			$st = mkp_db()->prepare('INSERT OR IGNORE INTO mail_log (user_id, kind, ref, sent_at) VALUES (?, ?, ?, ?)');
			$st->execute(array($u['id'], $kind, $t['expires'], mkp_now()));
			if ($st->rowCount() != 1) {
				continue;
			}
			$ok = mkp_mail_user($u, $kind == 'expired' ? 'expired' : 'expiry', array('days' => max(0, $days), 'expires' => $t['expires'], 'link' => mkp_abs('billing')));
			if ($ok) {
				$sent++;
			} else {
				mkp_q('DELETE FROM mail_log WHERE user_id = ? AND kind = ? AND ref = ?', array($u['id'], $kind, $t['expires']));
			}
		}
		return $sent;
	}
}
