<?php
/*
 * MikhmonLive portal tool (command line only).
 *
 *   php tools/portal.php admin <email>       make this registered customer a portal admin
 *   php tools/portal.php noadmin <email>     take the admin right away
 *   php tools/portal.php vpn-sync            rewrite the VPN state files (run daily from cron:
 *                                            tunnels of expired accounts are switched off)
 *   php tools/portal.php reminders           send the expiry reminder mails (run daily from cron)
 *   php tools/portal.php mail-test <email>   send a test mail to check the SMTP settings
 *   php tools/portal.php purge-unverified [days]   remove empty accounts that never confirmed their
 *                                            e-mail address (default: older than 7 days)
 *   php tools/portal.php stats
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
require_once(__DIR__ . '/../portal/src/app.php');

if (!mkl_tenant_mode()) {
	fwrite(STDERR, "Tenant mode is not configured. Copy include/site.sample.php to include/site.php first.\n");
	exit(1);
}
$cmd = isset($argv[1]) ? $argv[1] : '';
$arg = isset($argv[2]) ? strtolower($argv[2]) : '';

if ($cmd == 'admin' || $cmd == 'noadmin') {
	$st = mkp_q('UPDATE users SET is_admin = ? WHERE email = ?', array($cmd == 'admin' ? 1 : 0, $arg));
	if ($st->rowCount() != 1) {
		fwrite(STDERR, "No customer with the email $arg. Register in the portal first.\n");
		exit(1);
	}
	echo $cmd == 'admin' ? "$arg is now a portal admin.\n" : "$arg is no longer an admin.\n";
} elseif ($cmd == 'vpn-sync') {
	mkp_vpn_write_state();
	echo "VPN state written to " . mkp_vpn_dir() . "\n";
} elseif ($cmd == 'reminders') {
	echo mkp_mail_enabled() ? mkp_mail_reminders() . " reminder(s) sent\n" : "E-mail is not configured (smtp_host, mail_from).\n";
} elseif ($cmd == 'mail-test') {
	if (!mkp_mail_enabled()) {
		fwrite(STDERR, "E-mail is not configured (smtp_host, mail_from in include/site.php).\n");
		exit(1);
	}
	if (mkp_mail_send($arg, mkl_site('brand') . ' test mail', "This is a test.\nএটি একটি পরীক্ষামূলক ইমেইল।\n")) {
		echo "Sent to $arg.\n";
	} else {
		fwrite(STDERR, "Sending failed. See " . mkp_data_root() . "/mail-errors.log\n");
		exit(1);
	}
} elseif ($cmd == 'purge-unverified') {
	$gone = mkp_purge_unverified($arg != '' ? (int)$arg : 7);
	echo count($gone) . " account(s) removed" . ($gone ? ': ' . implode(', ', $gone) : '') . "\n";
} elseif ($cmd == 'stats') {
	$r = mkp_row('SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM orders WHERE status = "pending") p, (SELECT COUNT(*) FROM vpn_peers) v');
	echo "customers: {$r['u']}  pending orders: {$r['p']}  vpn connections: {$r['v']}\n";
} else {
	echo preg_replace('/^ \* ?/m', '', substr(file_get_contents(__FILE__), 9, 880)), "\n";
	exit(1);
}
