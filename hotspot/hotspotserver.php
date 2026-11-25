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
// Hotspot server list with enable / disable switch.
require_once(__DIR__ . '/../include/bootstrap.php');
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {
	$gethotspotserverlist = $API->comm("/ip/hotspot/print");
	$TotalReg = count((array)$gethotspotserverlist);
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class="fa fa-server"></i> <?= $_hotspot_server ?></h3>
</div>
<div class="card-body overflow">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th>Name</th>
    <th>Interface</th>
    <th>Address Pool</th>
    <th>Profile</th>
    <th>Idle Timeout</th>
    <th>Addresses Per Mac</th>
    <th>Status</th>
  </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$hs = $gethotspotserverlist[$i];
	$hsid = htmlspecialchars($hs['.id'], ENT_QUOTES);
	$hsname = htmlspecialchars($hs['name'], ENT_QUOTES);
	$isdisabled = ($hs['disabled'] == "true");
	echo "<tr>";
	echo "<td>" . $hsname . "</td>";
	echo "<td>" . htmlspecialchars($hs['interface']) . "</td>";
	echo "<td>" . htmlspecialchars($hs['address-pool']) . "</td>";
	echo "<td>" . htmlspecialchars($hs['profile']) . "</td>";
	echo "<td>" . htmlspecialchars($hs['idle-timeout']) . "</td>";
	echo "<td>" . htmlspecialchars($hs['addresses-per-mac']) . "</td>";
	echo "<td><select class='form-control' data-id='" . $hsid . "' data-name='" . $hsname . "' onchange='changeServerStatus(this)'>";
	echo "<option value='true'" . ($isdisabled ? "" : " selected") . ">Enabled</option>";
	echo "<option value='false'" . ($isdisabled ? " selected" : "") . ">Disabled</option>";
	echo "</select></td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
<script>
function changeServerStatus(el){
  $.ajax({
    url: "./hotspot/ajaxaction.php?session=<?= $session ?>",
    method: "POST",
    data: { id: el.getAttribute('data-id'), status: el.value },
    dataType: "json",
    success: function(data){
      if (data.result > 0) {
        alert('Server ' + el.getAttribute('data-name') + ': ' + data.action + ' OK');
      } else {
        alert('Failed to change server status');
        window.location.reload();
      }
    },
    error: function(){ alert('Failed to change server status'); }
  });
}
</script>
</div>
</div>
</div>
</div>
