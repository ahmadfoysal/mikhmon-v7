<?php
/*
 *  MikhmonLive portal - pages. Every value is escaped with e().
 */

function v_layout($title, $body, $active = '')
{
	$u = mkp_user();
	$brand = e(mkl_site('brand'));
	$nav = array();
	if ($u) {
		$nav = array('dashboard' => t('nav_dashboard'), 'vpn' => t('nav_vpn'), 'billing' => t('nav_billing'), 'account' => t('nav_account'));
		if ($u['is_admin']) {
			$nav['admin'] = t('nav_admin');
		}
		$nav['logout'] = t('nav_logout');
	} else {
		$nav = array('' => t('nav_home'), 'login' => t('nav_login'), 'register' => t('nav_register'));
	}
	$links = '';
	foreach ($nav as $p => $label) {
		$links .= '<a class="' . ($p === $active ? 'on' : '') . '" href="' . e(mkp_url($p)) . '">' . e($label) . '</a>';
	}
	$other = mkp_lang() == 'bn' ? array('en', 'English') : array('bn', 'বাংলা');
	$links .= '<a href="' . e(mkp_url('lang', array('l' => $other[0]))) . '">' . $other[1] . '</a>';
	$flash = mkp_flash();
	$msg = $flash ? '<div class="msg ' . e($flash[0]) . '">' . e($flash[1]) . '</div>' : '';
	return '<!DOCTYPE html><html lang="' . mkp_lang() . '"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>' . e($title) . ' - ' . $brand . '</title><link rel="stylesheet" href="assets/portal.css?v=1"></head><body>'
		. '<div class="top"><div class="in"><a class="brand" href="' . e(mkp_url($u ? 'dashboard' : '')) . '">' . $brand . '</a>' . $links . '</div></div>'
		. '<main><div class="in">' . $msg . $body . '</div></main>'
		. '<footer><div class="in">' . $brand . ' &middot; based on MIKHMON by Laksamadi Guko (GPL v2)'
		. (mkl_site('support_contact') != '' ? ' &middot; ' . e(mkl_site('support_contact')) : '') . '</div></footer></body></html>';
}

function v_home()
{
	require_once(__DIR__ . '/views_landing.php');
	return v_landing();
}

function v_register($old = array(), $error = '')
{
	$g = function ($k) use ($old) {
		return e(isset($old[$k]) ? $old[$k] : '');
	};
	$b = '<div class="card narrow"><h2>' . e(t('nav_register')) . '</h2>'
		. ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '')
		. (isset($_SESSION['want_plan']) && ($wp = mkp_package($_SESSION['want_plan'])) ? '<p><span class="tag ok">' . e(t('chosen_package', array(mkp_package_name($wp)))) . '</span></p>' : '')
		. '<form method="post" action="' . e(mkp_url('register')) . '" autocomplete="on">' . mkp_csrf_field()
		. '<label for="name">' . e(t('name')) . '</label><input id="name" type="text" name="name" value="' . $g('name') . '" required>'
		. '<label for="email">' . e(t('email')) . '</label><input id="email" type="email" name="email" value="' . $g('email') . '" required>'
		. '<label for="phone">' . e(t('phone')) . '</label><input id="phone" type="text" name="phone" value="' . $g('phone') . '" inputmode="tel">'
		. '<label for="tenant">' . e(t('subdomain')) . '</label><div class="addr"><input id="tenant" type="text" name="tenant" value="' . $g('tenant')
		. '" pattern="[a-z0-9][a-z0-9-]{1,30}[a-z0-9]" autocapitalize="none" required><span>.' . e(mkl_site('base_domain')) . '</span></div>'
		. '<p class="mute">' . e(t('subdomain_hint')) . '</p>'
		. '<label for="pass">' . e(t('password')) . '</label><input id="pass" type="password" name="pass" minlength="8" autocomplete="new-password" required>'
		. '<p class="mute">' . e(t('password_hint')) . '</p>'
		. '<div class="row"><button class="btn" type="submit">' . e(t('register_btn')) . '</button></div></form>'
		. '<p class="mute" style="margin-top:16px">' . e(t('have_account')) . ' <a href="' . e(mkp_url('login')) . '">' . e(t('nav_login')) . '</a></p></div>';
	return v_layout(t('nav_register'), $b, 'register');
}

