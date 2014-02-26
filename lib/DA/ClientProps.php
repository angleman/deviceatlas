<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

class Mobi_Mtld_DA_ClientProps extends Mobi_Mtld_DA_PostWalkRules {

	const CP_RULES = "cpr"; // Client Property Rules
	const USER_AGENT = "ua";
	const OPERATOR_EQUALS = "=";
	const OPERATOR_NOT_EQUALS = "!=";
	const OPERATOR_LESS_THAN = "<";
	const OPERATOR_LESS_THAN_EQUALS = "<=";
	const OPERATOR_GREATER_THAN = ">";
	const OPERATOR_GREATER_THAN_EQUALS = ">=";
	
	const PROPERTY_NAME_ALLOWED_CHARS_PATTERN  = '/^[a-z0-9.]+$/i';

	public function __construct(&$tree) {
		// calls parent class constructor
		return parent::__construct($tree, self::CP_RULES);
	}

	/**
	 * Merge the tree walk properties with the client side properties and run any
	 * additional rules based on the client side and tree walk properties. The rules
	 * can define replacement or additional values for properties and can also provide
	 * a new User-Agent to be used for a second tree walk. This is typically a fake 
	 * User-Agent mapped to a device that cannot normally be detected such as the various
	 * iPhone models.
	 *
	 * @param array $detectedProperties The results of the tree walk, map of property id to value id
	 * @param array $clientProperties
	 * @param boolean $typedValues Whether to return typed values or string values
	 * @return array of properties or NULL if there are no properties
	 */
	public function getProperties($detectedProperties, $clientProperties, $typedValues) {
		if ($clientProperties === null){
			return $detectedProperties;
		}

		// Merge the tree walk properties with the client side properties
		Mobi_Mtld_DA_PropsHandler::_mergeProperties($detectedProperties, $clientProperties);

		// using the merged properties to look up additional rules
		// STEP 1: try and find the rules to run on the UA
		$rulesToRun = $this->_getRulesToRun($detectedProperties, $typedValues);

		// STEP 2: do second tree walk if necessary and replace/create any new 
		// values based on the rules
		if ($rulesToRun !== null) {
			$userAgent = $rulesToRun->getUserAgent();
			if ($userAgent !== null) {

				// use the UA for a second tree walk - note the last param is 
				// false as we know the UA won't have any dynamic properties
				$secondTreeWalkProps = Mobi_Mtld_DA_Api::getProperties($this->tree, $userAgent, null, $typedValues, false);

				// merge origProperties in to get any parent properties such as the dynamic properties
				// 2nd tree walk > first tree walk
				Mobi_Mtld_DA_PropsHandler::_mergeProperties($detectedProperties, $secondTreeWalkProps); 

				// the client properties still take priority so merge them in again
				// client props > tree walks
				Mobi_Mtld_DA_PropsHandler::_mergeProperties($detectedProperties, $clientProperties);
			}

			// overlay the new properties
			$ruleSet = $rulesToRun->getRuleSet();
			foreach ($ruleSet as $propIdValId) {
				$propId = $propIdValId[self::PROPERTY];
				$propValId = $propIdValId[self::PROPERTY_VALUE];

				// get prop and val to set
				$propName = Mobi_Mtld_DA_PropsHandler::_propertyFromId($this->tree, $propId);
				$value = Mobi_Mtld_DA_PropsHandler::_getValue($this->tree, $propId, $propValId, $typedValues);

				// replace/create properties
				$detectedProperties[$propName] = $value;
			}
		}

		return $detectedProperties;
	}

	/**
	 * Check if a given property exists with a certain type. This follows the 
	 * same logic as the Api::propertyTypeCheck() in that it really checks if a 
	 * property exists and if it has the correct type.
	 * 
	 * @param $cookie The cookie string
	 * @param $property The property name to check
	 * @param $typePrefix The type of the property (s,b,i,d)
	 */
	public static function propertyTypeCheck($cookie, $property, $typePrefix) {
		if ($cookie !== null && strpos($cookie, $typePrefix.$property) === false){
			return false;
		}
		return true;
	}
	
