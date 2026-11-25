<?php
require_once(__DIR__ . '/bootstrap.php');
$langid = preg_replace('/[^a-z]/', '', mkl_pref('lang'));
if ($langid == '' || !is_file(__DIR__ . '/../lang/' . $langid . '.php')) {
	$langid = 'en';
}
