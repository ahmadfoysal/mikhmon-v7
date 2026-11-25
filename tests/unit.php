<?php
// Unit tests for the engine, the store and the template interpreter.
// Run: php tests/unit.php
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
error_reporting(E_ALL);
$tmp = sys_get_temp_dir() . '/mkl-unit-' . getmypid();
mkdir($tmp);
define('MKL_TENANT', '');
define('MKL_DATA', $tmp);
require __DIR__ . '/../lib/mkl_store.php';
require __DIR__ . '/../lib/mikhmonlive.php';
require __DIR__ . '/../lib/mkl_template.php';

$fail = 0;
function ok($cond, $what)
{
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL: $what\n";
	}
}
function same($a, $b, $what)
{
	ok($a === $b, $what . ' => ' . var_export($a, true) . ' (expected ' . var_export($b, true) . ')');
}

// --- dates
same(mkl_date_legacy('2024-01-14'), 'jan/14/2024', 'iso to legacy');
same(mkl_date_legacy('jan/14/2024'), 'jan/14/2024', 'legacy stays');
same(mkl_date_legacy('Dec/01/2025'), 'dec/01/2025', 'legacy lowercased');
same(mkl_date_iso('dec/31/2025'), '2025-12-31', 'legacy to iso');
same(mkl_date_legacy('vc-123'), 'vc-123', 'non date untouched');
same(mkl_comment_legacy('2030-01-14 10:20:30 note'), 'jan/14/2030 10:20:30 note', 'iso comment');
same(mkl_comment_legacy('vc-123-01.14.24-'), 'vc-123-01.14.24-', 'voucher comment untouched');
ok(mkl_comment_has_expiry('2030-01-14 10:20:30'), 'iso expiry detected');
ok(mkl_comment_has_expiry('jan/14/2030 10:20:30'), 'legacy expiry detected');
ok(!mkl_comment_has_expiry('up-hello'), 'no expiry');
same(mkl_report_day_keys('jan/14/2024'), array('jan/14/2024', '2024-01-14'), 'day keys');
same(mkl_report_month_keys('jan2024'), array('jan2024', '012024'), 'month keys');
same(mkl_report_month_keys('122024'), array('dec2024', '122024'), 'month keys numeric');
same(mkl_num(''), 0, 'num empty');
same(mkl_num('1500'), 1500, 'num');

// --- router scripts
$s = mkl_onlogin_script('remc', '1000', '1d', '1200', 'Enable', '1day');
$h = mkl_onlogin_parse($s);
same($h['expmode'] . '|' . $h['price'] . '|' . $h['validity'] . '|' . $h['sprice'] . '|' . $h['lock'], 'remc|1000|1d|1200|Enable', 'header round trip');
ok(strpos($s, MKL_ENGINE) !== false && !mkl_onlogin_outdated($s), 'new script is current');
ok(strpos($s, "\n") === false, 'script is one line');
same(substr_count($s, '{'), substr_count($s, '}'), 'braces balanced');
same(substr_count($s, '['), substr_count($s, ']'), 'brackets balanced');
same(substr_count($s, '('), substr_count($s, ')'), 'parens balanced');
ok(mkl_onlogin_outdated(':put (",remc,1000,1d,1200,,Enable,"); {:local date [ /system clock get date ];}'), 'classic script is outdated');
ok(!mkl_onlogin_outdated(':put (",,1000,,,noexp,Disable,")'), 'noexp profile is not outdated');
ok(!mkl_onlogin_outdated('/log info hello'), 'foreign script left alone');
same(mkl_onlogin_script('0', '100', '', '0', 'Disable', 'free'), ':put (",,100,,,noexp,Disable,");', 'noexp script');
same(mkl_onlogin_script('0', '', '', '', 'Disable', 'x'), '', 'no script');
$bad = mkl_onlogin_script('rem', '1"; /system reset-configuration; "', '1d', '0', 'x', 'a"b');
ok(strpos($bad, 'reset-configuration;') === false && strpos($bad, 'a"b') === false, 'script injection stripped');
foreach (array('rem', 'ntf', 'remc', 'ntfc') as $m) {
	$mon = mkl_monitor_script('1day', $m);
	same(substr_count($mon, '{'), substr_count($mon, '}'), "monitor braces $m");
	same(substr_count($mon, '['), substr_count($mon, ']'), "monitor brackets $m");
}
same(mkl_monitor_script('x', '0'), '', 'no monitor without expiry');

