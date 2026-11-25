<?php
/*
 *  MikhmonLive portal - landing page (bare domain, visitors).
 *  Every value is escaped with e(). The dashboard in the hero is an
 *  illustration built from HTML and CSS, not live data.
 */

/** "৳1,200" style price. */
function mkp_taka($n)
{
	return (mkl_site('currency') == 'BDT' ? '৳' : mkl_site('currency') . ' ') . number_format((int)$n);
}

/** Small inline icons (stroke style). */
function v_icon($name)
{
	$p = array(
		'ticket' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		'pulse' => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
		'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
		'wifi' => '<path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1"/>',
		'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
		'tunnel' => '<path d="M4 20V11a8 8 0 0 1 16 0v9"/><path d="M8 20v-8a4 4 0 0 1 8 0v8"/>',
		'check' => '<path d="M5 12l5 5 9-10"/>',
		'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
		'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
		. (isset($p[$name]) ? $p[$name] : '') . '</svg>';
}

/** The hero picture: a real screenshot of the customer dashboard in a browser frame. */
function v_mockup()
{
	$base = e(mkl_site('base_domain'));
	return '<div class="mock">'
		. '<div class="mk-win"><div class="mk-bar-top" aria-hidden="true"><i></i><i></i><i></i><span>rahimnet.' . $base . '</span></div>'
		. '<picture><source type="image/webp" srcset="assets/dashboard.webp"><img class="mk-shot" src="assets/dashboard.jpg" width="1600" height="950" '
		. 'fetchpriority="high" alt="' . e(t('m_alt')) . '"></picture></div>'
		. '<div class="mk-float f1" aria-hidden="true">' . v_icon('tunnel') . '<div><b>' . e(t('m_tunnel')) . '</b><span>Market-Shop · WireGuard</span></div></div>'
		. '<div class="mk-float f2" aria-hidden="true">' . v_icon('ticket') . '<div><b>' . e(t('m_printed')) . '</b><span>7 Days · QR</span></div></div>'
		. '</div>';
}

function v_pricing_cards($years_default = 1)
{
	$pk = mkp_packages();
	$trial = (int)mkl_site('trial_days');
	$cards = '';
	foreach ($pk as $p) {
		$prices = array();
		foreach (array(1, 2, 3) as $y) {
			$total = mkp_price($p, $y);
			$prices[$y] = array('year' => mkp_taka(round($total / $y)), 'month' => mkp_taka(round($total / $y / 12)), 'total' => mkp_taka($total));
		}
		$routers = t($p['routers'] == 1 ? 'pkg_routers' : 'pkg_routers_n', array($p['routers']));
		$cards .= '<div class="plan' . ($p['popular'] ? ' pop' : '') . '" data-prices=\'' . e(json_encode($prices, JSON_UNESCAPED_UNICODE)) . '\'>'
			. ($p['popular'] ? '<span class="plan-badge">' . e(t('pkg_popular')) . '</span>' : '')
			. '<h3>' . e(mkp_package_name($p)) . '</h3><p class="plan-for">' . e(t('pkg_' . $p['key'] . '_for')) . '</p>'
			. '<div class="plan-price"><strong data-k="year">' . e($prices[1]['year']) . '</strong><span>' . e(t('per_year')) . '</span></div>'
			. '<p class="plan-sub"><span data-k="month">' . e(t('per_month', array($prices[1]['month']))) . '</span>'
			. '<span data-k="total" hidden>' . e($prices[1]['total']) . '</span></p>'
			. '<ul><li class="big">' . v_icon('wifi') . e($routers) . '</li>'
			. '<li>' . v_icon('check') . e(t('pkg_all')) . '</li>'
			. '<li>' . v_icon('check') . e(t('pkg_unlimited')) . '</li>'
			. '<li>' . v_icon('check') . e(t('pkg_tunnel')) . '</li>'
			. '<li>' . v_icon('check') . e(t('pkg_' . $p['key'] . '_support')) . '</li></ul>'
			. '<a class="lbtn ' . ($p['popular'] ? 'solid' : 'ghost') . '" href="' . e(mkp_url('register', array('plan' => $p['key']))) . '">'
			. e($trial > 0 ? t('pkg_choose') : t('pkg_buy', array(mkp_package_name($p)))) . '</a></div>';
	}
	$tabs = '';
	foreach (array(1, 2, 3) as $y) {
		$d = mkp_discount($y);
		$tabs .= '<button type="button" data-y="' . $y . '"' . ($y == $years_default ? ' class="on"' : '') . '>' . e(t('year_' . $y))
			. ($d > 0 ? ' <em>' . e(t('save_pct', array($d))) . '</em>' : '') . '</button>';
	}
	$max = 0;
	foreach ($pk as $p) {
		$max = max($max, $p['routers']);
	}
	return '<div class="yr-tabs" role="tablist">' . $tabs . '</div><div class="plans">' . $cards . '</div>'
		. (mkl_site('support_contact') != '' ? '<p class="plan-more">' . e(t('pkg_more', array($max, mkl_site('support_contact')))) . '</p>' : '');
}

function v_landing()
{
	$brand = e(mkl_site('brand'));
	$base = e(mkl_site('base_domain'));
	$trial = (int)mkl_site('trial_days');
	$other = mkp_lang() == 'bn' ? array('en', 'English') : array('bn', 'বাংলা');
	require_once(__DIR__ . '/seo.php');
	$start = e(mkp_url('register'));

	$feats = array(array('ticket', 'f1'), array('clock', 'f2'), array('chart', 'f3'), array('pulse', 'f4'), array('tunnel', 'f5'),
		array('layers', 'f6'), array('wifi', 'f7'), array('lock', 'f8'), array('bell', 'f9'));
	$fh = '';
	foreach ($feats as $f) {
		$fh .= '<div class="feat">' . v_icon($f[0]) . '<h3>' . e(t($f[1])) . '</h3><p>' . e(t($f[1] . 't')) . '</p></div>';
	}

	$cmp = '';
	$rows = array(array('c1', 'x', ''), array('c2', 'c_sometimes', ''), array('c3', 'x', ''), array('c4', 'x', ''), array('c5', 'c_stopped', 'c_included'), array('c6', 'x', ''));
	foreach ($rows as $r) {
		$self = $r[1] == 'x' ? '<span class="no">' . v_icon('x') . '</span>' : '<span class="mid">' . e(t($r[1])) . '</span>';
		$live = $r[2] == '' ? '<span class="yes">' . v_icon('check') . '</span>' : '<span class="yes txt">' . v_icon('check') . e(t($r[2])) . '</span>';
		$cmp .= '<tr><th scope="row">' . e(t($r[0])) . '</th><td>' . $self . '</td><td>' . $live . '</td></tr>';
	}

	$faq = '';
	for ($i = 1; $i <= 8; $i++) {
		$faq .= '<details' . ($i == 1 ? ' open' : '') . '><summary><span>' . e(t('q' . $i)) . '</span></summary><p>' . e(t('a' . $i, array($trial))) . '</p></details>';
	}

	$why = '<div class="why-card old"><h4>' . e(t('l_why_old')) . '</h4><ul>';
	foreach (array('l_why_o1', 'l_why_o2', 'l_why_o3') as $k) {
		$why .= '<li>' . v_icon('x') . e(t($k)) . '</li>';
	}
	$why .= '</ul></div><div class="why-card new"><h4>' . e(t('l_why_new')) . '</h4><ul>';
	foreach (array('l_why_n1', 'l_why_n2', 'l_why_n3') as $k) {
		$why .= '<li>' . v_icon('check') . e(t($k)) . '</li>';
	}
	$why .= '</ul></div>';

	$cta = $trial > 0 ? t('l_cta_trial', array($trial)) : t('l_cta_start');

	return '<!DOCTYPE html><html lang="' . mkp_lang() . '"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. mkp_seo_head()
		. '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
		. '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
		. '<link rel="stylesheet" href="assets/landing.css?v=1"></head><body class="lp ' . mkp_lang() . '">'

		// navigation
		. '<header class="lnav"><div class="wrap"><a class="logo" href="./' . (mkp_lang() == 'bn' ? '?hl=bn' : '') . '" aria-label="' . $brand . '">' . v_icon('wifi') . $brand . '</a>'
		. '<nav><a href="#features">' . e(t('l_nav_features')) . '</a><a href="#how">' . e(t('l_nav_how')) . '</a><a href="#pricing">' . e(t('l_nav_pricing')) . '</a><a href="#faq">' . e(t('l_nav_faq')) . '</a></nav>'
		. '<div class="lnav-r"><a class="lang" href="./?hl=' . $other[0] . '" hreflang="' . $other[0] . '" lang="' . $other[0] . '">' . $other[1] . '</a>'
		. '<a class="login" href="' . e(mkp_url('login')) . '">' . e(t('nav_login')) . '</a><a class="lbtn solid sm" href="' . $start . '">' . e(t('l_start')) . '</a></div></div></header>'

		. '<main id="main">'
		// hero
		. '<section class="hero"><div class="glow g1"></div><div class="glow g2"></div><div class="wrap hero-in"><div class="hero-copy">'
		. '<span class="kick">' . v_icon('check') . e(t('l_badge')) . '</span>'
		. '<h1>' . e(t('l_title_1')) . ' <span class="grad">' . e(t('l_title_2')) . '</span></h1>'
		. '<p class="lead">' . e(t('l_sub')) . '</p>'
		. '<div class="hero-cta"><a class="lbtn solid lg" href="' . $start . '">' . e($cta) . v_icon('arrow') . '</a><a class="lbtn ghost lg" href="#pricing">' . e(t('l_cta_pricing')) . '</a></div>'
		. ($trial > 0 ? '<p class="note">' . e(t('l_no_card')) . '</p>' : '')
		. '<div class="chips"><span>' . v_icon('check') . e(t('l_chip_1')) . '</span><span>' . v_icon('check') . e(t('l_chip_2')) . '</span><span>' . v_icon('check') . e(t('l_chip_3')) . '</span></div>'
		. '</div>' . v_mockup() . '</div></section>'

		// compatibility strip
		. '<section class="strip"><div class="wrap"><p>' . e(t('l_works')) . '</p><div class="strip-row">'
		. '<span>RouterOS v6.x</span><span>RouterOS v7.0 – 7.9</span><span class="hi">RouterOS v7.10+</span><span>hAP</span><span>hEX</span><span>RB / CCR</span><span>CHR</span></div></div></section>'

		// why
		. '<section class="why"><div class="wrap why-in"><div><span class="kicker">' . e(t('l_why_kicker')) . '</span><h2>' . e(t('l_why_title')) . '</h2><p>' . e(t('l_why_text')) . '</p></div>'
		. '<div class="why-cards">' . $why . '</div></div></section>'

		// features
		. '<section class="sec" id="features"><div class="wrap"><div class="sec-head"><span class="kicker">' . e(t('l_feat_kicker')) . '</span><h2>' . e(t('l_feat_title')) . '</h2></div>'
		. '<div class="feats">' . $fh . '</div></div></section>'

		// how it works
		. '<section class="sec how" id="how"><div class="wrap"><div class="sec-head"><span class="kicker">' . e(t('l_how_kicker')) . '</span><h2>' . e(t('l_how_title')) . '</h2></div><ol class="steps">'
		. '<li><span>1</span><h3>' . e(t('h1')) . '</h3><p>' . e(t('h1t', array(mkl_site('base_domain')))) . '</p></li>'
		. '<li><span>2</span><h3>' . e(t('h2')) . '</h3><p>' . e(t('h2t')) . '</p><pre>/interface wireguard add name=mikhmonlive …</pre></li>'
		. '<li><span>3</span><h3>' . e(t('h3')) . '</h3><p>' . e(t('h3t')) . '</p></li></ol></div></section>'

		// pricing
		. '<section class="sec pricing" id="pricing"><div class="wrap"><div class="sec-head"><span class="kicker">' . e(t('l_price_kicker')) . '</span><h2>' . e(t('l_price_title')) . '</h2><p>' . e(t('l_price_sub')) . '</p></div>'
		. v_pricing_cards() . '</div></section>'

		// comparison
		. '<section class="sec cmp"><div class="wrap"><div class="sec-head"><h2>' . e(t('l_cmp_title')) . '</h2></div><div class="cmp-box"><table>'
		. '<thead><tr><th></th><th>' . e(t('l_cmp_self')) . '</th><th class="me">' . $brand . '</th></tr></thead><tbody>' . $cmp . '</tbody></table></div></div></section>'

		// faq
		. '<section class="sec faq" id="faq"><div class="wrap faq-in"><div class="sec-head left"><h2>' . e(t('l_faq_title')) . '</h2>'
		. (mkl_site('support_contact') != '' ? '<p>' . e(mkl_site('support_contact')) . '</p>' : '') . '</div><div class="faq-list">' . $faq . '</div></div></section>'

		// final call to action
		. '<section class="final"><div class="wrap final-in"><h2>' . e(t('l_final_title')) . '</h2><p>' . e(t('l_final_text', array(mkl_site('base_domain')))) . '</p>'
		. '<a class="lbtn light lg" href="' . $start . '">' . e($cta) . v_icon('arrow') . '</a></div></section>'

		. '</main>'
		. '<footer class="lfoot"><div class="wrap"><div class="logo">' . v_icon('wifi') . $brand . '</div><p>' . e(t('l_footer')) . '</p>'
		. '<p class="links"><a href="' . e(mkp_url('login')) . '">' . e(t('nav_login')) . '</a><a href="' . $start . '">' . e(t('nav_register')) . '</a>'
		. (mkl_site('support_contact') != '' ? '<a href="mailto:' . e(mkl_site('support_contact')) . '">' . e(mkl_site('support_contact')) . '</a>' : '') . '</p></div></footer>'
		. v_pricing_js() . '</body></html>';
}

/** Switches the prices of the plan cards between 1, 2 and 3 years. */
function v_pricing_js()
{
	return '<script>(function(){var tabs=document.querySelectorAll(".yr-tabs button");var per=' . json_encode(t('per_month', array('%m'))) . ',bill=' . json_encode(t('billed', array('%t', '%y'))) . ';'
		. 'var yl={1:' . json_encode(t('year_1')) . ',2:' . json_encode(t('year_2')) . ',3:' . json_encode(t('year_3')) . '};'
		. 'function set(y){tabs.forEach(function(b){b.classList.toggle("on",b.getAttribute("data-y")==y)});'
		. 'document.querySelectorAll(".plan").forEach(function(c){var p=JSON.parse(c.getAttribute("data-prices"))[y];'
		. 'c.querySelector("[data-k=year]").textContent=p.year;var m=c.querySelector("[data-k=month]"),t=c.querySelector("[data-k=total]");'
		. 'm.textContent=per.replace("%m",p.month);if(y>1){t.hidden=false;t.textContent=" · "+bill.replace("%t",p.total).replace("%y",yl[y])}else{t.hidden=true}})}'
		. 'tabs.forEach(function(b){b.addEventListener("click",function(){set(b.getAttribute("data-y"))})});})();</script>';
}
