<?php
/*
 *  MikhmonLive universal engine.
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
 *  Why this file exists
 * ---------------------------------------------------------------------------
 *  RouterOS up to v7.9 prints dates as  "jan/14/2024".
 *  RouterOS v7.10 and newer print them as "2024-01-14".
 *
 *  Mikhmon stores everything on the router (expiry date in the user comment,
 *  sales in /system script) and cuts those dates by character position, so a
 *  format change breaks expiry, the expiry monitor and the reports.
 *
 *  The rule used here:
 *    - Everything Mikhmon WRITES uses the classic format "jan/14/2024 10:20:30"
 *      on every RouterOS version (the router scripts convert when needed).
 *    - Everything Mikhmon READS is accepted in both formats, so data written
 *      by older forks on v7.10+ keeps working.
 *
 *  All router scripts and all date handling live in this one file.
 */

if (!defined('MKL_ENGINE')) {

	// Bump when the generated router scripts change. Profiles carrying an
	// older marker (or none) are offered for repair in the profile list.
	define('MKL_ENGINE', 'mikhmonlive-2');

	define('MKL_MONTHS', 'janfebmaraprmayjunjulaugsepoctnovdec');

	/* ------------------------------------------------------------------ */
	/*  Date helpers (PHP side)                                            */
	/* ------------------------------------------------------------------ */

	/** Month number (1-12) from a 3 letter name, 0 if unknown. */
	function mkl_month_num($mon)
	{
		$p = strpos(MKL_MONTHS, strtolower((string)$mon));
		return ($p === false || $p % 3 !== 0 || strlen((string)$mon) !== 3) ? 0 : (int)($p / 3) + 1;
	}

	/** 3 letter month name from a number, "" if out of range. */
	function mkl_month_name($num)
	{
		$num = (int)$num;
		return ($num < 1 || $num > 12) ? '' : substr(MKL_MONTHS, ($num - 1) * 3, 3);
	}

	/**
	 * Split a router date in either format.
	 * @return array|null  array('y' => 2024, 'm' => 1, 'd' => 14)
	 */
	function mkl_parse_date($s)
	{
		$s = trim((string)$s);
		if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $s, $m)) {
			return array('y' => (int)$m[1], 'm' => (int)$m[2], 'd' => (int)$m[3]);
		}
		if (preg_match('~^([A-Za-z]{3})/(\d{2})/(\d{4})$~', $s, $m) && mkl_month_num($m[1])) {
			return array('y' => (int)$m[3], 'm' => mkl_month_num($m[1]), 'd' => (int)$m[2]);
		}
		return null;
	}

	/** "2024-01-14" -> "jan/14/2024". Anything else is returned unchanged. */
	function mkl_date_legacy($s)
	{
		$d = mkl_parse_date($s);
		return $d ? sprintf('%s/%02d/%04d', mkl_month_name($d['m']), $d['d'], $d['y']) : (string)$s;
	}

	/** "jan/14/2024" -> "2024-01-14". Anything else is returned unchanged. */
	function mkl_date_iso($s)
	{
		$d = mkl_parse_date($s);
		return $d ? sprintf('%04d-%02d-%02d', $d['y'], $d['m'], $d['d']) : (string)$s;
	}

	/**
	 * Convert a user comment that starts with an ISO expiry
	 * ("2024-01-14 10:20:30 note") to the classic form
	 * ("jan/14/2024 10:20:30 note"). Other comments are returned unchanged.
	 */
	function mkl_comment_legacy($c)
	{
		$c = (string)$c;
		if (preg_match('~^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(.*)$~s', $c, $m)) {
			return mkl_date_legacy($m[1]) . ' ' . $m[2] . $m[3];
		}
		return $c;
	}

	/** True when the comment starts with an expiry date in either format. */
	function mkl_comment_has_expiry($c)
	{
		$c = mkl_comment_legacy($c);
		return (substr($c, 3, 1) == '/' && substr($c, 6, 1) == '/');
	}

	/* ------------------------------------------------------------------ */
	/*  Sales report storage (/system script)                              */
	/* ------------------------------------------------------------------ */

	/** Day key "jan/14/2024" -> every value the "source" field may hold. */
	function mkl_report_day_keys($idhr)
	{
		$keys = array((string)$idhr);
		$d = mkl_parse_date($idhr);
		if ($d) {
			$keys = array(mkl_date_legacy($idhr), mkl_date_iso($idhr));
		}
		return array_values(array_unique($keys));
	}

	/** Month key "jan2024" -> every value the "owner" field may hold. */
	function mkl_report_month_keys($idbl)
	{
		$idbl = (string)$idbl;
		$keys = array($idbl);
		if (preg_match('~^([A-Za-z]{3})(\d{4})$~', $idbl, $m) && mkl_month_num($m[1])) {
			$keys = array(strtolower($m[1]) . $m[2], sprintf('%02d%s', mkl_month_num($m[1]), $m[2]));
		} elseif (preg_match('~^(\d{2})(\d{4})$~', $idbl, $m) && mkl_month_name($m[1])) {
			$keys = array(mkl_month_name($m[1]) . $m[2], $idbl);
		}
		return array_values(array_unique($keys));
	}

	/**
	 * Read sales records.
	 *
	 * @param object $API     connected RouterosAPI
	 * @param string $mode    "day" | "month" | "all"
	 * @param string $key     "jan/14/2024" for day, "jan2024" for month
	 * @param bool   $idsOnly fetch only the .id column (for removal)
	 * @return array rows; the date at the start of every "name" is normalised
	 *               to the classic format so the report pages need one format.
	 */
	function mkl_report_fetch($API, $mode, $key = '', $idsOnly = false)
	{
		if ($mode == 'day') {
			$prop = 'source';
			$keys = mkl_report_day_keys($key);
		} elseif ($mode == 'month') {
			$prop = 'owner';
			$keys = mkl_report_month_keys($key);
		} else {
			$prop = 'comment';
			$keys = array('mikhmon');
		}

		$words = array();
		foreach ($keys as $k) {
			$words[] = '?' . $prop . '=' . $k;
		}
		for ($i = 1; $i < count($keys); $i++) {
			$words[] = '?#|';
		}
		if ($idsOnly) {
			$words[] = '=.proplist=.id';
		}

		$API->write('/system/script/print', false);
		$last = count($words) - 1;
		foreach ($words as $n => $w) {
			$API->write($w, $n == $last);
		}
		$rows = $API->read();
		if (!is_array($rows)) {
			return array();
		}

		$out = array();
		foreach ($rows as $row) {
			if (!is_array($row) || !isset($row['.id'])) {
				continue;
			}
			if (isset($row['name'])) {
				$parts = explode('-|-', $row['name'], 2);
				if (count($parts) == 2) {
					$row['name'] = mkl_date_legacy($parts[0]) . '-|-' . $parts[1];
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/** Remove the sales records of one day or one month. */
	function mkl_report_remove($API, $mode, $key)
	{
		$rows = mkl_report_fetch($API, $mode, $key, true);
		foreach ($rows as $row) {
			$API->write('/system/script/remove', false);
			$API->write('=.id=' . $row['.id']);
			$API->read();
		}
		return count($rows);
	}

	/** Numeric field of a sales record, safe for arithmetic on PHP 8. */
	function mkl_num($v)
	{
		return is_numeric($v) ? $v + 0 : 0;
	}

	/* ------------------------------------------------------------------ */
	/*  Router scripts                                                     */
	/* ------------------------------------------------------------------ */

	/** Remove characters that would break out of a quoted RouterOS string. */
	function mkl_script_safe($s)
	{
		return str_replace(array('"', '\\', '$', ';', "\r", "\n", '[', ']', '{', '}'), '', (string)$s);
	}

	/** Join a readable multi line script into the one line Mikhmon stores. */
	function mkl_script_join($lines)
	{
		$out = array();
		foreach (explode("\n", $lines) as $l) {
			$l = trim($l);
			if ($l !== '') {
				$out[] = $l;
			}
		}
		return implode(' ', $out);
	}

	/**
	 * Header every Mikhmon profile starts with. The pages read validity,
	 * price, selling price and lock from it by position, so the layout must
	 * never change.
	 */
	function mkl_onlogin_header($expmode, $price, $validity, $sprice, $lock)
	{
		return ':put (",' . $expmode . ',' . $price . ',' . $validity . ',' . $sprice . ',,' . $lock . ',");';
	}

	/**
	 * Parse the header back.
	 * @return array|null  expmode, price, validity, sprice, lock
	 */
	function mkl_onlogin_parse($onlogin)
	{
		$onlogin = (string)$onlogin;
		if (substr($onlogin, 0, 8) != ':put (",') {
			return null;
		}
		$f = explode(',', $onlogin);
		if (count($f) < 7) {
			return null;
		}
		return array(
			'expmode' => $f[1],
			'price' => $f[2],
			'validity' => $f[3],
			'sprice' => $f[4],
			'noexp' => ($f[5] == 'noexp'),
			'lock' => $f[6],
		);
	}

	/** True when a Mikhmon profile script was not generated by this engine. */
	function mkl_onlogin_outdated($onlogin)
	{
		$p = mkl_onlogin_parse($onlogin);
		if (!$p) {
			return false; // not a Mikhmon profile, leave it alone
		}
		if ($p['noexp'] || $p['expmode'] == '' || $p['expmode'] == '0') {
			return false; // no date handling inside, nothing to repair
		}
		return strpos((string)$onlogin, MKL_ENGINE) === false;
	}

	/**
	 * The on-login script of a user profile.
	 *
	 * @param string $expmode  rem | ntf | remc | ntfc | 0
	 * @param string $price    price (shown on vouchers, stored in reports)
	 * @param string $validity RouterOS interval, e.g. 1d, 12h, 30d
	 * @param string $sprice   selling price
	 * @param string $lock     "Enable" to lock the voucher to the first MAC
	 * @param string $name     profile name
	 */
	function mkl_onlogin_script($expmode, $price, $validity, $sprice, $lock, $name)
	{
		$price = mkl_script_safe($price);
		$sprice = mkl_script_safe($sprice);
		$validity = mkl_script_safe($validity);
		$name = mkl_script_safe($name);
		$lock = ($lock == 'Enable') ? 'Enable' : 'Disable';

		$lockcmd = ($lock == 'Enable')
			? ':local lmac $"mac-address"; /ip hotspot user set mac-address=$lmac [find where name="$user"];'
			: '';

		if (!in_array($expmode, array('rem', 'ntf', 'remc', 'ntfc'))) {
			if ($expmode == '0' && $price != '') {
				// no expiry: only price info and the optional MAC lock
				return trim(':put (",,' . $price . ',,,noexp,' . $lock . ',"); ' . $lockcmd);
			}
			return '';
		}

		$record = '';
		if ($expmode == 'remc' || $expmode == 'ntfc') {
			$record = '
				:do {
					:local mac $"mac-address";
					:local time [/system clock get time];
					/system script add name="$date-|-$time-|-$user-|-' . $price . '-|-$address-|-$mac-|-' . $validity . '-|-' . $name . '-|-$comment" owner="$month$year" source="$date" comment="mikhmon";
				} on-error={};';
		}

		// $date is always the classic "jan/14/2024" form, whatever the router prints.
		$body = '
			{
			:local mkv "' . MKL_ENGINE . '";
			:local ms "' . MKL_MONTHS . '";
			:local mkd do={
				:local s [:tostr $d];
				:if ([:pick $s 4 5] = "-") do={
					:local m [:tonum [:pick $s 5 7]];
					:return ([:pick $ms (($m - 1) * 3) ($m * 3)] . "/" . [:pick $s 8 10] . "/" . [:pick $s 0 4]);
				};
				:return $s;
			};
			:local comment [/ip hotspot user get [/ip hotspot user find where name="$user"] comment];
			:local ucode [:pick $comment 0 2];
			:if ($ucode = "vc" or $ucode = "up" or $comment = "") do={
				:local rdate [/system clock get date];
				:local date [$mkd d=$rdate ms=$ms];
				:local year [:pick $date 7 11];
				:local month [:pick $date 0 3];
				:local sn ("mkl-" . $user);
				/system scheduler remove [find where name=$sn];
				/system scheduler add name=$sn disabled=no start-date=$rdate interval="' . $validity . '";
				:delay 5s;
				:local nx [:tostr [/system scheduler get [/system scheduler find where name=$sn] next-run]];
				/system scheduler remove [find where name=$sn];
				:local l [:len $nx];
				:local exp $nx;
				:if ($l = 8) do={
					:set exp ($date . " " . $nx);
				};
				:if ($l = 15) do={
					:local y [:tonum $year];
					:if ([:find $ms [:pick $nx 0 3]] < [:find $ms $month]) do={ :set y ($y + 1); };
					:set exp ([:pick $nx 0 6] . "/" . $y . " " . [:pick $nx 7 15]);
				};
				:if ($l = 14) do={
					:local y [:tonum $year];
					:local m [:tonum [:pick $nx 0 2]];
					:if ($m < (([:find $ms $month] / 3) + 1)) do={ :set y ($y + 1); };
					:set exp ([:pick $ms (($m - 1) * 3) ($m * 3)] . "/" . [:pick $nx 3 5] . "/" . $y . " " . [:pick $nx 6 14]);
				};
				:if ($l = 19) do={
					:set exp ([$mkd d=[:pick $nx 0 10] ms=$ms] . " " . [:pick $nx 11 19]);
				};
				/ip hotspot user set comment=$exp [find where name="$user"];'
			. $record . '
				' . $lockcmd . '
			};
			}';

		return mkl_onlogin_header($expmode, $price, $validity, $sprice, $lock) . ' ' . mkl_script_join($body);
	}

	/** What the expiry monitor does with an expired user. */
	function mkl_monitor_mode($expmode)
	{
		if ($expmode == 'rem' || $expmode == 'remc') {
			return 'remove';
		}
		if ($expmode == 'ntf' || $expmode == 'ntfc') {
			return 'set limit-uptime=1s';
		}
		return '';
	}

	/**
	 * The scheduler script that expires the users of one profile.
	 * Reads the expiry from the user comment in the classic and the ISO format.
	 */
	function mkl_monitor_script($name, $expmode)
	{
		$name = mkl_script_safe($name);
		$mode = mkl_monitor_mode($expmode);
		if ($mode == '') {
			return '';
		}

		$body = '
			:local mkv "' . MKL_ENGINE . '";
			:local ms "' . MKL_MONTHS . '";
			:local dateint do={
				:local s [:tostr $d];
				:if ([:pick $s 4 5] = "-") do={
					:return [:tonum ([:pick $s 0 4] . [:pick $s 5 7] . [:pick $s 8 10])];
				};
				:local m (([:find $ms [:pick $s 0 3]] / 3) + 1);
				:local mm [:tostr $m];
				:if ($m < 10) do={ :set mm ("0" . $m); };
				:return [:tonum ([:pick $s 7 11] . $mm . [:pick $s 4 6])];
			};
			:local timeint do={
				:return (([:tonum [:pick $t 0 2]] * 60) + [:tonum [:pick $t 3 5]]);
			};
			:local date [/system clock get date];
			:local time [/system clock get time];
			:local today [$dateint d=$date ms=$ms];
			:local curtime [$timeint t=$time];
			:foreach i in=[/ip hotspot user find where profile="' . $name . '"] do={
				:local comment [/ip hotspot user get $i comment];
				:local uname [/ip hotspot user get $i name];
				:local dpart "";
				:local tpart "";
				:if ([:pick $comment 3] = "/" and [:pick $comment 6] = "/") do={
					:set dpart [:pick $comment 0 11];
					:set tpart [:pick $comment 12 20];
				};
				:if ([:pick $comment 4] = "-" and [:pick $comment 7] = "-" and [:pick $comment 13] = ":") do={
					:set dpart [:pick $comment 0 10];
					:set tpart [:pick $comment 11 19];
				};
				:if ($dpart != "") do={
					:local expd [$dateint d=$dpart ms=$ms];
					:local expt [$timeint t=$tpart];
					:if (($expd < $today) or (($expd = $today) and ($expt < $curtime))) do={
						/ip hotspot user ' . $mode . ' $i;
						/ip hotspot active remove [find where user=$uname];
					};
				};
			};';

		return mkl_script_join($body);
	}

	/**
	 * Create, update or remove the expiry monitor scheduler of a profile.
	 *
	 * @param object $API     connected RouterosAPI
	 * @param string $name    profile name
	 * @param string $expmode expired mode of the profile
	 * @param string $oldname previous profile name when it was renamed
	 */
	function mkl_monitor_sync($API, $name, $expmode, $oldname = '')
	{
		$find = ($oldname != '') ? $oldname : $name;
		$existing = $API->comm('/system/scheduler/print', array('?name' => "$find"));
		$monid = (is_array($existing) && isset($existing[0]['.id'])) ? $existing[0]['.id'] : '';

		$script = mkl_monitor_script($name, $expmode);
		if ($script == '') {
			if ($monid != '') {
				$API->comm('/system/scheduler/remove', array('.id' => "$monid"));
			}
			return;
		}

		$args = array(
			'name' => "$name",
			'start-time' => '0' . rand(1, 5) . ':' . rand(10, 59) . ':' . rand(10, 59),
			'interval' => '00:02:' . rand(10, 59),
			'on-event' => $script,
			'disabled' => 'no',
			'comment' => "Monitor Profile $name",
		);
		if ($monid == '') {
			$API->comm('/system/scheduler/add', $args);
		} else {
			$API->comm('/system/scheduler/set', array('.id' => "$monid") + $args);
		}
	}

	/**
	 * Rewrite the scripts of every Mikhmon profile on the router with the
	 * current engine. Price, validity, expired mode and lock are kept.
	 *
	 * @return array names of the profiles that were rewritten
	 */
	function mkl_repair_profiles($API)
	{
		$done = array();
		$profiles = $API->comm('/ip/hotspot/user/profile/print');
		if (!is_array($profiles)) {
			return $done;
		}
		foreach ($profiles as $p) {
			if (!isset($p['.id'], $p['name'], $p['on-login'])) {
				continue;
			}
			$h = mkl_onlogin_parse($p['on-login']);
			if (!$h || $h['noexp'] || !in_array($h['expmode'], array('rem', 'ntf', 'remc', 'ntfc'))) {
				continue;
			}
			$script = mkl_onlogin_script($h['expmode'], $h['price'], $h['validity'], $h['sprice'], $h['lock'], $p['name']);
			$API->comm('/ip/hotspot/user/profile/set', array(
				'.id' => $p['.id'],
				'on-login' => $script,
			));
			mkl_monitor_sync($API, $p['name'], $h['expmode']);
			$done[] = $p['name'];
		}
		return $done;
	}
}