function v_login($email = '', $error = '')
{
	$b = '<div class="card narrow"><h2>' . e(t('nav_login')) . '</h2>'
		. ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '')
		. '<form method="post" action="' . e(mkp_url('login')) . '">' . mkp_csrf_field()
		. '<label for="email">' . e(t('email')) . '</label><input id="email" type="email" name="email" value="' . e($email) . '" required autofocus>'
		. '<label for="pass">' . e(t('password')) . '</label><input id="pass" type="password" name="pass" autocomplete="current-password" required>'
		. '<div class="row"><button class="btn" type="submit">' . e(t('login_btn')) . '</button></div></form>'
		. '<p class="mute" style="margin-top:16px">' . e(t('no_account')) . ' <a href="' . e(mkp_url('register')) . '">' . e(t('nav_register')) . '</a></p>'
		. (mkp_mail_enabled() ? '<p class="mute"><a href="' . e(mkp_url('forgot')) . '">' . e(t('forgot_link')) . '</a></p>'
			: (mkl_site('support_contact') != '' ? '<p class="mute">' . e(t('forgot', array(mkl_site('support_contact')))) . '</p>' : '')) . '</div>';
	return v_layout(t('nav_login'), $b, 'login');
}

function v_forgot()
{
	$b = '<div class="card narrow"><h2>' . e(t('forgot_title')) . '</h2><p>' . e(t('forgot_text')) . '</p>'
		. '<form method="post" action="' . e(mkp_url('forgot')) . '">' . mkp_csrf_field()
		. '<label for="email">' . e(t('email')) . '</label><input id="email" type="email" name="email" required autofocus>'
		. '<div class="row"><button class="btn" type="submit">' . e(t('forgot_btn')) . '</button>'
		. '<a href="' . e(mkp_url('login')) . '">' . e(t('nav_login')) . '</a></div></form></div>';
	return v_layout(t('forgot_title'), $b, 'login');
}

function v_reset($token, $error = '')
{
	$b = '<div class="card narrow"><h2>' . e(t('reset_title')) . '</h2>' . ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '')
		. '<form method="post" action="' . e(mkp_url('reset')) . '">' . mkp_csrf_field() . '<input type="hidden" name="token" value="' . e($token) . '">'
		. '<label for="pass">' . e(t('new_password')) . '</label><input id="pass" type="password" name="pass" minlength="8" autocomplete="new-password" required autofocus>'
		. '<p class="mute">' . e(t('password_hint')) . '</p><div class="row"><button class="btn" type="submit">' . e(t('save')) . '</button></div></form></div>';
	return v_layout(t('reset_title'), $b, 'login');
}

function v_state_tag($s)
{
	$cls = array('trial' => 'warn', 'active' => 'ok', 'expired' => 'bad', 'suspended' => 'bad', 'unverified' => 'warn');
	return '<span class="tag ' . $cls[$s['state']] . '">' . e(t('state_' . $s['state'])) . '</span>';
}

function v_sub_line($s)
{
	if ($s['expires'] == '' || $s['state'] == 'unverified') {
		return '';
	}
	return e($s['days_left'] >= 0 ? t('valid_until', array($s['expires'], $s['days_left'])) : t('ended_on', array($s['expires'])));
}

