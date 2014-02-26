<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CacheProvider.php';

/**
 * Simple MemCache provider
 * 
 * @see CacheProviderInterface.php for more information 
 * 
 */
class Mobi_Mtld_DA_CacheProvider_MemCacheProvider extends Mobi_Mtld_DA_CacheProvider_CacheProvider {
	
	private $memcache;
	private $cacheItemExpiry; // number of seconds
	
	public function __construct($memcacheHost = 'localhost', $memcachePort = 11211, $pconnect = false, $cacheItemExpiry = 86400) {
		$this->cacheItemExpiry = $cacheItemExpiry;
		$this->memcache = new Memcache();
		if ($pconnect){
			// Persistent connection
			if (!@$this->memcache->pconnect($memcacheHost, $memcachePort)){
				throw new Exception ("Could open a persistent connection to memcache.");
			}
		} else {
			// Normal connection
			if (!@$this->memcache->connect($memcacheHost, $memcachePort)){
				throw new Exception ("Could open connection to memcache.");
			}
		}
	}

	public function get($key){
		$hash = $this->_hash($key);
		$value = $this->memcache->get($hash);
		if ($value != false){
			return json_decode($value, true);
		}
		return null;
	}

	public function set($key, $value){
		$hash = $this->_hash($key);
		if (!$this->memcache->set($hash, json_encode($value), false, $this->cacheItemExpiry)){
			throw new Exception('Failed to save data to the memcache server');
		}
		return true;
	}

	public function delete($key){
		$hash = $this->_hash($key);
		return $this->memcache->delete($hash);
	}
	
    public function clear(){
		return $this->memcache->flush();
	}
}
