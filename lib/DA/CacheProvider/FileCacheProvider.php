<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CacheProvider.php';

/**
 * Simple FileCache provider
 * 
 * @see CacheProviderInterface.php for more information 
 * 
 */
class Mobi_Mtld_DA_CacheProvider_FileCacheProvider extends Mobi_Mtld_DA_CacheProvider_CacheProvider {

	private $cacheDir; // uses sys_get_temp_dir() by default
	private $cacheItemExpiry; // number of seconds

	public function __construct($cacheDir = null, $cacheItemExpiry = 86400) {
		$this->cacheItemExpiry = $cacheItemExpiry;
		if (is_null($cacheDir)){
			$this->cacheDir = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.__CLASS__;
		} else {
			$this->cacheDir = rtrim($cacheDir, '/\\');
		}
	}
	
	public function get($key){
		$path = $this->_cachePath($key);
		if(file_exists($path)) {
			$mtime = @filemtime($path);
			if($mtime + $this->cacheItemExpiry > time()) {
				return json_decode(@file_get_contents($path), true);
			}
		}
		return null;
	}

	public function set($key, $value){
		$path = $this->_cachePath($key, true);
		if(@file_put_contents($path, json_encode($value), LOCK_EX) === false) {
			throw new Exception('Unable to write cache file at '.$path);
		}
		return true;
	}
	
	public function delete($key){
		$path = $this->_cachePath($key);
		if(file_exists($path)) {
			@unlink($path);
		}
	}

    public function clear(){
		$this->_rmDirRecursive($this->cacheDir, true);
	}
	
	private function _rmDirRecursive($dir, $empty = false){

		// open the directory
		$handle = opendir($dir);
		while (FALSE !== ($item = readdir($handle))){
			if($item != '.' && $item != '..'){
				$path = $dir.DIRECTORY_SEPARATOR.$item;
				if(is_dir($path)){
					$this->_rmDirRecursive($path);
				} else {
					// check filename
					if(preg_match("/\.json$/", $path)){
						@unlink($path);
					}
				}
			}
		}

		// close the directory
		closedir($handle);

		if (!$empty){
			@rmdir($dir);
		}
	}

	private function _cachePath($key, $createDirectory = false){
		$path = $this->cacheDir.DIRECTORY_SEPARATOR;
		
		// Subdirectory
		$hash = $this->_hash($key);
		$fragLevel = 2;
		$fragSubDirLength = 2;
		for ($i = 0, $n = $fragLevel * $fragSubDirLength; $i < $n; $i += $fragSubDirLength){
			$path .= substr($hash, $i, $fragSubDirLength).DIRECTORY_SEPARATOR;
		}
		
		if ($createDirectory && !is_dir($path)){
			@mkdir($path, 0755, true);
		}
		
		return $path.substr($hash, $fragLevel * $fragSubDirLength).'.json';
	}
}
