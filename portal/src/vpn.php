<?php
/*
 *  MikhmonLive portal - VPN onboarding.
 *
 *  Most hotspot routers sit behind NAT, so the cloud cannot reach their API.
 *  Each router dials in to the VPN server and gets a fixed private address:
 *
 *      RouterOS v7  ->  WireGuard        (vpn_wg_net)
 *      RouterOS v6  ->  L2TP over IPsec  (vpn_l2tp_net)
 *
 *  The portal only writes the wanted state to two files in the state folder:
 *
 *      wg-peers.conf   [Peer] blocks for WireGuard
 *      chap-secrets    one line per L2TP account
 *
 *  A small root service on the server (tools/vpn/sync.sh) applies them. The
 *  web user never needs root rights.
 *
 *  For every connection the customer gets one script to paste into the router
 *  terminal. It creates the tunnel and an API user that only the VPN server
 *  can use, and the router is added to the customer's Mikhmon automatically.
 */

if (!function_exists('mkp_vpn_enabled')) {

	function mkp_vpn_dir()
	{
		return mkl_site('vpn_state_dir') != '' ? rtrim(mkl_site('vpn_state_dir'), '/') : mkp_data_root() . '/vpn';
	}

	function mkp_vpn_file($name)
	{
		$f = mkp_vpn_dir() . '/' . $name;
		return is_file($f) ? trim((string)file_get_contents($f)) : '';
	}

	/** WireGuard public key of the server (written by tools/vpn/install.sh). */
	function mkp_vpn_server_pub()
	{
		return mkp_vpn_file('server.pub');
	}

	/** IPsec pre-shared key of the L2TP server (written by tools/vpn/install.sh). */
	function mkp_vpn_psk()
	{
		return mkp_vpn_file('l2tp.psk');
	}

	function mkp_vpn_enabled()
	{
		return mkl_site('vpn_host') != '' && (mkp_vpn_server_pub() != '' || mkp_vpn_psk() != '');
	}

	function mkp_vpn_types()
	{
		$t = array();
		if (mkp_vpn_server_pub() != '' && function_exists('sodium_crypto_scalarmult_base')) {
			$t[] = 'wg';
		}
		if (mkp_vpn_psk() != '') {
			$t[] = 'l2tp';
		}
		return $t;
	}

	function mkp_rand($len)
	{
		$abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$s = '';
		for ($i = 0; $i < $len; $i++) {
			$s .= $abc[random_int(0, strlen($abc) - 1)];
		}
		return $s;
	}

	/** New WireGuard key pair: array(private, public), both base64. */
	function mkp_wg_keypair()
	{
		$priv = random_bytes(32);
		$priv[0] = chr(ord($priv[0]) & 248);
		$priv[31] = chr((ord($priv[31]) & 127) | 64);
		return array(base64_encode($priv), base64_encode(sodium_crypto_scalarmult_base($priv)));
	}

	/** Lowest free address of a network, '' when it is full. */
	function mkp_vpn_alloc($cidr)
	{
		$parts = explode('/', $cidr);
		$net = ip2long($parts[0]);
		$bits = isset($parts[1]) ? (int)$parts[1] : 24;
		if ($net === false || $bits < 16 || $bits > 30) {
			return '';
		}
		$used = array(mkl_site('vpn_server_ip') => true);
		foreach (mkp_q('SELECT ip FROM vpn_peers') as $r) {
			$used[$r['ip']] = true;
		}
		$size = 1 << (32 - $bits);
		// the first 256 addresses are kept for the server side
		for ($i = 257; $i < $size - 1; $i++) {
			$ip = long2ip($net + $i);
			$last = ($net + $i) & 255;
			if ($last == 0 || $last == 255 || isset($used[$ip])) {
				continue;
			}
			return $ip;
		}
		return '';
	}

	function mkp_vpn_peers($user)
	{
		return mkp_q('SELECT * FROM vpn_peers WHERE user_id = ? ORDER BY id', array($user['id']))->fetchAll();
	}

	/**
	 * Create a VPN connection for one router of a customer.
	 * @return array array(peer id, '') or array(0, error text)
	 */
	function mkp_vpn_create($user, $name, $type)
	{
		$name = mkl_router_name($name);
		if ($name == '' || substr($name, 0, 4) == 'new-') {
			return array(0, t('err_vpn_name'));
		}
		if (!in_array($type, mkp_vpn_types(), true)) {
			return array(0, t('err_vpn_type'));
		}
		$dir = mkl_tenant_dir($user['tenant']);
		$sub = mkl_tenant_info($dir);
		$count = (int)mkp_row('SELECT COUNT(*) c FROM vpn_peers WHERE user_id = ?', array($user['id']))['c'];
		if ($sub['max_routers'] > 0 && $count >= $sub['max_routers']) {
			return array(0, t('err_vpn_limit'));
		}
		if (mkp_row('SELECT id FROM vpn_peers WHERE user_id = ? AND name = ?', array($user['id'], $name))) {
			return array(0, t('err_vpn_exists'));
		}

		$db = mkp_db();
		$db->exec('BEGIN IMMEDIATE');
		$ip = mkp_vpn_alloc($type == 'wg' ? mkl_site('vpn_wg_net') : mkl_site('vpn_l2tp_net'));
		if ($ip == '') {
			$db->exec('ROLLBACK');
			return array(0, t('err_vpn_full'));
		}
		$apiPass = mkp_rand(24);
		$pub = '';
		$pppUser = '';
		if ($type == 'wg') {
			list($priv, $pub) = mkp_wg_keypair();
			$secret = mkl_seal($priv);
		} else {
			$pppUser = 'ml' . strtolower(mkp_rand(10));
			$secret = mkl_seal(mkp_rand(24));
		}
		mkp_q('INSERT INTO vpn_peers (user_id, tenant, name, type, ip, wg_pub, secret, ppp_user, api_user, api_pass, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array($user['id'], $user['tenant'], $name, $type, $ip, $pub, $secret, $pppUser, 'mikhmonlive', mkl_seal($apiPass), mkp_now()));
		$id = (int)$db->lastInsertId();
		$db->exec('COMMIT');

		// this customer may now reach this address ...
		$ips = isset($sub['vpn_ips']) && is_array($sub['vpn_ips']) ? $sub['vpn_ips'] : array();
		$ips[] = $ip;
		mkl_tenant_write($user['tenant'], array('vpn_ips' => array_values(array_unique($ips))));

		// ... and the router appears in their Mikhmon, ready to connect
		$max = $sub['max_routers'];
		mkl_store_update(function ($cfg) use ($name, $ip, $apiPass, $max) {
			if (isset($cfg['routers'][$name]) || ($max > 0 && count($cfg['routers']) >= $max)) {
				return $cfg;
			}
			$cfg['routers'][$name] = array_merge(mkl_router_defaults(), array(
				'host' => $ip, 'user' => 'mikhmonlive', 'pass' => mkl_seal($apiPass), 'hotspotname' => $name,
				'currency' => mkl_site('currency'),
			));
			return $cfg;
		}, $dir);

		mkp_vpn_write_state();
		return array($id, '');
	}

	function mkp_vpn_delete($user, $peerId)
	{
		$p = mkp_row('SELECT * FROM vpn_peers WHERE id = ? AND user_id = ?', array((int)$peerId, $user['id']));
		if (!$p) {
			return false;
		}
		mkp_q('DELETE FROM vpn_peers WHERE id = ?', array($p['id']));
		$dir = mkl_tenant_dir($user['tenant']);
		$sub = mkl_tenant_info($dir);
		$ips = isset($sub['vpn_ips']) && is_array($sub['vpn_ips']) ? $sub['vpn_ips'] : array();
		mkl_tenant_write($user['tenant'], array('vpn_ips' => array_values(array_diff($ips, array($p['ip'])))));
		// drop the Mikhmon router that pointed at this tunnel (if the customer did not change it)
		$ip = $p['ip'];
		mkl_store_update(function ($cfg) use ($ip) {
			foreach ($cfg['routers'] as $n => $r) {
				if ($r['host'] === $ip) {
					unset($cfg['routers'][$n]);
				}
			}
			return $cfg;
		}, $dir);
		mkp_vpn_write_state();
		return true;
	}

	/**
	 * Write the wanted server state. Only connections of customers whose
	 * subscription is running are included, so an expired account also loses
	 * its tunnels.
	 */
	function mkp_vpn_write_state()
	{
		$dir = mkp_vpn_dir();
		if (!is_dir($dir)) {
			@mkdir($dir, 0750, true);
		}
		$wg = "# written by the MikhmonLive portal - do not edit\n";
		$ppp = "# written by the MikhmonLive portal - do not edit\n";
		$alive = array();
		foreach (mkp_q('SELECT * FROM vpn_peers ORDER BY id') as $p) {
			if (!isset($alive[$p['tenant']])) {
				$t = mkl_tenant_info(mkl_tenant_dir($p['tenant']));
				$alive[$p['tenant']] = $t['status'] == 'active' && ($t['expires'] == '' || $t['expires'] >= date('Y-m-d'));
			}
			if (!$alive[$p['tenant']]) {
				continue;
			}
			if ($p['type'] == 'wg') {
				$wg .= "\n[Peer]\n# " . $p['tenant'] . ' ' . $p['name'] . "\nPublicKey = " . $p['wg_pub'] . "\nAllowedIPs = " . $p['ip'] . "/32\n";
			} else {
				$ppp .= '"' . $p['ppp_user'] . '" * "' . mkl_open($p['secret']) . '" ' . $p['ip'] . "\n";
			}
		}
		foreach (array('wg-peers.conf' => $wg, 'chap-secrets' => $ppp) as $file => $text) {
			$tmp = $dir . '/.' . $file . '.tmp';
			if (file_put_contents($tmp, $text, LOCK_EX) !== false) {
				@chmod($tmp, 0640);
				rename($tmp, $dir . '/' . $file);
			}
		}
	}

	/** The script the customer pastes into the router terminal. */
	function mkp_vpn_script($p)
	{
		$host = mkl_site('vpn_host');
		$srv = mkl_site('vpn_server_ip');
		$apiPass = mkl_open($p['api_pass']);
		$s = "# MikhmonLive - " . $p['name'] . "\n";
		if ($p['type'] == 'wg') {
			$s .= "# RouterOS v7 (WireGuard). Paste everything into a New Terminal.\n"
				. '/interface wireguard add name=mikhmonlive private-key="' . mkl_open($p['secret']) . '" comment="MikhmonLive"' . "\n"
				. '/interface wireguard peers add interface=mikhmonlive public-key="' . mkp_vpn_server_pub() . '" endpoint-address=' . $host
				. ' endpoint-port=' . (int)mkl_site('vpn_wg_port') . ' allowed-address=' . $srv . '/32 persistent-keepalive=25s comment="MikhmonLive"' . "\n"
				. '/ip address add address=' . $p['ip'] . '/32 network=' . $srv . ' interface=mikhmonlive comment="MikhmonLive"' . "\n";
		} else {
			$s .= "# RouterOS v6 (L2TP/IPsec). Paste everything into a New Terminal.\n"
				. '/interface l2tp-client add name=mikhmonlive connect-to=' . $host . ' user="' . $p['ppp_user'] . '" password="' . mkl_open($p['secret'])
				. '" use-ipsec=yes ipsec-secret="' . mkp_vpn_psk() . '" add-default-route=no disabled=no comment="MikhmonLive"' . "\n";
		}
		$s .= '/user group add name=mikhmonlive policy=api,read,write,policy,test,sensitive,reboot comment="MikhmonLive"' . "\n"
			. '/user add name=' . $p['api_user'] . ' group=mikhmonlive password="' . $apiPass . '" address=' . $srv . '/32 comment="MikhmonLive"' . "\n"
			. '/ip service set api disabled=no' . "\n"
			. ':do { :local a [/ip service get api address]; :if ([:len $a] > 0) do={ /ip service set api address=($a , ' . $srv . '/32) } } on-error={}' . "\n"
			. ':do { /ip firewall filter add chain=input action=accept protocol=tcp dst-port=8728 src-address=' . $srv
			. ' in-interface=mikhmonlive comment="MikhmonLive API" place-before=0 } on-error={ /ip firewall filter add chain=input action=accept protocol=tcp dst-port=8728 src-address='
			. $srv . ' in-interface=mikhmonlive comment="MikhmonLive API" }' . "\n";
		return $s;
	}

	/** Script that removes everything the setup script created. */
	function mkp_vpn_unscript($p)
	{
		return "# MikhmonLive - remove " . $p['name'] . "\n"
			. '/ip firewall filter remove [find comment="MikhmonLive API"]' . "\n"
			. '/user remove [find comment="MikhmonLive"]' . "\n"
			. '/user group remove [find comment="MikhmonLive"]' . "\n"
			. '/ip address remove [find comment="MikhmonLive"]' . "\n"
			. ($p['type'] == 'wg'
				? '/interface wireguard peers remove [find comment="MikhmonLive"]' . "\n" . '/interface wireguard remove [find comment="MikhmonLive"]' . "\n"
				: '/interface l2tp-client remove [find comment="MikhmonLive"]' . "\n");
	}
}
