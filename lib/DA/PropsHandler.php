<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

/**
 * Class to provide methods and constants to handle properties.
 */

class Mobi_Mtld_DA_PropsHandler {

	/**
	 * Prepare the user-agent rules branch before we start recognition.
	 * To maintain backwards compatibility - only do this if we have the ua rules branch
	 * 
	 * NOT for public use
	 * 
	 * @param array &$tree
	 * @param boolean $includeChangeableUserAgentProperties
	 */
	public static function _initPostWalkHandlers(array &$tree, $includeChangeableUserAgentProperties){
		
		// UaProps
		if(isset($tree[Mobi_Mtld_DA_UaProps::UA_RULES])) {
			if(!$includeChangeableUserAgentProperties) {
				// remove the UAR branch to avoid it being used later on...
				unset($tree[Mobi_Mtld_DA_UaProps::UA_RULES]);
			} else {
				// stick in the tree so we can use it later
				$tree[Mobi_Mtld_DA_Api::UA_PROPS_HANDLER] = new Mobi_Mtld_DA_UaProps($tree);
			}
		}

		// ClientProps
		if(isset($tree[Mobi_Mtld_DA_ClientProps::CP_RULES])) {
			// stick in the tree so we can use it later
			$tree[Mobi_Mtld_DA_Api::CLIENT_PROPS_HANDLER] = new Mobi_Mtld_DA_ClientProps($tree);
		}
	}


	/**
	 * Gets the value for the property value id and either converts it to a strong type or returns a string.
	 * 
	 * This function is used in the PostWalk classes as well as in this class.
	 *
	 * @param array &$tree Previously generated tree
	 * @param integer $propId Property ID
	 * @param integer $propValId Property value ID
	 * @param boolean $typed 
	 * @return string|integer|boolean value
	 */
	public static function _getValue(array &$tree, $propId, $propValId, $typed) {
		// all changes to this function need to be applied to the _lookupNameValue()
		if ($typed) {
			return self::_convertToTyped($tree, $tree[Mobi_Mtld_DA_Api::ID_TO_VALUES][$propValId], $propId);
		} else {
			return $tree[Mobi_Mtld_DA_Api::ID_TO_VALUES][$propValId];
			//return self::_valueFromId($tree, $propValId);
		}
	}

	/**
	 * Returns the name for a property's coded ID.
	 * 
	 * This function is used in the PostWalk classes as well as in this class.
	 *
	 * @param array &$tree Previously generated tree
	 * @param integer $id
	 * @return string property
	 */
	public static function _propertyFromId(array &$tree, $id) {
		return $tree[Mobi_Mtld_DA_Api::ID_TO_PROPERTY_WITHOUT_TYPE][$id];
	}

	/**
	 * Convert a value to a typed object.
	 * 
	 * This function is used in the PostWalk classes as well as in this class.
	 * 
	 * @param array $tree Previously generated tree
	 * @param object $obj
	 * @param integer $propertyId
	 * @return string|integer|boolean value
	 */
	public static function _convertToTyped(array &$tree, $obj, $propertyId) {
		// get the property type using the propertyId
		$type = substr($tree[Mobi_Mtld_DA_Api::ID_TO_PROPERTIES_WITH_TYPE][$propertyId], 0, 1);
		return self::_convertToTypedByType($obj, $type);
	}

	public static function _convertToTypedByType($obj, $type) {
		switch ($type) {
			case 'b':
				if(is_string($obj)) {
					$obj = ($obj == '1' || strtolower($obj) == 'true');
				} else {
					settype($obj, "boolean");
				}
				break;
			case 's':
				settype($obj, "string");
				break;
			case 'i':
				settype($obj, "integer");
				break;
			case 'd':
				settype($obj, "string");
				break;
		}

		return $obj;
	}
	
	/**
	 * Returns the property value typed using PHP function settype().
	 * 
	 * This function is used in the PostWalk classes as well as in this class.
	 * @access private
	 *
	 * @param array &$tree Previously generated tree
	 * @param integer $id
	 * @param integer $propertyId
	 * @return string|integer|boolean value
	 */
	public static function _valueAsTypedFromId(array &$tree, $id, $propertyId) {
		return self::_convertToTyped($tree, $tree[Mobi_Mtld_DA_Api::ID_TO_VALUES][$id], $propertyId);
	}
	
	/**
	 * This function merges the user-agent properties into the main properties map.
	 * 
	 * @access protected
	 * 
	 * @param array &$properties
	 * @param array &$uaProperties
	 * @return array properties
	 */
	public static function _mergeProperties(&$properties, $uaProperties) {
		if(is_array($properties) && !empty($uaProperties)) {
			foreach($uaProperties as $key => $val) {
				$properties[$key] = $val;
			}
		}
	}
}
