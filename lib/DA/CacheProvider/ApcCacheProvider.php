<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CacheProvider.php';

/**
 * Simple APC Cache provider
 * 
 * @see CacheProviderInterface.php for more information 
 * 
 */
class Mobi_Mtld_DA_CacheProvider_ApcCacheProvider extends Mobi_Mtld_DA_CacheProvider_CacheProvider {
	
	private $cacheItemExpiry; // number of seconds
	
	public function __construct($cacheItemExpiry = 86400) {
		$this->cacheItemExpiry = $cacheItemExpiry;
	}

	public function get($key){
		$hash = $this->_hash($key);
		if (apc_exists($hash)){
			return json_decode(apc_fetch($hash), true);
		}
		return null;
	}

	public function set($key, $value){
		$hash = $this->_hash($key);
		if (!apc_store($hash, json_encode($value), $this->cacheItemExpiry)){
			throw new Exception('Failed to save data in the APC data store');
		}
		return true;
	}

	public function delete($key){
		$hash = $this->_hash($key);
		return apc_delete($hash);
	}
	
    public function clear(){
		return apc_clear_cache('user');
	}
}
