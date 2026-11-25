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
// Rewrite the on-login script and the expiry monitor of every Mikhmon
// profile with the universal engine (works on every RouterOS date format).
require_once(__DIR__ . '/../include/bootstrap.php');
// hide all error
error_reporting(0);

$mkldone = mkl_repair_profiles($API);
$_SESSION[$session . 'mklrepaired'] = count($mkldone) > 0 ? implode(", ", $mkldone) : "nothing to update";

echo "<script>window.location='./?hotspot=user-profiles&session=" . $session . "'</script>";