// --- store
same(mkl_router_save('r1', array('host' => '10.0.0.1', 'user' => 'adm', 'pass_plain' => "p'a\"ss\\<?php", 'hotspotname' => "A%B'<?php x ?>")), 'ok', 'router save');
$r = mkl_router('r1');
same($r['pass_plain'], "p'a\"ss\\<?php", 'password round trip');
same($r['hotspotname'], "A%B'<?php x ?>", 'special characters kept as data');
ok(strpos(file_get_contents($tmp . '/config.json.php'), "p'a") === false, 'password not stored in clear');
same(substr(file_get_contents($tmp . '/config.json.php'), 0, strlen(MKL_GUARD)), MKL_GUARD, 'config starts with guard');
same(mkl_router_save('r1', array('host' => '10.0.0.2'), 'r2'), 'ok', 'rename');
same(array_keys(mkl_routers()), array('r2'), 'renamed');
same(mkl_router('r2')['pass_plain'], "p'a\"ss\\<?php", 'password kept on rename');
file_put_contents($tmp . '/tenant.json', json_encode(array('max_routers' => 1)));
same(mkl_router_save('r3', array()), 'limit', 'router limit enforced');
same(mkl_router_save('r2', array('currency' => 'BDT')), 'ok', 'edit allowed at limit');
file_put_contents($tmp . '/tenant.json', json_encode(array('max_routers' => 2)));
same(mkl_router_save('r3', array()), 'ok', 'second router after limit raised');
same(mkl_router_save('r3', array(), 'r2'), 'exists', 'rename onto existing refused');
mkl_router_remove('r3');
same(count(mkl_routers()), 1, 'remove');
same(mkl_router_name('../../etc/x y'), '....etcxy', 'name sanitised');
ok(mkl_admin_verify('mikhmon', '1234'), 'default login');
mkl_admin_set('boss', 's3cret');
ok(mkl_admin_verify('boss', 's3cret') && !mkl_admin_verify('boss', '1234') && !mkl_admin_verify('mikhmon', 's3cret'), 'admin change');
mkl_admin_set('boss2', '');
ok(mkl_admin_verify('boss2', 's3cret'), 'empty password keeps hash');
mkl_pref_set(array('theme' => 'blue'));
same(mkl_pref('theme'), 'blue', 'pref');

// --- templates
foreach (array('default', 'thermal', 'small') as $k) {
	same(mkl_tpl_check(mkl_tpl_builtin($k)), '', "builtin template $k accepted");
}
$vars = array('username' => 'u1', 'password' => 'p1', 'usermode' => 'up', 'qr' => 'no', 'num' => 3, 'price' => 'Rp 5', 'validity' => '1d',
	'timelimit' => '', 'datalimit' => '', 'hotspotname' => 'HS', 'dnsname' => 'x.net', 'logo' => 'l.png', 'qrcode' => '<q>');
$out = mkl_tpl_render('default', $vars);
ok(strpos($out, 'u1') !== false && strpos($out, 'p1') !== false && strpos($out, '<?') === false, 'default renders');
$t = '<?php if ($usermode == "vc") { ?>V<?php } elseif ($usermode == "up" && $qr != "yes") { ?>U<?= " [$num]"; ?><?php } else { ?>E<?php } ?>|<?php $c = strtoupper("a") . "b"; echo $c, $nope; ?>|<?= date("Y") ?>';
same(mkl_tpl_check($t), '', 'custom template accepted');
$n = mkl_tpl_compile($t);
same(mkl_tpl_run($n, $vars), 'U [3]|Ab|' . date('Y'), 'custom template output');
foreach (array(
	'<?php system("id"); ?>', '<?php echo `id`; ?>', '<?php include "x"; ?>', '<?= $_SERVER["X"] ?>', '<?php eval($x); ?>',
	'<?= file_get_contents("/etc/passwd") ?>', '<?php $f = "system"; $f("id"); ?>', '<?php if ($a) { ?>x', '<?php echo $a->b; ?>',
	'<?php function x(){} ?>', '<?= $a[0] ?>', '<?php } ?>', 'x <?php echo 1;',
) as $evil) {
	ok(mkl_tpl_check($evil) != '', 'rejected: ' . $evil);
}
same(mkl_tpl_save('default', '<?php system("id"); ?>') != '', true, 'evil template not saved');
ok(!is_file(mkl_tpl_file('default')), 'no file written for refused template');
same(mkl_tpl_save('small', 'Hi <?= $username ?>'), '', 'template saved');
same(mkl_tpl_load('small'), 'Hi <?= $username ?>', 'template loaded');

// --- tenants
same(mkl_valid_tenant('shop-1'), true, 'tenant ok');
foreach (array('', 'a', '-ab', 'ab-', 'a.b', '../x', 'ABC', 'www mikh') as $badname) {
	same(mkl_valid_tenant($badname), false, "tenant refused: $badname");
}

// --- address policy
ok(mkl_ip_in_cidr('10.99.3.4', '10.99.0.0/16') && !mkl_ip_in_cidr('10.98.3.4', '10.99.0.0/16'), 'cidr match');
ok(mkl_ip_in_cidr('1.2.3.4', '0.0.0.0/0') && mkl_ip_in_cidr('1.2.3.4', '1.2.3.4'), 'cidr edge cases');
same(mkl_host_check('127.0.0.1'), '127.0.0.1', 'single mode does not restrict hosts');

array_map('unlink', array_merge(glob($tmp . '/*'), glob($tmp . '/.[a-z]*')));
rmdir($tmp);
echo $fail ? "$fail test(s) failed\n" : "all unit tests passed\n";
exit($fail ? 1 : 0);
