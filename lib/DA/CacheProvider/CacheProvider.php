<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CacheProviderInterface.php';

abstract class Mobi_Mtld_DA_CacheProvider_CacheProvider implements Mobi_Mtld_DA_CacheProvider_CacheProviderInterface {
    
	protected function _hash($string){
		return md5(__CLASS__.$string);
	}
}