function v_dashboard($u)
{
	$s = mkp_subscription($u);
	$url = mkp_tenant_url($u['tenant']);
	$open = in_array($s['state'], array('trial', 'active'), true);
	$verify = '';
	if (mkp_mail_enabled() && !$u['email_verified']) {
		$verify = '<div class="msg warn">' . e(t($s['state'] == 'unverified' ? 'verify_ask_closed' : 'verify_ask', array($u['email']))) . ' <form class="inline" method="post" action="' . e(mkp_url('verify-resend')) . '">'
			. mkp_csrf_field() . '<button class="btn sm alt" type="submit">' . e(t('verify_resend')) . '</button></form></div>';
	}
	$b = '<h1>' . e(t('welcome', array($u['name']))) . '</h1>' . $verify . '<div class="grid">'
		. '<div class="card"><h2>' . e(t('your_mikhmon')) . '</h2><p><a href="' . e($url) . '">' . e(preg_replace('~^https://~', '', $url)) . '</a></p>'
		. ($open ? '<div class="row"><a class="btn" href="' . e(mkp_url('open')) . '">' . e(t('open_mikhmon')) . '</a></div>' : '')
		. '<p class="mute" style="margin-top:12px">' . e(t('mikhmon_login', array($u['tenant']))) . '</p></div>'
		. '<div class="card"><h2>' . e(t('subscription')) . '</h2><p>' . v_state_tag($s) . ($s['package'] ? ' <b>' . e(mkp_package_name($s['package'])) . '</b>' : '') . '</p><p>' . v_sub_line($s) . '</p>'
		. '<p>' . e(t('routers_used', array($s['routers_used'], $s['max_routers'] > 0 ? $s['max_routers'] : '∞'))) . '</p>'
		. '<div class="row"><a class="btn' . ($open && $s['state'] == 'active' && $s['days_left'] > 30 ? ' alt' : '') . '" href="' . e(mkp_url('billing')) . '">' . e(t('renew')) . '</a></div></div></div>'
		. '<div class="card"><h2>' . e(t('next_steps')) . '</h2><ol><li>' . e(t('step_1')) . '</li><li>' . e(t('step_2')) . '</li><li>' . e(t('step_3')) . '</li></ol></div>';
	return v_layout(t('nav_dashboard'), $b, 'dashboard');
}

function v_order_status($st)
{
	$cls = array('pending' => 'warn', 'paid' => 'ok', 'rejected' => 'bad', 'cancelled' => 'bad');
	return '<span class="tag ' . (isset($cls[$st]) ? $cls[$st] : 'warn') . '">' . e(t('st_' . $st)) . '</span>';
}

