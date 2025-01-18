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
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

// load session MikroTik
	$session = $_GET['session'];
	//$serveractive = $_GET['server'];

    

// load config
	include('../include/config.php');
	include('../include/readcfg.php');
	
// lang
  include('../include/lang.php');
  include('../lang/'.$langid.'.php');

// routeros api
	include_once('../lib/routeros_api.class.php');
	include_once('../lib/formatbytesbites.php');
	$API = new RouterosAPI();
	$API->debug = false;
	$API->connect($iphost, $userhost, decrypt($passwdhost));

    /*

	if ($serveractive != "") {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print", array("?server" => "" . $serveractive . ""));
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "", "?server" => "" . $serveractive . ""
		));

	} else {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print");
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "",
		));
	}
    */

    $gethotspotserverlist = $API->comm("/ip/hotspot/print");
	$TotalReg = count($gethotspotserverlist);

    $counthotspotserverlist = $API->comm("/ip/hotspot/print", array(
        "count-only" => "",
    ));

    $status_array = array(
        "true"=>'Enabled',
        "false"=>'Disabled'
    );

    
}
?>
<div class="row">
<div id="reloadHotspotActive">
<div class="col-12">
	<div class="card">
		
         <div class="card-body overflow">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    
    <th>Name</th>
    <th>Interface</th>
    <th>Address Pool</th>
    <th>Profile</th>
    <th>IDLE-TIMEOUT</th> 
    <th>Addresses Per Mac</th>
    <th>Status</th>   
  </tr>
  </thead>
  <tbody>
<?php

for ($i = 0; $i < $TotalReg; $i++) {
	$hotspotserver = $gethotspotserverlist[$i];
	$id = $hotspotserver['.id'];
	$name = $hotspotserver['name'];
	$interface = $hotspotserver['interface'];
	$address_pool = $hotspotserver['address-pool'];
	$profile = $hotspotserver['profile'];
    $idle_timeout = $hotspotserver['idle-timeout'];
    $addresses_per_mac = $hotspotserver['addresses-per-mac'];
    $disabled = $hotspotserver['disabled'];
    echo "<tr>";
    //echo "<td>" . $id . "</td>";
    echo "<td>" . $name . "</td>";
    echo "<td>" . $interface . "</td>";
    echo "<td>" . $address_pool . "</td>";
    echo "<td>" . $profile . "</td>";
    echo "<td>" . $idle_timeout . "</td>";
    echo "<td>" . $addresses_per_mac . "</td>";
    $statuslist = "<select onchange=\"change_status('$name','$id',this)\" class='form-control'><option value=''>Select Status</option>";
    
    foreach($status_array as $k => $v){
        $statuslist.="<option value='$k' ".($k != $disabled ? "selected":"")." >$v</option>";
    }
    $statuslist.="</select>";
    echo "<td>$statuslist</td>";
    echo "</tr>";
}
	
//print_r($gethotspotserverlist)
?>
  </tbody>
</table>

<script>
var sessiondata = "<?= $session ?>";
function change_status(name, id, e){
    //alert(name+''+e.value)

    $.ajax({

    url:"./hotspot/ajaxaction.php?session="+sessiondata,
    method:"POST",
    data:{
        id:id,
        //name: name,
        status:e.value 
    },
    dataType:"JSON",
    success:function(data)
    {
        //alert(data.status)
        //window.location.reload()
        if(data.result > 0){
            alert('Server: '+name+', '+data.action+' succssfuly!');
        }
    }


    })

    
    
}
</script>
</div>
</div>
</div>
</div>
</div>