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
session_start();
// hide all error
error_reporting(0);

// get user id
$getuname = $API->comm("/ip/hotspot/user/print", array(
	"?.id" => "$clearuserdata",
));
$name = $getuname[0]['name'];

// get cookie id
$getcookie = $API->comm("/ip/hotspot/cookie/print", array(
    "?user" => "$name",
));
$ck = $getcookie[0]['.id'];
$ck_sec = $getcookie[1]['.id'];
// remove cookie
$API->comm("/ip/hotspot/cookie/remove", array(
    ".id" => "$ck",
));
// remove 2nd cookie
if ($ck_sec !== NULL){ 
$API->comm("/ip/hotspot/cookie/remove", array(
    ".id" => "$ck",
));
}

// get user active
$getuser = $API->comm("/ip/hotspot/active/print", array(
    "?user" => "$name",
));
$acID = $getuser[0]['.id'];
// remove user active
$API->comm("/ip/hotspot/active/remove", array(
    ".id" => "$acID",
));

// remove Mac
$mac = "00:00:00:00:00:00";

$API->comm("/ip/hotspot/user/set", array(
	".id" 			=> "$clearuserdata",
	"mac-address" 	=> "$mac"
));
echo "<script>window.location='./?hotspot=users&profile=all&session=" . $session . "'</script>";
//echo '<script type="text/javascript">alert("'.$acID.'");</script>';


?>