function v_billing($u, $error = '')
{
	$s = mkp_subscription($u);
	require_once(__DIR__ . '/views_landing.php');
	$cur = $s['package'] ? $s['package']['key'] : '';
	$want = isset($_SESSION['want_plan']) && mkp_package($_SESSION['want_plan']) ? $_SESSION['want_plan'] : '';
	$sel = $want != '' ? $want : ($cur != '' ? $cur : '');
	if ($sel == '') {
		foreach (mkp_packages() as $p) {
			if ($sel == '' || $p['popular']) {
				$sel = $p['key'];
			}
		}
	}
	$b = '<h1>' . e(t('nav_billing')) . '</h1>' . ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '')
		. '<div class="card"><p>' . v_state_tag($s) . ' ' . ($s['package'] ? '<b>' . e(mkp_package_name($s['package'])) . '</b> ' : '') . v_sub_line($s) . '</p></div>'
		. '<div class="card"><h2>' . e(t('choose_package')) . '</h2><form method="post" action="' . e(mkp_url('order')) . '" id="orderform">' . mkp_csrf_field()
		. '<div class="pk-grid">';
	foreach (mkp_packages() as $p) {
		$prices = array();
		foreach (array(1, 2, 3) as $y) {
			$prices[$y] = mkp_price($p, $y);
		}
		$b .= '<label class="pk' . ($p['popular'] ? ' pop' : '') . '"><input type="radio" name="package" value="' . e($p['key']) . '"' . ($p['key'] == $sel ? ' checked' : '')
			. ' data-prices=\'' . e(json_encode($prices)) . '\'><span class="pk-box"><span class="pk-top"><b>' . e(mkp_package_name($p)) . '</b>'
			. ($p['key'] == $cur ? '<span class="tag ok">' . e(t('pkg_current')) . '</span>' : ($p['popular'] ? '<span class="tag warn">' . e(t('pkg_popular')) . '</span>' : '')) . '</span>'
			. '<span class="pk-r">' . e(t($p['routers'] == 1 ? 'pkg_routers' : 'pkg_routers_n', array($p['routers']))) . '</span>'
			. '<span class="pk-p">' . e(mkp_taka($p['price'])) . '<small>' . e(t('per_year')) . '</small></span></span></label>';
	}
	$yopts = '';
	foreach (array(1, 2, 3) as $y) {
		$d = mkp_discount($y);
		$yopts .= '<option value="' . $y . '">' . e(t('year_' . $y)) . ($d > 0 ? ' (' . e(t('save_pct', array($d))) . ')' : '') . '</option>';
	}
	$b .= '</div><div class="grid" style="margin-top:8px"><div><label for="years">' . e(t('years')) . '</label><select id="years" name="years">' . $yopts . '</select></div>'
		. '<div><label>' . e(t('total')) . '</label><div class="total" id="total"></div></div></div>'
		. '<p class="mute" style="margin-top:12px">' . e(t('upgrade_note')) . '</p>';
	if (mkp_ps_enabled()) {
		$b .= '<div class="row"><button class="btn" type="submit" name="method" value="paystation">' . e(t('pay_online')) . '</button></div>';
	}
	if (mkl_site('manual_payment') != '') {
		$b .= '<hr style="border:0;border-top:1px solid var(--line);margin:20px 0"><h3>' . e(t('pay_manual')) . '</h3><p>' . nl2br(e(mkl_site('manual_payment'))) . '</p>'
			. '<div class="grid"><div><label for="mm">' . e(t('pay_method')) . '</label><input id="mm" type="text" name="manual_method" placeholder="bKash / Nagad / Rocket"></div>'
			. '<div><label for="ms">' . e(t('manual_sender')) . '</label><input id="ms" type="text" name="manual_sender" inputmode="tel"></div>'
			. '<div><label for="mt">' . e(t('manual_trx')) . '</label><input id="mt" type="text" name="manual_trx"></div></div>'
			. '<div class="row"><button class="btn alt" type="submit" name="method" value="manual">' . e(t('manual_submit')) . '</button></div>';
	}
	$sym = mkl_site('currency') == 'BDT' ? '৳' : mkl_site('currency') . ' ';
	$b .= '</form></div><script>(function(){var f=document.getElementById("orderform"),y=document.getElementById("years"),o=document.getElementById("total");'
		. 'function c(){var r=f.querySelector("input[name=package]:checked");if(!r){o.textContent="";return}var p=JSON.parse(r.getAttribute("data-prices"))[y.value];'
		. 'o.textContent=' . json_encode($sym) . '+p.toLocaleString("en-US")}f.addEventListener("change",c);c()})();</script>';

	$orders = mkp_q('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50', array($u['id']))->fetchAll();
	$b .= '<div class="card"><h2>' . e(t('orders')) . '</h2>';
	if (!$orders) {
		$b .= '<p class="mute">' . e(t('no_orders')) . '</p>';
	} else {
		$b .= '<div class="scroll"><table><tr><th>' . e(t('invoice')) . '</th><th>' . e(t('date')) . '</th><th>' . e(t('routers')) . '</th><th>' . e(t('years'))
			. '</th><th>' . e(t('amount')) . '</th><th>' . e(t('status')) . '</th><th></th></tr>';
		foreach ($orders as $o) {
			$act = '';
			if ($o['status'] == 'pending') {
				if ($o['method'] == 'paystation') {
					$act .= '<form class="inline" method="post" action="' . e(mkp_url('pay-check')) . '">' . mkp_csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id']
						. '"><button class="btn sm" type="submit">' . e(t('check_payment')) . '</button></form> ';
				}
				$act .= '<form class="inline" method="post" action="' . e(mkp_url('order-cancel')) . '">' . mkp_csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id']
					. '"><button class="btn sm alt" type="submit">' . e(t('cancel')) . '</button></form>';
			}
			$opk = mkp_package($o['package']);
			$b .= '<tr><td>' . e($o['invoice']) . '</td><td>' . e(substr($o['created_at'], 0, 10)) . '</td><td>' . ($opk ? e(mkp_package_name($opk)) . ' · ' : '') . (int)$o['routers'] . '</td><td>' . (int)$o['years']
				. '</td><td>' . e(mkp_money($o['amount'])) . '</td><td>' . v_order_status($o['status']) . '</td><td>' . $act . '</td></tr>';
		}
		$b .= '</table></div>';
	}
	return v_layout(t('nav_billing'), $b . '</div>', 'billing');
}

