<?php
require_once(__DIR__ . '/../include/bootstrap.php');
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){
  header("Location:../admin.php?id=login");
}else{

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
    
    
    if(isset($_POST['id'])){
    
            $id = $_POST['id'];
            $status = $_POST['status'];
            $enable_server = $status == 'true' ? 'enable':'disable'; 
            $gethotspotactive = $API->comm("/ip/hotspot/$enable_server",
        array('.id'=>$id));

            $result = 0;

            if(count($gethotspotactive) < 1){
                $result =1;
            }

            header("Content-type: application/json; charset=utf-8");

            echo json_encode(['result'=>$result,'action'=>$enable_server]);
            exit();
    }
  
}