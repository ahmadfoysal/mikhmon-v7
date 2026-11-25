<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
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
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
// Clear everything that keeps a voucher bound to a device:
// hotspot cookies, active sessions and the MAC lock.
require_once(__DIR__ . '/../include/bootstrap.php');
// hide all error
error_reporting(0);

$getuname = $API->comm("/ip/hotspot/user/print", array(
	"?.id" => "$clearuserdata",
));
$name = $getuname[0]['name'];

if ($name != "") {
	// remove all cookies of this user
	$getcookie = $API->comm("/ip/hotspot/cookie/print", array(
		"?user" => "$name",
	));
	foreach ((array)$getcookie as $ck) {
		$API->comm("/ip/hotspot/cookie/remove", array(".id" => $ck['.id']));
	}

	// remove all active sessions of this user
	$getactive = $API->comm("/ip/hotspot/active/print", array(
		"?user" => "$name",
	));
	foreach ((array)$getactive as $ac) {
		$API->comm("/ip/hotspot/active/remove", array(".id" => $ac['.id']));
	}

	// remove MAC lock
	$API->comm("/ip/hotspot/user/set", array(
		".id" => "$clearuserdata",
		"mac-address" => "00:00:00:00:00:00",
	));
}

if ($_SESSION['ubp'] != "") {
	echo "<script>window.location='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'</script>";
} elseif ($_SESSION['ubc'] != "") {
	echo "<script>window.location='./?hotspot=users&comment=" . $_SESSION['ubc'] . "&session=" . $session . "'</script>";
} else {
	echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
}
