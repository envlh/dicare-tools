<?php

array_walk_recursive($_GET, function (&$val) { $val = trim($val); });
array_walk_recursive($_POST, function (&$val) { $val = trim($val); });

require_once __DIR__.'/../conf/conf.inc.php';

function __autoload($className) {
	require __DIR__.'/lib/'.$className.'.class.php';
}

?>