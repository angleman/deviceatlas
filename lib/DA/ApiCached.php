<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Api.php';
require_once Mobi_Mtld_DA_Path . 'TreeOptimizer.php';

// Basic cache providers
require_once Mobi_Mtld_DA_Path . 'CacheProvider' . DIRECTORY_SEPARATOR . 'ApcCacheProvider.php';
require_once Mobi_Mtld_DA_Path . 'CacheProvider' . DIRECTORY_SEPARATOR . 'FileCacheProvider.php';
require_once Mobi_Mtld_DA_Path . 'CacheProvider' . DIRECTORY_SEPARATOR . 'MemCacheProvider.php';

/**
 * This class significantly improves core DA PHP Api performance
 * when used on a standard website with a single detection per request.
 * 
 * Current optimizations consists of two main parts:
 * 
 * 1) tree optimizer - speeds up loading the json file<br/>
 * 2) cache provider - caches the results
 * 
 * <b>Typical usage:</b>
 * 
 * <code>
 * $ua = $_SERVER['HTTP_USER_AGENT'];
 * 
 * $da_cache_provider = new Mobi_Mtld_DA_CacheProvider_FileCacheProvider();
 * $da_api_cached = new Mobi_Mtld_DA_ApiCached("json/sample.json",
 *												$da_cache_provider);
 * 
 * $properties = $da_api_cached->getProperties($ua);
 * </code>
 * 
 * Client side properties
 * 
 * Client side properties can be collected and merged into the results by
 * using the DeviceAtlas Javascript detection file. The results from the client
 * side are sent to the server inside a cookie. The contents of this cookie can
 * be passed to the DeviceAtlas getProperty and getProperties methods. The 
 * client side properties over-ride any data file properties and also serve as
 * an input into additional logic to determine other properties such as the 
 * iPhone models that are otherwise not detectable. The following code shows
 * how this can be done:
 * 
 * <code>
 * $ua = $_SERVER['HTTP_USER_AGENT'];
 * 
 * // Get the cookie containing the client side properties
 * $cookie_contents = null;
 * if (isset($_COOKIE['DAPROPS'])){
 *   $cookie_contents = $_COOKIE['DAPROPS'];
 * }
 * 
 * $da_cache_provider = new Mobi_Mtld_DA_CacheProvider_FileCacheProvider();
 * $da_api_cached = new Mobi_Mtld_DA_ApiCached("json/sample.json",
 *												$da_cache_provider);
 * 
 * $properties = $da_api_cached->getProperties($ua, $cookie_contents);
 * </code>
 *  
 * <b>Note:</b>
 * 
 * It is not recommended to use ApiCached extension for batch processing
 * (i.e. multiple User-Agent detections during a single request). In these
 * situations use standard API interface.
 * 
 * See Api.php for more information
 * 
 *
 * @author dotMobi
 */
class Mobi_Mtld_DA_ApiCached {

	private $pathToJson; // path to the JSON file
	private $disableFileExistCheck = true; // disable addition file_exists check for the JSON file
	private $includeChangeableUserAgentProperties;
	
	private $tree; // loaded tree
	private $useTreeOptimizer; // enables tree optimizer
	private $treeOptimizerCacheDir; // cache dir for tree optimizer
	private $treeFragmentInMemory;
	private $useSysTempDir = true; // DEPRECATED
		
	private $cacheProvider; // results cache provider

	/**
	 * Creates new instance of Mobi_Mtld_DA_ApiCached object
	 *
	 * @param string $pathToJson The location of the file to read in.
	 * @param Mobi_Mtld_DA_CacheProvider_CacheProviderInterface $cacheProvider
	 * @param boolean $useTreeOptimizer
	 * @param string $treeOptimizerCacheDir Cache directory for the tree optimizer; uses sys_get_temp_dir() by default
	 * @param boolean $includeChangeableUserAgentProperties Also detect changeable user-agent properties
	 */
	public function __construct($pathToJson, Mobi_Mtld_DA_CacheProvider_CacheProviderInterface $cacheProvider = null, $useTreeOptimizer = true, $treeOptimizerCacheDir = null, $includeChangeableUserAgentProperties = true) {
		
		// Check JSON path
		$this->pathToJson = $pathToJson;
		if (!$this->disableFileExistCheck && !file_exists($pathToJson)){
			throw new Exception('Missing JSON file');
		}

		// Cache provider
		$this->cacheProvider = $cacheProvider;

		// Tree Optimizer
		$this->useTreeOptimizer = $useTreeOptimizer;
		$this->treeOptimizerCacheDir = $treeOptimizerCacheDir;

		// Changeable User-Agent Properties
		$this->includeChangeableUserAgentProperties = $includeChangeableUserAgentProperties;
	}
	
