<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CacheProvider.php';

/**
 * SocketServerProvider provider prototype
 * 
 * @see CacheProviderInterface.php for more information 
 * 
 */
class Mobi_Mtld_DA_CacheProvider_SocketServerProvider extends Mobi_Mtld_DA_CacheProvider_CacheProvider {
	
	private $cacheItemExpiry; // number of seconds
	private $socketHost;
	private $socketPort;
	private $socketResource;
	
	public function __construct($cacheItemExpiry = 86400, $socketHost = 'localhost', $socketPort = 8484) {
		$this->cacheItemExpiry = $cacheItemExpiry;
		$this->socketHost = $socketHost;
		$this->socketPort = (int) $socketPort;
	}

	public function get($key) {
		return $this->_sendSocketRequest($key);
	}

	private function _sendSocketRequest($userAgent, $propertyName = '') {
		$this->_getCleanSocket();
		fwrite($this->socketResource, "{$userAgent}\n");
		$response = '';
		while (!feof($this->socketResource)) {
			$response .= fgets($this->socketResource);
		}
		return json_decode(trim($response), TRUE);
	}

	private function _getCleanSocket() {
		if (is_null($this->socketResource) || feof($this->socketResource)) {
			$errorNo = NULL;
			$errorMsg = NULL;
			try {
				$this->socketResource = @fsockopen($this->socketHost, $this->socketPort, $errorNo, $errorMsg, 1);
			} catch (Exception $e) {
				throw new Mobi_Mtld_DA_Exception_SocketException("Error opening socket: " . $e->getMessage(), $e->getCode());
			}
			if (!$this->socketResource) {
				throw new Mobi_Mtld_DA_Exception_SocketException("Unable to load socket: [$errorNo] $errorMsg", $errorNo);
			}
		}
	}

	public function set($key, $value){
		return true;
	}

	public function delete($key){
		return true;
	}
	
    public function clear(){
		return true;
	}
}
