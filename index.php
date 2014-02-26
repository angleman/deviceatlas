<?php
include 'lib/DA/Api.php';
$no_cache = true;
$tree_file = $argv[1];

$s = microtime(true);
$tree = 0;

$memcache_enabled = extension_loaded("memcache");
$no_cache = array_key_exists("nocache", $_GET);
if ($memcache_enabled && !$no_cache) {
  $memcache = new Memcache;
  $memcache->connect('localhost', 11211);
  $tree = $memcache->get('tree');
}
 
if (!is_array($tree)) {
	try {
		$tree = Mobi_Mtld_DA_Api::getTreeFromFile($tree_file);
	} catch (Mobi_Mtld_Da_Exception_JsonException $e) {
		die($e->getMessage());
	}

	if ($memcache_enabled && !$no_cache) {
		$memcache->set('tree', $tree, false, 10);
	}
}
 
if ($memcache_enabled && !$no_cache) {
  $memcache->close();
}


$results = array();
for ($i=2; $i<$argc; $i++) {
	$user_agent = trim($argv[$i]);
	$properties = Mobi_Mtld_DA_Api::getPropertiesAsTyped($tree, $user_agent);
 
	$e = microtime(true);

	$properties['_detectTime'] = floor(($e - $s)*1000);

	$s = microtime(true);
	
	$results[$user_agent] = $properties;
}


$json = json_encode($results);
echo $json;
?>