	/**
	 * Try and get a property from _only_ the passed client cookie
	 * 
	 * @param integer $propertyId
	 * @param string $propertyName
	 * @param array $parsedCookie
	 * @param boolean $typedValues
	 * @return array
	 */
	public function getClientProperty($propertyId, $propertyName, array $parsedCookie, $typedValues) {
		$detected = null;

		if (isset($parsedCookie[$propertyName])) {
			$detected[$propertyName] = $parsedCookie[$propertyName];
			if ($typedValues) {
				$detected[$propertyName] = Mobi_Mtld_DA_PropsHandler::_convertToTyped($this->tree, $parsedCookie[$propertyName], $propertyId);
			}
		}

		return $detected;
	}
	
	/**
	 * Find all the properties that are used for matching. This is needed in case
	 * the Api.getProperty() function is called as we need these properties for
	 * the rules to work correctly
	 * 
	 * @param array $groups The rule group that can contain a property matcher
	 * @param array $propIds The set of found property IDs
	 * @return An updated set of property IDs
	 */
	protected function _initGetMatcherPropertyIds($group, $propIds) {
		if (isset($group[self::PROPERTY_MATCHER])) {
			foreach ($group[self::PROPERTY_MATCHER] as &$propertyMatcher) {
				if (!isset($propIds[$propertyMatcher[self::PROPERTY]])) {
					$propIds[$propertyMatcher[self::PROPERTY]] = 1;
				}
			}
		}
		return $propIds;
	}

	/**
	 * Prepare the rule set by extracting it from the current group and wrapping
	 * it in an array. This is done to remain compatible with initGetRulePropertyIds()
	 * 
	 * @param array $group The current parent group.
	 * @return A list of all rule sets
	 */
	protected function _initRuleSets($group) {
		// wrap the single rule set in an array list.
		return array(array(self::RULE_ARR => $group[self::RULE_ARR]));
	}

	/**
	 * Parse the client side properties and if typed values is set convert the
	 * values to the appropiate type.
	 * 
	 * The propStr is of the form:
	 * bjs.webGl:1|bjs.geoLocation:1|sdeviceAspectRatio:16/10|iusableDisplayHeight:1050
	 * 
	 * The first character of the property name is the type of the value.
	 * 
	 * @param string $propStr
	 * @param boolean $typedValues
	 * @return array
	 * 
	 * @throws Mobi_Mtld_Da_Exception_JsonException 
	 */
	public function parseClientSideProperties($propStr, $typedValues) {
		$props = null;
		if (!empty($propStr)) {
			$nameValuePairs = explode('|', trim($propStr, '"'));
			foreach($nameValuePairs as $nameValuePair){
				list($typeAndName, $rawValue) = explode(':', $nameValuePair, 2);
				self::_appendPropVal($props, $typeAndName, $rawValue, $typedValues);
			}
			if (count($props) == 0){
				throw new Mobi_Mtld_Da_Exception_ClientPropertiesException("Could not decode client properties");
			}
		}

		return $props;
	}

	/**
	 * Convert the collected prop and value strings and convert the value to a
	 * typed value if necessary. The prop and value are then added to the props
	 * HashMap.
	 * 
	 * @param array &$props
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 * @param $typedValues 
	 */
	private static function _appendPropVal(&$props, $typeAndName, $value, $typedValues) {
		if (!empty($typeAndName) && preg_match(self::PROPERTY_NAME_ALLOWED_CHARS_PATTERN, $typeAndName)) {
		//if (!empty($typeAndName) && ctype_alnum(str_replace('.', '', $typeAndName))) {

			// Sanitize any external input
			switch ($typeAndName{0}){
				case 'b':
				case 'i':
					$value = (int) $value;
					break;
				case 's':
				case 'd':
					$value = filter_var(trim($value, '"'), FILTER_SANITIZE_SPECIAL_CHARS);
					break;
				default:
					throw new Mobi_Mtld_Da_Exception_ClientPropertiesException("Could not decode client properties");
					break;
			}
			
			if ($typedValues) {
				$value = Mobi_Mtld_DA_PropsHandler::_convertToTypedByType($value, $typeAndName{0});
			}

			$props[substr($typeAndName, 1)] = $value;
		}
	}

