<?php
// test helper: record fatal errors and warnings even though pages call error_reporting(0)
register_shutdown_function(function () {
	$e = error_get_last();
	if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
		file_put_contents(getenv('MKL_ERRLOG'), $_SERVER['REQUEST_URI'] . ' :: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND);
	}
});