function v_copy_js()
{
	return '<script>function mkCopy(id,btn){var t=document.getElementById(id).textContent;function d(){btn.textContent=' . json_encode(t('copied'))
		. '}if(navigator.clipboard){navigator.clipboard.writeText(t).then(d)}else{var a=document.createElement("textarea");a.value=t;document.body.appendChild(a);a.select();document.execCommand("copy");a.remove();d()}}</script>';
}

function v_vpn($u, $show = null, $error = '')
{
	$b = '<h1>' . e(t('vpn_title')) . '</h1>' . ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '');
	if (!mkp_vpn_enabled()) {
		return v_layout(t('nav_vpn'), $b . '<div class="msg warn">' . e(t('vpn_off')) . '</div>', 'vpn');
	}
	$b .= '<p>' . e(t('vpn_intro')) . '</p>';
	if ($show) {
		$b .= '<div class="card"><h2>' . e(t('vpn_script', array($show['name']))) . '</h2><p>' . e(t('vpn_script_how')) . '</p><pre id="s1">' . e(mkp_vpn_script($show)) . '</pre>'
			. '<div class="row" style="margin:0 0 20px"><button class="btn" type="button" onclick="mkCopy(\'s1\',this)">' . e(t('copy')) . '</button></div>'
			. '<h3>' . e(t('vpn_unscript')) . '</h3><pre id="s2">' . e(mkp_vpn_unscript($show)) . '</pre></div>' . v_copy_js();
	}
	$types = mkp_vpn_types();
	$b .= '<div class="card"><h2>' . e(t('vpn_add')) . '</h2><form method="post" action="' . e(mkp_url('vpn-add')) . '">' . mkp_csrf_field()
		. '<div class="grid"><div><label for="vn">' . e(t('vpn_name')) . '</label><input id="vn" type="text" name="name" maxlength="30" pattern="[A-Za-z0-9_.\-]+" placeholder="Office-1" required></div>'
		. '<div><label for="vt">' . e(t('vpn_type')) . '</label><select id="vt" name="type">';
	foreach ($types as $ty) {
		$b .= '<option value="' . $ty . '">' . e(t('vpn_' . $ty)) . '</option>';
	}
	$b .= '</select></div></div><div class="row"><button class="btn" type="submit">' . e(t('vpn_add')) . '</button></div></form></div>';

	$peers = mkp_vpn_peers($u);
	$b .= '<div class="card"><h2>' . e(t('vpn_list')) . '</h2>';
	if (!$peers) {
		$b .= '<p class="mute">' . e(t('vpn_none')) . '</p>';
	} else {
		$b .= '<div class="scroll"><table><tr><th>' . e(t('vpn_name')) . '</th><th>' . e(t('vpn_type')) . '</th><th>' . e(t('vpn_address')) . '</th><th></th></tr>';
		foreach ($peers as $p) {
			$b .= '<tr><td>' . e($p['name']) . '</td><td>' . e(t('vpn_' . $p['type'])) . '</td><td>' . e($p['ip']) . '</td><td>'
				. '<a class="btn sm" href="' . e(mkp_url('vpn', array('show' => $p['id']))) . '">' . e(t('vpn_show')) . '</a> '
				. '<form class="inline" method="post" action="' . e(mkp_url('vpn-del')) . '" onsubmit="return confirm(' . e(json_encode(t('vpn_delete_confirm'))) . ')">' . mkp_csrf_field()
				. '<input type="hidden" name="id" value="' . (int)$p['id'] . '"><button class="btn sm bad" type="submit">' . e(t('vpn_delete')) . '</button></form></td></tr>';
		}
		$b .= '</table></div>';
	}
	return v_layout(t('nav_vpn'), $b . '</div>', 'vpn');
}