	/**
	 * 
	 * @param array $detectedProperties
	 * @param boolean $typedValues
	 * @return array
	 */
	private function _getRulesToRun($detectedProperties, $typedValues) {
		$rulesToReturn = null;
		foreach ($this->tree[$this->branch][self::RULE_GROUPS] as $group) {
			$propertyMatchers = $group[self::PROPERTY_MATCHER];

			// try matching defined properties so we know what rules to run. If there
			// is a match then we can return the rules to run.
			$propMatch = $this->_checkPropertiesMatch($propertyMatchers, $detectedProperties, $typedValues);
			if ($propMatch) {
				$userAgent = isset($group[self::USER_AGENT]) ? $group[self::USER_AGENT] : null;
				$ruleSet = $group[self::RULE_ARR];
				$rulesToReturn = new Mobi_Mtld_DA_ClientPropsRuleSet($userAgent, $ruleSet);
				break;
			}
		}

		return $rulesToReturn;
	}

	/**
	 * This functions checks all the properties in the property matcher branch of
	 * this rule group. This branch contains a list of properties, their values
	 * and an operator to use for comparison. All must match for this function to 
	 * return true.
	 *
	 * In reality the properties and values are indexes to the main property and
	 * value arrays.
	 * 
	 * @param array $propertyMatchers the list of property matchers to run
	 * @param array $detectedProperties The merged properties from the tree walk and client
	 * @param boolean $typedValues if the user asked for typed values - if not we need to convert the detectedProperties to typed
	 * @return TRUE if ALL properties match, false otherwise
	 */
	private function _checkPropertiesMatch($propertyMatchers, $detectedProperties, $typedValues) {
		$propMatch = false;

		// loop over propList and try and match ALL properties
		foreach ($propertyMatchers as $matcherDetails) {
			$propId = $matcherDetails[self::PROPERTY];
			$propName = Mobi_Mtld_DA_PropsHandler::_propertyFromId($this->tree, $propId);

			// compare the detected value to the expected value
			if (isset($detectedProperties[$propName])) {
				$detectedValue = $detectedProperties[$propName];

				// if not already converted convert the string to correct property type 
				if (!$typedValues) {
					$detectedValue = Mobi_Mtld_DA_PropsHandler::_convertToTyped($this->tree, $detectedValue, $propId);
				}

				// get the expected value
				$propValId = $matcherDetails[self::PROPERTY_VALUE];
				$expectedValue = Mobi_Mtld_DA_PropsHandler::_valueAsTypedFromId($this->tree, $propValId, $propId);
				$operator = $matcherDetails[self::OPERATOR];
				$typePropName = $this->propertyIdToType[$propId];
				$propMatch = $this->_compareValues($detectedValue, $expectedValue, $operator, $typePropName);
				if (!$propMatch) {
					break; // break out of here!
				}

			} else {
				$propMatch = false;
				break;
			}
		}

		return $propMatch;
	}

	/**
	 * Compare two values that can be one of String, Boolean or Integer using the
	 * passed in operator.
	 * 
	 * @param string $detectedValue
	 * @param string $expectedValue
	 * @param string $operator
	 * @param string $typePropName
	 * @return boolean
	 */
	private function _compareValues($detectedValue, $expectedValue, $operator, $typePropName) {
		$result = false;

		switch ($typePropName{0}) {
			case 's':
			case 'b':
				if ($operator == self::OPERATOR_EQUALS) {
					$result = ($detectedValue == $expectedValue);
				} else if ($operator == self::OPERATOR_NOT_EQUALS) {
					$result = !($detectedValue == $expectedValue);
				}

				break;
			case 'i':
				$dVal = (int) $detectedValue;
				$eVal = (int) $expectedValue;

				if ($dVal == $eVal && ($operator == self::OPERATOR_EQUALS
						|| $operator == self::OPERATOR_LESS_THAN_EQUALS
						|| $operator == self::OPERATOR_GREATER_THAN_EQUALS)) {
					$result = true;
				} else if ($dVal > $eVal && ($operator == self::OPERATOR_GREATER_THAN
						|| $operator == self::OPERATOR_GREATER_THAN_EQUALS)) {
					$result = true;
				} else if ($dVal < $eVal && ($operator == self::OPERATOR_LESS_THAN
						|| $operator == self::OPERATOR_LESS_THAN_EQUALS)) {
					$result = true;
				} else if ($dVal != $eVal && $operator == self::OPERATOR_NOT_EQUALS) {
					$result = true;
				}
				break;
		}

		return $result;
	}

}