	/**
	 * Returns an array of known properties (as strings) for the user agent
	 * 
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return array properties Property name => Property value
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException
	 */
	public function getProperties($userAgent, $cookie = null) {
		if ($userAgent === ''){
			return array();
		}
		
		// If there is a tree in memory, let's use it
		if (!is_null($this->tree)){
			if (!$this->useTreeOptimizer){
				return Mobi_Mtld_DA_Api::getProperties($this->tree, $userAgent, $cookie);
			} else {
				$treeFragment = Mobi_Mtld_DA_TreeOptimizer::getShardId($userAgent);
				if (strcmp($this->treeFragmentInMemory, $treeFragment) === 0){
					return Mobi_Mtld_DA_Api::getProperties($this->tree, $userAgent, $cookie);
				}
			}
		}

		// Cache provider
		if (!is_null($this->cacheProvider)){
			// Create cache key
			$cacheKey = is_null($cookie) ? $userAgent : $userAgent.'::'.md5($cookie);
			$properties = $this->cacheProvider->get($cacheKey);
			if (is_null($properties)){
				//TODO we can add secondary cache here, using the TreeOptimizer pre walk tree
				$properties = $this->_getProperties($userAgent, $cookie);
				$this->cacheProvider->set($cacheKey, $properties);
			}
			return $properties;
		}

		// Return properties
		return $this->_getProperties($userAgent, $cookie);
	}

	/**
	 * Returns a value for the named property for this user agent
	 * 
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return string property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	public function getProperty($userAgent, $property, $cookie = null) {
		$properties = $this->getProperties($userAgent, $cookie);
		if (!isset($properties[$property])) {
			throw new Mobi_Mtld_Da_Exception_InvalidPropertyException("The property \"" . $property . "\" does not exist for the User Agent:\"" . $userAgent . "\"");
		}
		return $properties[$property];
	}

	/**
	 * DEPRECATED, not functional
	 * 
	 * @param boolean $useSysTempDir
	 */
	public function setUseSysTempDir($useSysTempDir){
		$this->useSysTempDir = (bool) $useSysTempDir;
	}

	/**
	 * Deletes all TreeOptimizer cache files
	 */
	public function clearTreeOptimizerCache(){
		return Mobi_Mtld_DA_TreeOptimizer::clearCache($this->treeOptimizerCacheDir);
	}

	/**
	 * Automatically populate full TreeOptimizer cache
	 */
	public function populateTreeOptimizerCache($force = false){
		$this->tree = null;
		$this->tree = Mobi_Mtld_DA_TreeOptimizer::populateCache($this->pathToJson, $this->treeOptimizerCacheDir, $force);
		// Disable tree optimizer
		$this->useTreeOptimizer = false;
	}
	
	/**
	 * Checks if the tree is loaded into the memory and call core API method
	 * to get the properties for a given user agent string
	 *
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return array properties
	 */
	private function _getProperties($userAgent, $cookie) {
		self::_loadTree($this->pathToJson, $userAgent);
		return Mobi_Mtld_DA_Api::getProperties($this->tree, $userAgent, $cookie);
	}

	/**
	 * Loads the optimized tree into the memory
	 *
	 * @param string $filename The location of the file to read in.
	 * @param string $userAgent String from the device's User-Agent header
	 */
	private function _loadTree($filename, $userAgent) {
		
		// No tree optimizer
		if (!$this->useTreeOptimizer){
			if (is_null($this->tree)){
				$this->tree = Mobi_Mtld_DA_Api::getTreeFromFile($filename, $this->includeChangeableUserAgentProperties);
			}
			return;
		}

		// Tree optimizer
		$treeFragment = Mobi_Mtld_DA_TreeOptimizer::getShardId($userAgent);
		if (is_null($this->tree)){
			$this->tree = Mobi_Mtld_DA_TreeOptimizer::getTreeFromFile($filename, $userAgent, $this->treeOptimizerCacheDir);
			$this->treeFragmentInMemory = $treeFragment;

			// Init PostWalk handlers
			Mobi_Mtld_DA_PropsHandler::_initPostWalkHandlers($this->tree, $this->includeChangeableUserAgentProperties);

		} else {
			// Most likely second+ query during a lifetime
			if (strcmp($this->treeFragmentInMemory, $treeFragment) !== 0){
				$this->tree = null;
				$this->tree = Mobi_Mtld_DA_Api::getTreeFromFile($filename, $this->includeChangeableUserAgentProperties);
				// Disable tree optimizer
				$this->useTreeOptimizer = false;
				return;
			}
		}
	}
}
