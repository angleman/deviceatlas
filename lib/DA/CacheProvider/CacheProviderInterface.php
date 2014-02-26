<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

interface Mobi_Mtld_DA_CacheProvider_CacheProviderInterface {
    
    /**
	 * Returns values for a given $key
	 * 
	 * NOTE: Must return null when there are no data!
	 */
	public function get($key);

	/**
	 * Stores an item in the repository
	 */
	public function set($key, $value);
	
	/**
	 * Removes an item from the repository
	 */
	public function delete($key);
	
	/**
	 * Clears the whole cache repository
	 */
	public function clear();
}
