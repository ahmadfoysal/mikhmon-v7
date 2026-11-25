<?php
/*
 * MikhmonLive account tool (command line only).
 *
 *   php tools/tenant.php create <name> --user=U --pass=P [--routers=1] [--expires=YYYY-MM-DD] [--plan=yearly]
 *   php tools/tenant.php set    <name> [--routers=N] [--expires=YYYY-MM-DD] [--status=active|suspended] [--user=U] [--pass=P] [--plan=X]
 *   php tools/tenant.php renew  <name> [--years=1]
 *   php tools/tenant.php info   <name>
 *   php tools/tenant.php list
 *   php tools/tenant.php import <name> <path-to-old-include/config.php>
 *   php tools/tenant.php delete <name> --yes
 *
 * In single mode (no include/site.php) only "import" is useful:
 *   php tools/tenant.php import - <path-to-old-include/config.php>
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
require_once(__DIR__ . '/../lib/mkl_store.php');
require_once(__DIR__ . '/../lib/routeros_api.class.php');

$args = array();
$opt = array();
foreach (array_slice($argv, 1) as $a) {
	if (preg_match('/^--([a-z]+)(?:=(.*))?$/s', $a, $m)) {
		$opt[$m[1]] = isset($m[2]) ? $m[2] : '1';
	} else {
		$args[] = $a;
	}
}
$cmd = isset($args[0]) ? $args[0] : '';
$name = isset($args[1]) ? strtolower($args[1]) : '';

function done($msg, $code = 0)
{
	fwrite($code ? STDERR : STDOUT, $msg . "\n");
	exit($code);
}

function show($name)
{
	$dir = mkl_tenant_dir($name);
	$t = mkl_tenant_info($dir);
	$c = mkl_store_read($dir);
	return sprintf("%-24s %-10s expires %-10s routers %d/%s  admin %s", $name, $t['status'], $t['expires'] == '' ? 'never' : $t['expires'],
		count($c['routers']), $t['max_routers'] > 0 ? $t['max_routers'] : 'unlimited', $c['admin']['user']);
}

if ($cmd == 'import' && !mkl_tenant_mode()) {
	if (!isset($args[2]) || !is_file($args[2])) {
		done('Usage: php tools/tenant.php import - <old config.php>', 1);
	}
	mkl_boot();
	$n = mkl_import_legacy(file_get_contents($args[2]));
	done($n < 0 ? 'Nothing recognised in that file.' : "Imported $n router(s) and the admin login.", $n < 0 ? 1 : 0);
}

if (!mkl_tenant_mode()) {
	done("Tenant mode is not configured. Copy include/site.sample.php to include/site.php first.", 1);
}
if ($cmd == 'list') {
	foreach (mkl_tenant_list() as $n) {
		echo show($n), "\n";
	}
	exit(0);
}
if (!in_array($cmd, array('create', 'set', 'renew', 'info', 'import', 'delete'), true) || $name == '') {
	done(preg_replace('/^ \* ?/m', '', substr(file_get_contents(__FILE__), 9, 760)), 1);
}

if ($cmd == 'create') {
	$values = array('max_routers' => isset($opt['routers']) ? (int)$opt['routers'] : 1,
		'expires' => isset($opt['expires']) ? $opt['expires'] : date('Y-m-d', strtotime('+1 year')),
		'plan' => isset($opt['plan']) ? $opt['plan'] : 'yearly');
	$err = mkl_tenant_create($name, isset($opt['user']) ? $opt['user'] : '', isset($opt['pass']) ? $opt['pass'] : '', $values);
	done($err != '' ? $err : "Created: https://$name." . mkl_site('base_domain') . "\n" . show($name), $err != '' ? 1 : 0);
}

if (!mkl_valid_tenant($name) || !is_dir(mkl_tenant_dir($name))) {
	done("No such account: $name", 1);
}
$dir = mkl_tenant_dir($name);

if ($cmd == 'info') {
	done(show($name));
}
if ($cmd == 'set') {
	$values = array();
	if (isset($opt['routers'])) {
		$values['max_routers'] = (int)$opt['routers'];
	}
	foreach (array('expires', 'status', 'plan') as $k) {
		if (isset($opt[$k])) {
			$values[$k] = $opt[$k];
		}
	}
	if ($values && !mkl_tenant_write($name, $values)) {
		done('Invalid value.', 1);
	}
	if (isset($opt['user']) || isset($opt['pass'])) {
		mkl_admin_set(isset($opt['user']) ? $opt['user'] : '', isset($opt['pass']) ? $opt['pass'] : '', $dir);
	}
	done(show($name));
}
if ($cmd == 'renew') {
	$t = mkl_tenant_info($dir);
	$from = ($t['expires'] != '' && $t['expires'] > date('Y-m-d')) ? $t['expires'] : date('Y-m-d');
	$years = isset($opt['years']) ? max(1, (int)$opt['years']) : 1;
	mkl_tenant_write($name, array('expires' => date('Y-m-d', strtotime($from . " +$years year")), 'status' => 'active'));
	done(show($name));
}
if ($cmd == 'import') {
	if (!isset($args[2]) || !is_file($args[2])) {
		done('Usage: php tools/tenant.php import <name> <old config.php>', 1);
	}
	$n = mkl_import_legacy(file_get_contents($args[2]), $dir);
	done($n < 0 ? 'Nothing recognised in that file.' : "Imported $n router(s) and the admin login.\n" . show($name), $n < 0 ? 1 : 0);
}
if ($cmd == 'delete') {
	if (!isset($opt['yes'])) {
		done("This removes all settings of $name. Repeat with --yes to confirm.", 1);
	}
	foreach (array_merge((array)glob($dir . '/*'), (array)glob($dir . '/.[!.]*')) as $f) {
		if (is_file($f)) {
			unlink($f);
		}
	}
	done(rmdir($dir) ? "Deleted $name" : "Could not remove $dir", 0);
}
