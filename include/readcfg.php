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
// hide all error
error_reporting(0);
if (substr($_SERVER["REQUEST_URI"], -11) == "readcfg.php") {
    header("Location:./");
};
// read config (from the data folder of this customer, see lib/mkl_store.php)
require_once(__DIR__ . '/bootstrap.php');

$mklrouter = mkl_router(isset($session) ? $session : '');
$iphost = $mklrouter['host'];
$userhost = $mklrouter['user'];
// pages call decrypt($passwdhost); the stored value itself is AES encrypted
$passwdhost = encrypt($mklrouter['pass_plain']);
$hotspotname = $mklrouter['hotspotname'];
$dnsname = $mklrouter['dnsname'];
$currency = $mklrouter['currency'];
$areload = $mklrouter['areload'];
$iface = $mklrouter['iface'];
$infolp = $mklrouter['infolp'];
$idleto = $mklrouter['idleto'];
$sesname = isset($session) ? $session : '';
$useradm = mkl_admin_user();
$passadm = '';
$livereport = $mklrouter['livereport'];
$curency = $currency;

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);