function v_account($u, $error = '')
{
	$b = '<h1>' . e(t('account_title')) . '</h1>' . ($error != '' ? '<div class="msg bad">' . e($error) . '</div>' : '')
		. '<div class="grid"><div class="card"><form method="post" action="' . e(mkp_url('account')) . '">' . mkp_csrf_field()
		. '<input type="hidden" name="what" value="profile"><label for="name">' . e(t('name')) . '</label><input id="name" type="text" name="name" value="' . e($u['name']) . '" required>'
		. '<label for="phone">' . e(t('phone')) . '</label><input id="phone" type="text" name="phone" value="' . e($u['phone']) . '">'
		. '<label>' . e(t('email')) . '</label><input type="email" value="' . e($u['email']) . '" disabled>'
		. '<div class="row"><button class="btn" type="submit">' . e(t('save')) . '</button></div></form></div>'
		. '<div class="card"><form method="post" action="' . e(mkp_url('account')) . '">' . mkp_csrf_field()
		. '<input type="hidden" name="what" value="password"><label for="cur">' . e(t('current_password')) . '</label><input id="cur" type="password" name="current" autocomplete="current-password" required>'
		. '<label for="new">' . e(t('new_password')) . '</label><input id="new" type="password" name="pass" minlength="8" autocomplete="new-password" required>'
		. '<p class="mute">' . e(t('password_hint')) . '</p><div class="row"><button class="btn" type="submit">' . e(t('save')) . '</button></div></form></div></div>';
	return v_layout(t('nav_account'), $b, 'account');
}

/* ---------------------------------------------------------------------- */
/*  Admin (owner of the service) - English only                           */
/* ---------------------------------------------------------------------- */

function v_admin()
{
	$pending = mkp_q('SELECT o.*, u.email, u.tenant, u.name uname FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status = "pending" ORDER BY o.id')->fetchAll();
	$b = '<h1>Admin</h1><div class="card"><h2>Orders waiting (' . count($pending) . ')</h2>';
	if (!$pending) {
		$b .= '<p class="mute">Nothing to approve.</p>';
	} else {
		$b .= '<div class="scroll"><table><tr><th>Invoice</th><th>Customer</th><th>Order</th><th>Amount</th><th>Method</th><th>Payment details</th><th></th></tr>';
		foreach ($pending as $o) {
			$b .= '<tr><td>' . e($o['invoice']) . '<br><span class="mute">' . e($o['created_at']) . '</span></td><td>' . e($o['uname']) . '<br><span class="mute">' . e($o['tenant'])
				. '</span></td><td>' . (($apk = mkp_package($o['package'])) ? e(mkp_package_name($apk)) . ': ' : '') . (int)$o['routers'] . ' router, ' . (int)$o['years'] . ' yr</td><td>' . e(mkp_money($o['amount'])) . '</td><td>' . e($o['method']) . '</td><td>'
				. e($o['note']) . ($o['trx_id'] != '' ? '<br>TrxID: <b>' . e($o['trx_id']) . '</b>' : '') . '</td><td>'
				. '<form class="inline" method="post" action="' . e(mkp_url('admin-order')) . '">' . mkp_csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id'] . '">'
				. '<button class="btn sm" name="do" value="approve" type="submit" onclick="return confirm(\'Mark as paid and extend the subscription?\')">Approve</button> '
				. '<button class="btn sm bad" name="do" value="reject" type="submit" onclick="return confirm(\'Reject this order?\')">Reject</button></form></td></tr>';
		}
		$b .= '</table></div>';
	}
	$b .= '</div>';

	$users = mkp_q('SELECT * FROM users ORDER BY id DESC')->fetchAll();
	$b .= '<div class="card"><h2>Customers (' . count($users) . ')</h2><div class="scroll"><table><tr><th>Address</th><th>Customer</th><th>Status</th><th>Expires</th><th>Routers</th><th>VPN</th><th></th></tr>';
	foreach ($users as $c) {
		$s = mkp_subscription($c);
		$vpn = (int)mkp_row('SELECT COUNT(*) c FROM vpn_peers WHERE user_id = ?', array($c['id']))['c'];
		$b .= '<tr><td><a href="' . e(mkp_tenant_url($c['tenant'])) . '">' . e($c['tenant']) . '</a></td><td>' . e($c['name']) . '<br><span class="mute">' . e($c['email']) . ' ' . e($c['phone'])
			. ($c['email_verified'] ? '' : ' (unverified)') . '</span></td><td>' . v_state_tag($s) . '</td><td>' . e($s['expires']) . '</td><td>' . (int)$s['routers_used'] . ' / ' . ($s['max_routers'] > 0 ? (int)$s['max_routers'] : '&infin;')
			. '</td><td>' . $vpn . '</td><td><a class="btn sm alt" href="' . e(mkp_url('admin-user', array('id' => $c['id']))) . '">Edit</a></td></tr>';
	}
	$paid = mkp_row('SELECT COUNT(*) c, COALESCE(SUM(amount), 0) s FROM orders WHERE status = "paid" AND method != "admin"');
	$b .= '</table></div></div><p class="mute">Paid orders: ' . (int)$paid['c'] . ' &middot; ' . e(mkp_money($paid['s'])) . '</p>';
	return v_layout('Admin', $b, 'admin');
}

