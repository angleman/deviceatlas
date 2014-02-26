<?php

/**
 * @package Mobi_Mtld_DA
 * @copyright Copyright (c) 2008-2013 by mTLD Top Level Domain Limited.  All rights reserved.
 * @version 1.6_1
 * 
 * Portions copyright (c) 2008 by Argo Interactive Limited.
 * Portions copyright (c) 2008 by Nokia Inc.
 * Portions copyright (c) 2008 by Telecom Italia Mobile S.p.A.
 * Portions copyright (c) 2008 by Volantis Systems Limited.
 * Portions copyright (c) 2002-2008 by Andreas Staeding.
 * Portions copyright (c) 2008 by Zandan.
 * 
 */

/**
 * Class definitions for custom errors
 */
define('Mobi_Mtld_DA_Path', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('Mobi_Mtld_DA_Exception', Mobi_Mtld_DA_Path . 'Exception' . DIRECTORY_SEPARATOR);

require_once Mobi_Mtld_DA_Exception . 'JsonException.php';
require_once Mobi_Mtld_DA_Exception . 'InvalidPropertyException.php';
require_once Mobi_Mtld_DA_Exception . 'UnknownPropertyException.php';
require_once Mobi_Mtld_DA_Exception . 'IncorrectPropertyTypeException.php';
require_once Mobi_Mtld_DA_Exception . 'ClientPropertiesException.php';

require_once Mobi_Mtld_DA_Path . 'PostWalkRules.php';
require_once Mobi_Mtld_DA_Path . 'UaProps.php';
require_once Mobi_Mtld_DA_Path . 'ClientProps.php';
require_once Mobi_Mtld_DA_Path . 'ClientPropsRuleSet.php';
require_once Mobi_Mtld_DA_Path . 'PropsHandler.php';

/**
 * Used to load the recognition tree and perform lookups of all properties, or
 * individual properties.
 * 
 * <b>Note:</b> Due to limitations in the level of recursion allowed, versions of PHP
 * older than 5.2.3 will be unable to load the JSON data file.
 * i.e. DeviceAtlas must be run with PHP version 5.2.3 or later.
 * 
 * Typical usage is as follows:
 * 
 * <code>
 * $tree = Mobi_Mtld_DA_Api::getTreeFromFile("json/sample.json");
 * $props = Mobi_Mtld_DA_Api::getProperties($tree, "Nokia6680...");
 * </code>
 * 
 * Note that you should normally use the user-agent that was received in
 * the device's HTTP request. In a PHP environment, you would do this as follows:
 * 
 * <code>
 * $ua = $_SERVER['HTTP_USER_AGENT'];
 * $displayWidth = Mobi_Mtld_DA_Api::getPropertyAsInteger($tree, $ua, "displayWidth");
 * </code>
 * 
 * (Also note the use of the strongly typed property accessor)
 * 
 * Third-party Browsers
 * 
 * In some contexts, the user-agent you want to recognise may have been provided in a
 * different header. Opera's mobile browser, for example, makes requests via an
 * HTTP proxy, which rewrites the headers. in that case, the original device's
 * user-agent is in the HTTP_X_OPERAMINI_PHONE_UA header, and the following code
 * could be used:
 * 
 * <code>
 * $opera_header = "HTTP_X_OPERAMINI_PHONE_UA";
 * if (array_key_exists($opera_header, $_SERVER) {
 *   $ua = $_SERVER[$opera_header];
 * } else {
 *   $ua = $_SERVER['HTTP_USER_AGENT'];
 * }
 * $displayWidth = Mobi_Mtld_DA_Api::getPropertyAsInteger($tree, $ua, "displayWidth");
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
 * $props = Mobi_Mtld_DA_Api::getProperties($tree, $ua, $cookie_contents);
 * </code>
 * 
 */
class Mobi_Mtld_DA_Api {

	const API_ID = 1;
	const MIN_PHP_VERSION = '5.2.3';
	const JSON_INFO = '$';

	const CLIENT_PROPS_HANDLER = "_cprops";
	const UA_PROPS_HANDLER = "_uaprops";

	const ID_TO_PROPERTIES_WITH_TYPE = "p";		// e.g. 1: bmobileDevice
	const PROPERTIES_WITH_TYPE_TO_ID = "pr";	// e.g. bmobileDevice: 1
	const PROPERTIES_WITHOUT_TYPE_TO_ID = "pn";	// e.g. mobileDevice: 1
	const ID_TO_PROPERTY_WITHOUT_TYPE = "pnr";	// e.g. 1: mobileDevice

	const ID_TO_VALUES = "v";		// e.g. 1: false
	
	const MAIN_TREE_BRANCH = "t";
	const REGEX = "r";
	const COMPILED_REGEX = "creg"; // not used in PHP

	/**
	 * Returns a loaded JSON tree from a string of JSON data.
	 *
	 * Some properties cannot be known before runtime and can change from user-agent to
	 * user-agent. The most common of these are the OS Version and the Browser Version. This
	 * API is able to dynamically detect these changing properties but introduces a small
	 * overhead to do so. To disable returning these extra properties set
	 * <i>includeChangeableUserAgentProperties</i> to <b>false</b>.
	 * 
	 * @param string &$json The string of json data.
	 * @param boolean $includeChangeableUserAgentProperties Also detect changeable user-agent properties
	 * @return array The loaded JSON tree
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException
	 */
	public static function getTreeFromString(&$json, $includeChangeableUserAgentProperties=true) {
		if(version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0){
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"DeviceAtlas requires PHP version 5.2.3 or later to load the Json data.",
			Mobi_Mtld_Da_Exception_JsonException::PHP_VERSION);
		}

		$tree = json_decode($json, true);

		if($tree === FALSE || !is_array($tree)){
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"Unable to load Json data.",
			Mobi_Mtld_Da_Exception_JsonException::JSON_DECODE);
		}
		elseif (!array_key_exists(self::JSON_INFO, $tree)){
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"Bad data loaded into the tree.",
			Mobi_Mtld_Da_Exception_JsonException::BAD_DATA);
		}
		elseif($tree[self::JSON_INFO]["Ver"] < 0.7) {
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"DeviceAtlas json file must be v0.7 or greater. Please download a more recent version.",
			Mobi_Mtld_Da_Exception_JsonException::JSON_VERSION);
		}
		
		// Internal lookup arrays
		$tree[self::PROPERTIES_WITH_TYPE_TO_ID] = array(); // propertytype+name => propertyid
		$tree[self::PROPERTIES_WITHOUT_TYPE_TO_ID] = array(); // propertyname => propertyid
		$tree[self::ID_TO_PROPERTY_WITHOUT_TYPE] = array();// property names without the type char

		foreach ($tree[self::ID_TO_PROPERTIES_WITH_TYPE] as $key => $value) {
			$name =  substr($value, 1); // knock off property type char
			$tree[self::PROPERTIES_WITH_TYPE_TO_ID][$value] = $key;
			$tree[self::PROPERTIES_WITHOUT_TYPE_TO_ID][$name] = $key;
			$tree[self::ID_TO_PROPERTY_WITHOUT_TYPE][$key] = $name;
		}

		if (!isset($tree[self::REGEX])) {
			$tree[self::REGEX] = array();
		}

		// Init internal handlers
		Mobi_Mtld_DA_PropsHandler::_initPostWalkHandlers($tree, $includeChangeableUserAgentProperties);

		// Done
		return $tree;
	}
	
	/**
	 * Returns a tree from a JSON file.
	 * 
	 * Use an absolute path name to be sure of success if the current working directory is not clear.
	 *
	 * Some properties cannot be known before runtime and can change from user-agent to
	 * user-agent. The most common of these are the OS Version and the Browser Version. This
	 * API is able to dynamically detect these changing properties but introduces a small
	 * overhead to do so. To disable returning these extra properties set
	 * <i>includeChangeableUserAgentProperties</i> to <b>false</b>.
	 * 
	 * @param string $filename The location of the file to read in.
	 * @param boolean $includeChangeableUserAgentProperties
	 * @return array &$tree Previously generated tree
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException
	 */
	public static function getTreeFromFile($filename, $includeChangeableUserAgentProperties=true) {
		$json = file_get_contents($filename);
		if($json === FALSE){
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"Unable to load file:" . $filename,
			Mobi_Mtld_Da_Exception_JsonException::FILE_ERROR);
		}
		return self::getTreeFromString($json, $includeChangeableUserAgentProperties);
	}

	/**
	 * Get the generation date for this tree.
	 * 
	 * @param array &$tree Previously generated tree
	 * @return string The time/date the tree was generated.
	 */
	public static function getTreeGeneration(array &$tree) {
		return $tree[self::JSON_INFO]['Gen'];
	}

	/**
	 * Get the generation date for this tree as a UNIX timestamp.
	 * 
	 * @param array &$tree Previously generated tree
	 * @return integer The time/date the tree was generated.
	 */
	public static function getTreeGenerationAsTimestamp(array &$tree) {
		return $tree[self::JSON_INFO]['Utc'];
	}

	/**
	 * Returns the revision number of the tree
	 *
	 * @param array &$tree Previously generated tree
	 * @return integer revision
	 */
	public static function getTreeRevision(array &$tree) {
		return self::_getRevisionFromKeyword($tree[self::JSON_INFO]["Rev"]);
	}

	/**
	 * Returns the revision number of this API
	 * 
	 * @return integer revision
	 */
	public static function getApiRevision() {
		return self::_getRevisionFromKeyword('$Rev: 2830 $');
	}

	/**
	 * Returns all properties available for all user agents in this tree,
	 * with their data type names.
	 *
	 * @param array &$tree Previously generated tree
	 * @return array properties
	 */
	public static function listProperties(array &$tree) {
		$types = array(
			"s"=>"string",
			"b"=>"boolean",
			"i"=>"integer",
			"d"=>"date",
			"u"=>"unknown"
		);
		$listProperties = array();
		foreach($tree[self::ID_TO_PROPERTIES_WITH_TYPE] as $property) {
			$listProperties[substr($property, 1)] = $types[$property{0}];
		}
		return $listProperties;
	}
	
	/**
	 * Returns an array of known properties (as strings) for the user agent
	 * 
	 *		= or =
	 * 
	 * Returns an array of known properties merged with properties from the client
	 * side JavaScript. The client side JavaScript sets a cookie with collected
	 * properties. The contents of this cookie must be passed to this method for it
	 * to work. The client properties over-ride any properties discovered from the
	 * main JSON data file.
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return array properties Property name => Property value
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException
	 */
	public static function getProperties(array &$tree, $userAgent, $cookie = null) {
		return self::_getProperties($tree, $userAgent, $cookie, false);
	}
	
	/**
	 * Returns an array of known properties (as typed) for the user agent
	 * 
	 *		= or =
	 * 
	 * Returns an array of known properties merged with properties from the client
	 * side JavaScript. The client side JavaScript sets a cookie with collected
	 * properties. The contents of this cookie must be passed to this method for it
	 * to work. The client properties over-ride any properties discovered from the
	 * main JSON data file.
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return array properties. Property name => Typed property value
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException
	 */
	public static function getPropertiesAsTyped(array &$tree, $userAgent, $cookie = null) {
		return self::_getProperties($tree, $userAgent, $cookie, true);
	}

	/**
	 * Returns a value for the named property for this user agent
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return string property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	public static function getProperty(array &$tree, $userAgent, $property, $cookie = null) {
		return self::_getProperty($tree, $userAgent, $property, $cookie, false);
	}

	/**
	 * Strongly typed property accessor.
	 * 
	 * Returns a boolean property.
	 * (Throws an exception if the property is actually of another type.)
	 * 
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return boolean property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	public static function getPropertyAsBoolean(array &$tree, $userAgent, $property, $cookie = null) {
		self::_propertyTypeCheckWithCookie($tree, $property, "b", "boolean", $cookie);
		return self::_getProperty($tree, $userAgent, $property, $cookie, true);
	}

	/**
	 * Strongly typed property accessor.
	 * 
	 * Returns a date property.
	 * (Throws an exception if the property is actually of another type.)
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return string property
	 *  
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 *
	 */
	public static function getPropertyAsDate(array &$tree, $userAgent, $property, $cookie = null) {
		self::_propertyTypeCheckWithCookie($tree, $property, "d", "string", $cookie);
		return self::_getProperty($tree, $userAgent, $property, $cookie, true);
	}

	/**
	 * Strongly typed property accessor.
	 * 
	 * Returns an integer property.
	 * (Throws an exception if the property is actually of another type.)
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return integer property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 *
	 */
	public static function getPropertyAsInteger(array &$tree, $userAgent, $property, $cookie = null) {
		self::_propertyTypeCheckWithCookie($tree, $property, "i", "integer", $cookie);
		return self::_getProperty($tree, $userAgent, $property, $cookie, true);
	}

	/**
	 * Strongly typed property accessor.
	 * 
	 * Returns a string property.
	 * (Throws an exception if the property is actually of another type.)
	 * 
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @return string property
	 *  
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 *
	 */
	public static function getPropertyAsString(array &$tree, $userAgent, $property, $cookie = null) {
		self::_propertyTypeCheckWithCookie($tree, $property, "s", "string", $cookie);
		return self::_getProperty($tree, $userAgent, $property, $cookie, true);
	}
	
	// PRIVATE FUNCTIONS

	/**
	 * Formats the SVN revision string to return a number
	 * 
	 * @access private
	 * 
	 * @param string $keyword
	 * @return integer revision number
	 */
	private static function _getRevisionFromKeyword($keyword) {
		return trim(str_replace('$', "", substr($keyword, 6)));
	}

	/**
	 * Returns an array of known properties for the user agent.
	 * Allows the values of properties to be forced to be strings.
	 *
	 * @access private
	 * 
	 * @param array &$tree previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @param boolean $typedValues Whether values in the results are typed
	 * @return array properties Property name => Property value
	 */
	private static function _getProperties(array &$tree, $userAgent, $cookie = null, $typedValues) {
		if ($cookie === null){
			return self::_getPropertiesFromTree($tree, $userAgent, null, $typedValues);
		} else {
			return self::_getPropertiesWithCookies($tree, $userAgent, $cookie, $typedValues);
		}
	}

	/**
	 * Returns an array of known properties merged with properties from the client
	 * side JavaScript. The client side JavaScript sets a cookie with collected
	 * properties. The contents of this cookie must be passed to this method for it
	 * to work. The client properties over-ride any properties discovered from the
	 * main JSON data file.
	 * 
	 * @access private
	 * 
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @param boolean $typedValues Whether values in the results are typed
	 * @return array properties Property name => Property value
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	private static function _getPropertiesWithCookies(array &$tree, $userAgent, $cookie, $typedValues) {
		$clientPropsHandler = $tree[self::CLIENT_PROPS_HANDLER];
		if(!isset($tree[self::CLIENT_PROPS_HANDLER])) {
			// don't let them use this method if the JSON file does not contain the
			// required CPR section
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"JSON file does not support client properties.",
				Mobi_Mtld_Da_Exception_JsonException::NO_CLIENT_PROPERTIES_SECTION);
		}

		$treeWalkProperties = self::_getPropertiesFromTree($tree, $userAgent, null, false);
		$parsedCookie = $clientPropsHandler->parseClientSideProperties($cookie, $typedValues);
		return $clientPropsHandler->getProperties($treeWalkProperties, $parsedCookie, $typedValues);
	}

	/**
	 * Returns the properties for a given User-Agent by first walking the tree
	 * and then supplementing the tree properties with properties from the 
	 * User-Agent string itself.
	 * 
	 * @access private
	 * 
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param array|null $sought Properties being sought
	 * @param boolean $typedValues Whether values in the results are typed
	 * @param boolean $uaPropsNeeded Whether the extra properties from the UA String are needed
	 * @return array properties Property name => Property value
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException 
	 */
	private static function _getPropertiesFromTree(array &$tree, $userAgent, $sought, $typedValues, $uaPropsNeeded = true) {
		$foundProperties = null;
		
		// we can exclude the UA property part if none of the sought properties depend on it
		$uaPropsHandler = null;
		
		if ($sought !== null){
			$uaPropsNeeded = false; // default to false unless one of the sought properties needs it
			
			// check if we need to get any of the ua props
			if($tree[self::UA_PROPS_HANDLER] !== null) {
				$uaPropsHandler = $tree[self::UA_PROPS_HANDLER];
				// check all sought properties and see if the ua props output any of them
				foreach(array_keys($sought) as $soughtIdProperty){
					if ($uaPropsHandler->propIsOutput($soughtIdProperty)){
						$uaPropsNeeded = true;
						break;
					}
				}

				if($uaPropsNeeded) {
					// add required props to our list that gets passed to the tree walk
					$sought += $uaPropsHandler->getRequiredProperties();
				}
			}
		}

		// we have our list of properties so do a tree walk with them
		$userAgent = trim($userAgent);
		
		// prepare containers for tree walk data
		$idProperties = array();
		$matched = "";		

		// walk the tree and find the properties
		$regexes = $tree[self::REGEX][self::API_ID];
		$soughtCopy = $sought; // create a copy for secondary tree walk
		self::_seekProperties($tree[self::MAIN_TREE_BRANCH], $userAgent, $idProperties, $sought, $matched, $regexes);

		// get the actual name and value from the ID arrays
		$foundProperties = self::_lookupNameValue($tree, $idProperties, $typedValues);

		if ($sought === null){
			$foundProperties["_matched"] = $matched;
			$foundProperties["_unmatched"] = substr($userAgent, strlen($matched)).'';
		}

		// augment property set with properties from the UA string itself
		if($uaPropsNeeded) {
			if($uaPropsHandler === null) {
				// if it was not fetched above go get it...
				$uaPropsHandler = $tree[self::UA_PROPS_HANDLER];
			}
			
			if($uaPropsHandler !== null) {
				// augment the properties with properties from User-Agent string itself
				$uaProps = $uaPropsHandler->getProperties($userAgent, $idProperties, $soughtCopy, $typedValues);
				Mobi_Mtld_DA_PropsHandler::_mergeProperties($foundProperties, $uaProps);
			}
		}

		return $foundProperties;
	}

	/**
	 * Lookup the property names and property values from the holder arrays.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param array $idProperties List of integers
	 * @param boolean $typedValues Whether values in the results are typed
	 * @return array properties The property list with real name and values
	 */
	private static function _lookupNameValue(array &$tree, $idProperties, $typedValues) {
		// Use some lookup arrays directly here to speed up the code
		$properties = array_flip(array_intersect_key($tree[self::ID_TO_PROPERTY_WITHOUT_TYPE], $idProperties));
		foreach ($properties as $propName => $propId) {
			$properties[$propName] = $typedValues ? Mobi_Mtld_DA_PropsHandler::_convertToTyped($tree, $tree[self::ID_TO_VALUES][$idProperties[$propId]], $propId) : $tree[self::ID_TO_VALUES][$idProperties[$propId]];
		}
		return $properties;
	}

	/**
	 * Returns a value for the named property for this user agent.
	 * Allows the value to be typed or forced as a string.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @param boolean $typedValue Whether values in the results are typed
	 * @return string|integer|boolean property
	 *
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 */
	private static function _getProperty(array &$tree, $userAgent, $property, $cookie, $typedValue = false) {
		if ($cookie === null || empty($cookie)){
			return self::_getPropertyFromTree($tree, $userAgent, $property, $typedValue);
		} else {
			return self::_getPropertyWithCookies($tree, $userAgent, $property, $cookie, $typedValue);
		}
	}

	/**
	 * Get the property from the tree walk, User-Agent string and client properties.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * @param boolean $typedValues Whether values in the results are typed
	 * @return string|integer|boolean property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	private static function _getPropertyWithCookies(array &$tree, $userAgent, $property, $cookie, $typedValues) {
		$clientPropsHandler = $tree[self::CLIENT_PROPS_HANDLER];
		if($clientPropsHandler === null) {
			// don't let them use this method if the JSON file does not contain the
			// required CPR section
			throw new Mobi_Mtld_Da_Exception_JsonException(
				"JSON file does not support client properties.",
				Mobi_Mtld_Da_Exception_JsonException::NO_CLIENT_PROPERTIES_SECTION);
		}
				
		// property priority:
		// - client side rules
		// - client side
		// - second tree walk
		// - ua props
		// - first tree walk
		
		// we keep the parsed cookie to avoid re-parsing it further down...
		$parsedCookie = $clientPropsHandler->parseClientSideProperties($cookie, $typedValues);
		$foundProperties = null;

		// get a property id, if exists
		try {
			$propertyId = self::_idFromProperty($tree, $property);
		} catch (Mobi_Mtld_Da_Exception_UnknownPropertyException $e){
			$propertyId = null;
		}

		// given that the client side properties have second highest priority - check if this
		// property is available without needing the client side rules to run
		if($parsedCookie !== null && isset($parsedCookie[$property])) {
			$isRuleProp = ($propertyId !== null && $clientPropsHandler->propIsOutput($propertyId)); // if its set by a rule prop we need to a full walk
			if(!$isRuleProp) {
				$foundProperties = array(
					$property => $parsedCookie[$property]
				);
			}
		}
		
		// check the tree
		if(($foundProperties === null || !isset($foundProperties[$property])) && $propertyId !== null) {
			// get properties from normal tree walk and uar
			$sought = array($propertyId => 1); // add the actual one we are looking for
			
			// prepare for tree walk by getting all the required properties for
			// the client side rules
			$sought += $clientPropsHandler->getRequiredProperties();
			
			// get properties from tree walk and uar
			$foundProperties = self::_getPropertiesFromTree($tree, $userAgent, $sought, $typedValues);
			
			// pass all to client props handler
			$foundProperties = $clientPropsHandler->getProperties($foundProperties, $parsedCookie, $typedValues);
		}
		
		if($foundProperties === null || !isset($foundProperties[$property])) {
			throw new Mobi_Mtld_Da_Exception_InvalidPropertyException("The property \"" . $property . "\" does not exist for the User-Agent:\"" . $userAgent . "\"");
		}

		return $foundProperties[$property];
	}
	
	/**
	 * Returns a value for the named property from the tree walk only
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $userAgent String from the device's User-Agent header
	 * @param string $property The name of the property to return
	 * @param boolean $typedValues Whether values in the results are typed
	 * @return string|integer|boolean property
	 * 
	 * @throws Mobi_Mtld_Da_Exception_InvalidPropertyException
	 */
	private static function _getPropertyFromTree(array &$tree, $userAgent, $property, $typedValues) {
		$propertyId = self::_idFromProperty($tree, $property);
		$sought = array($propertyId => 1);

		$properties = self::_getPropertiesFromTree($tree, $userAgent, $sought, $typedValues);
		if (!isset($properties[$property])) {
			throw new Mobi_Mtld_Da_Exception_InvalidPropertyException("The property \"" . $property . "\" does not exist for the User-Agent:\"" . $userAgent . "\"");
		}

		return $properties[$property];
	}

	/**
	 * Return the coded ID for a property's name.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $property
	 * @return string id
	 * 
	 * @throws Mobi_Mtld_Da_Exception_UnknownPropertyException
	 */
	private static function _idFromProperty(array &$tree, $property) {
		if(isset($tree[self::PROPERTIES_WITHOUT_TYPE_TO_ID][$property])){
			return $tree[self::PROPERTIES_WITHOUT_TYPE_TO_ID][$property];
		} else {
			throw new Mobi_Mtld_Da_Exception_UnknownPropertyException("The property \"" . $property . "\" is not known in this tree.");
		}
	}

	/**
	 * Checks that the property is of the supplied type or throws an error.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated HashMap tree
	 * @param array $property The name of the property to return
	 * @param string $typePrefix The type prefix (i for integer)
	 * @param string $typeName Easy readable type name
	 * 
	 * @throws Mobi_Mtld_Da_Exception_IncorrectPropertyTypeException
	 */
	private static function _propertyTypeCheck(array &$tree, $property, $typePrefix, $typeName) {
	  if (!isset($tree[self::PROPERTIES_WITH_TYPE_TO_ID][$typePrefix.$property])) {
	    throw new Mobi_Mtld_Da_Exception_IncorrectPropertyTypeException(
			$property . " is not of type " . $typeName);
		}
	}
	
	/**
	 * Check the property type when the user provides a cookie value
	 * 
	 * @param array &$tree Previously generated HashMap tree
	 * @param array $property The name of the property to return
	 * @param string $typePrefix The type prefix (i for integer)
	 * @param string $typeName Easy readable type name
	 * @param string|null $cookie The contents of the cookie containing the client side properties
	 * 
	 * @throws Mobi_Mtld_Da_Exception_IncorrectPropertyTypeException
	 * 
	 */
	private static function _propertyTypeCheckWithCookie(array &$tree, $property, $typePrefix, $typeName, $cookie) {
		try {
			// check values defined in JSON file
			self::_propertyTypeCheck($tree, $property, $typePrefix, $typeName);
			
		} catch (Mobi_Mtld_Da_Exception_IncorrectPropertyTypeException $ex) {
			// check values defined in cookie just in case it is a pure client side property
			$res = Mobi_Mtld_DA_ClientProps::propertyTypeCheck($cookie, $property, $typePrefix);
			if(!$res) {
				throw $ex; // pass exception on
			}
		}
	}

	/**
	 * Seek properties for a user agent within a node. 
	 * 
	 * This is designed to be recursed, and only externally called with the node representing the top of the tree.
	 * @access private
	 *
	 * @param array &$node
	 * @param string $string
	 * @param array &$properties Properties found
	 * @param array &$sought Properties being sought
	 * @param string &$matched Part of UA that has been matched
	 * 
	 */
	private static function _seekProperties(array &$node, $string, array &$properties, &$sought, &$matched, &$rules) {
//		$unmatched = $string;

		if (isset($node['d'])) {
			if ($sought === null){
				$properties = $node['d'] + $properties;
			} else {
				foreach($sought as $property => $value) {
					if (isset($node['d'][$property])) {
						$properties[$property] = $node['d'][$property];
						if (!isset($node['m']) || !isset($node['m'][$property])){
							unset($sought[$property]);
							if (count($sought) == 0){
								return;
							}
						}
					}
				}
			}
			
//			if ($sought !== NULL && count($sought) == 0) {
//				return;
//			}
//			foreach($node['d'] as $property => $value) {
//				if ($sought === NULL || isset($sought[$property])) {
//					$properties[$property] = $value;
//				}
//				if ($sought !== NULL &&
//				( !isset($node['m']) || ( isset($node['m']) && !isset($node['m'][$property]) ) ) ){
//					unset($sought[$property]);
//					}
//				}
//			}
		}

		if (isset($node['c'])) {
			// rules - strip out parts of the UA
			if (isset($node['r'])) {
				// make sure to run them in the order they appear
				foreach($node['r'] as $rule_id){
					$string = preg_replace('/' . $rules[$rule_id] . '/', '', $string);
				}
			}
			
			for($c = 1; $c < strlen($string) + 1; $c++) {
				$seek = substr($string, 0, $c);
				if(isset($node['c'][$seek])) {
					$matched .= $seek;
					self::_seekProperties($node['c'][$seek], substr($string, $c), $properties, $sought, $matched, $rules);
					break;
				}
			}
		}
	}

	/**
	 * Return the value for a value's coded ID.
	 * 
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param string $id
	 * @return string value
	 */
	private static function _valueFromId(array &$tree, $id) {
		return $tree[self::ID_TO_VALUES][$id];
	}

	/**
	 * Used only by the TreeOptimizer class to get the PostWalk User-Agents properties.
	 * 
	 * NOT for public use
	 * 
	 * @param array &$node
	 * @param string $string
	 * @param array &$properties
	 * 
	 * @return array properties
	 */
	public static function _seekPostWalkProperties(array &$node, $string, array &$properties){
		$sought = null;
		$matched = '';
		$rules = array();
		return self::_seekProperties($node, $string, $properties, $sought, $matched, $rules);
	}
	
	
}
