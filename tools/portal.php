<?php
/*
 * MikhmonLive portal tool (command line only).
 *
 *   php tools/portal.php admin <email>       make this registered customer a portal admin
 *   php tools/portal.php noadmin <email>     take the admin right away
 *   php tools/portal.php vpn-sync            rewrite the VPN state files (run daily from cron:
 *                                            tunnels of expired accounts are switched off)
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
} elseif ($cmd == 'stats') {
	$r = mkp_row('SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM orders WHERE status = "pending") p, (SELECT COUNT(*) FROM vpn_peers) v');
	echo "customers: {$r['u']}  pending orders: {$r['p']}  vpn connections: {$r['v']}\n";
} else {
	echo preg_replace('/^ \* ?/m', '', substr(file_get_contents(__FILE__), 9, 470)), "\n";
	exit(1);
}