function v_admin_user($c)
{
	$s = mkp_subscription($c);
	$b = '<p><a href="' . e(mkp_url('admin')) . '">&larr; Admin</a></p><h1>' . e($c['tenant']) . '.' . e(mkl_site('base_domain')) . '</h1>'
		. '<p>' . e($c['name']) . ' &middot; ' . e($c['email']) . ' &middot; ' . e($c['phone']) . ' &middot; registered ' . e(substr($c['created_at'], 0, 10)) . '</p>'
		. '<div class="grid"><div class="card"><h2>Subscription</h2><form method="post" action="' . e(mkp_url('admin-user', array('id' => $c['id']))) . '">' . mkp_csrf_field()
		. '<input type="hidden" name="what" value="sub"><label for="mr">Router limit (0 = unlimited)</label><input id="mr" type="number" name="max_routers" min="0" max="10000" value="' . (int)$s['max_routers'] . '">'
		. '<label for="ex">Expires</label><input id="ex" type="date" name="expires" value="' . e($s['expires']) . '">'
		. '<label for="st">Status</label><select id="st" name="status"><option value="active"' . ($s['status'] == 'active' ? ' selected' : '') . '>active</option><option value="suspended"'
		. ($s['status'] != 'active' ? ' selected' : '') . '>suspended</option></select>'
		. '<div class="row"><button class="btn" type="submit">Save</button></div></form></div>'
		. ($c['email_verified'] ? '' : '<div class="card"><h2>Email not confirmed</h2><p class="mute">Use this when the customer cannot receive the mail and you know the address is theirs.'
			. ' A waiting account opens and its trial starts today.</p><form method="post" action="' . e(mkp_url('admin-user', array('id' => $c['id']))) . '">' . mkp_csrf_field()
			. '<input type="hidden" name="what" value="verify"><div class="row"><button class="btn alt" type="submit">Mark as confirmed</button></div></form></div>')
		. '<div class="card"><h2>Set a new password</h2><p class="mute">For a customer who lost the password. It applies to the portal and to their Mikhmon login.</p>'
		. '<form method="post" action="' . e(mkp_url('admin-user', array('id' => $c['id']))) . '">' . mkp_csrf_field()
		. '<input type="hidden" name="what" value="pass"><label for="np">New password</label><input id="np" type="text" name="pass" minlength="8" autocomplete="off" required>'
		. '<div class="row"><button class="btn alt" type="submit">Set password</button></div></form></div></div>';
	$orders = mkp_q('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', array($c['id']))->fetchAll();
	$b .= '<div class="card"><h2>Orders</h2><div class="scroll"><table><tr><th>Invoice</th><th>Date</th><th>Order</th><th>Amount</th><th>Method</th><th>Status</th><th>TrxID / note</th></tr>';
	foreach ($orders as $o) {
		$b .= '<tr><td>' . e($o['invoice']) . '</td><td>' . e($o['created_at']) . '</td><td>' . (int)$o['routers'] . ' router, ' . (int)$o['years'] . ' yr</td><td>' . e(mkp_money($o['amount']))
			. '</td><td>' . e($o['method']) . '</td><td>' . v_order_status($o['status']) . '</td><td>' . e($o['trx_id']) . ' ' . e($o['note']) . '</td></tr>';
	}
	return v_layout('Admin', $b . '</table></div></div>', 'admin');
}
