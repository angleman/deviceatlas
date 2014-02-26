<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

/**
 * This class is used by the main Api class and should not be used directly.
 * 
 * This class tries to extract properties from the User-Agent string itself. This is
 * a completely separate step to the main JSON tree walk but uses the results of the
 * tree walk to optimise the property extraction. The property extraction is done in
 * two steps.
 *
 * Step 1: Try and identify the type of User-Agent and thus the set of property
 * extraction rules to run. This is optimised by the properties from the tree walk.
 *
 * Step 2: Run the rules found in step 1 to try and extract the properties.
 *
 * @author dotMobi
 */
class Mobi_Mtld_DA_UaProps extends Mobi_Mtld_DA_PostWalkRules {

	// setup names for all the node ids from the tree!
	const UA_RULES = "uar";
	const SKIP_IDS = "sk";
	const REGEXES = "reg";
	const DEFAULT_REGEX_SET = "d";
	const REFINE_REGEX_ID = "f";
	const SEARCH_REGEX_ID = "s";
	const RULE_REGEX_ID = "r";
	const REGEX_MATCH_POS = "m";
	
	private $regexes;

	public function __construct(&$tree) {
		// calls parent class constructor
		parent::__construct($tree, self::UA_RULES);

		// process the regexes - we need to override the default ones with any API
		// specific regexes and compile them all
		$this->_initProcessRegexes();
	}

	/**
	 * Get the User-Agent string properties using the User-Agent rules
	 *
	 * @param string $userAgent The User-Agent to find properties for
	 * @param array $idProperties The results of the tree walk, map of property id to value id
	 * @param array $sought A set of properties to return values for
	 * @param boolean $typedValues Whether to return typed values or string values
	 * @return array of properties or NULL if there are no properties
	 */
	public function getProperties($userAgent, $idProperties, $sought, $typedValues) {
		$propsToReturn = null;

		// first check list of items that skip rules - these are typically non-mobile
		// boolean properties such as isBrowser, isBot etc
		if(self::_skipUaRules($idProperties)) {
			return null;
		}

		// now find the rules to run on the UA. This is a two step process.
		// Step 1 identifies the UA type and finds as list of rules to run.
		// Step 2 uses the list of rules to find properties in a UA

		// STEP 1: try and find the rules to run on the UA
		$rulesToRun = $this->_getUaPropertyRules($userAgent, $idProperties);
		
		// STEP 2: try and extract properties using the rules
		if($rulesToRun !== null) {
			$propsToReturn = $this->_extractProperties($rulesToRun, $userAgent, $sought, $typedValues);
		}
		
		return $propsToReturn;
	}
	
	/**
	 * Find all the properties that are used for matching. This is needed in case
	 * the Api.getProperty() function is called as we need these properties for
	 * the User-Agent extraction rules to work correctly.
	 *
	 * @param array $group The rule group that can contain a property matcher
	 * @param array $propIds The set of found property IDs to which new ones are added to
	 * @return An updated set of property IDs
	 */
	protected function _initGetMatcherPropertyIds($group, $propIds) {

		// the properties matcher may not exist....
		if(isset($group[self::PROPERTY_MATCHER])) {
			foreach($group[self::PROPERTY_MATCHER] as $propId => $propVal) {
				if(!isset($propIds[$propId])) {
					$propIds[$propId] = 1;
				}
			}
		}

		return $propIds;
	}

	/**
	 * Prepare the rule set by extracting it from the current group and counting
	 * the items in the group. This is done to avoid counting the items on every
	 * request.
	 * 
	 * @param array $group The current parent group.
	 * @return array A list of all rule sets
	 */
	protected function _initRuleSets($group) {
		// count the number of sets to avoid doing it on every request
//		$sets = $group[self::RULE_SET];
//		$group[self::RULE_SET_COUNT] = count($sets);
		
		return $group[self::RULE_SET];
	}

	/**
	 * Process the regexes by overriding any default ones with API specific regexes.
	 */
	private function _initProcessRegexes() {
		// process regexes...
		if(isset($this->tree[$this->branch][self::REGEXES][Mobi_Mtld_DA_Api::API_ID])) {
			$this->regexes = $this->tree[$this->branch][self::REGEXES][Mobi_Mtld_DA_Api::API_ID];
		} else {
			$this->regexes = $this->tree[$this->branch][self::REGEXES][self::DEFAULT_REGEX_SET];
		}
	}

	/**
	 * This function loops over all the rules in rulesToRun and returns any properties
	 * that match. The properties returned can be typed or strings.
	 *
	 * @param array $rulesToRun The rules to run against the User-Agent to find the properties
	 * @param string $userAgent The User-Agent to find properties for
	 * @param array $sought A set of properties to return values for
	 * @param boolean $typedValues Whether to return typed values or string values
	 * @return array of properties or NULL if there are no properties
	 */
	private function _extractProperties(array $rulesToRun, $userAgent, $sought, $typedValues) {
		$propsToReturn = null;

		// Loop over the rules array, each object in the array can contain 4 items:
		// propertyid, propertyvalue, regexid and regexmatchposition
		foreach($rulesToRun as $ruleDetails) {
			$rulePropId = $ruleDetails[self::PROPERTY];

			// check if we are looking for a specific property, if so and the
			// current rule property id is not it then continue
			if($sought !== null && !isset($sought[$rulePropId])) {
				continue;
			}
			
			// do we have a property we can set without running the regex rule?
			if(isset($ruleDetails[self::PROPERTY_VALUE])) {
				// we have an ID to the value...
				$value = Mobi_Mtld_DA_PropsHandler::_getValue($this->tree, $rulePropId, $ruleDetails[self::PROPERTY_VALUE], $typedValues);
				$propName = Mobi_Mtld_DA_PropsHandler::_propertyFromId($this->tree, $rulePropId);
				$propsToReturn[$propName] = $value;
			} else {
				// otherwise apply the rule to extract the property from the UA
				$regexId = $ruleDetails[self::RULE_REGEX_ID];
				$regex = $this->regexes[$regexId];

				// match the rule and extract the results
				$res = preg_match($regex, $userAgent, $matches);
				if($res) {
					$matchPos = $ruleDetails[self::REGEX_MATCH_POS];
					if(!empty($matches[$matchPos])) {
						$matchRes = $matches[$matchPos];
						// we have the real value but we might want it as a typed item - not that it really matters in PHP!
						$value = $typedValues ? Mobi_Mtld_DA_PropsHandler::_convertToTyped($this->tree, $matchRes, $rulePropId) : $matchRes;
						$propName = Mobi_Mtld_DA_PropsHandler::_propertyFromId($this->tree, $rulePropId);
						$propsToReturn[$propName] = $value;
					}
				}
				
			} // end else
		} // end foreach


		return $propsToReturn;
	}

	/**
	 * Check list of items that skip rules - these are typically non-mobile boolean
	 * properties such as isBrowser, isBot, isCrawler etc
	 *
	 * @param array $idProperties The results of the tree walk, map of property id to value id
	 * @return TRUE if the UA rules are to be skipped, FALSE if they are to be run
	 */
	private function _skipUaRules(&$idProperties) {
		$skip = false;

		foreach($this->tree[$this->branch][self::SKIP_IDS] as $propId) {
			if(isset($idProperties[$propId])) {
				$propVal = Mobi_Mtld_DA_PropsHandler::_getValue($this->tree, $propId, $idProperties[$propId], false);

				if($propVal) {
					$skip = true;
					break;
				}
			}
		}

		return $skip;
	}



	/**
	 * Try and find a set of property extraction rules to run on the User-Agent. This
	 * is done in two ways.
	 *
	 * The first way uses properties found from the tree walk to identify the
	 * User-Agent type. If there are still multiple UA types then refining regexes
	 * can be run.
	 *
	 * If the above approach fails to find a match then fall back to the second way
	 * which uses a more brute regex search approach.
	 *
	 * Once the UA type is known the correct set of property extraction rules can
	 * be returned.
	 *
	 * @param string $userAgent The User-Agent to find properties for
	 * @param array $idProperties The results of the tree walk, map of property id to value id
	 * @return An array of rules to run against the User-Agent or NULL if no rules are found
	 */
	private function _getUaPropertyRules($userAgent, &$idProperties) {

		// Method one - use properties from tree walk to speed up rule search
		$rulesToRun = $this->_findRulesByProperties($userAgent, $idProperties);

		// No match found using the properties so now we loop over all rule groups
		// again and try to use a more brute force attempt to find the rules to run
		// on this user-agent.
		$tempRules = $this->_findRulesByRegex($userAgent);
		if ($tempRules !== null){
			$rulesToRun = array_merge($rulesToRun, $tempRules);
		}

		return $rulesToRun;
	}

	/**
	 * Try and find User-Agent type and thus the rules to run by using the properties
	 * returned from the tree walk. All the properties defined in the property matcher
	 * set must match. If a match is found then the rules can be returned.
	 *
	 * @param string $userAgent The User-Agent to find properties for
	 * @param array $idProperties The results of the tree walk, map of property id to value id
	 * @return An array of rules to run against the User-Agent or NULL if no rules are found
	 */
	private function _findRulesByProperties($userAgent, &$idProperties) {
		$rulesToRunA = array();

		foreach($this->tree[$this->branch][self::RULE_GROUPS] as $group) {

			// check if we have the property match list
			if(!isset($group[self::PROPERTY_MATCHER])) {
				continue;
			}

			// try matching defined properties so we know what rules to run. If there
			// is a match then we can return the rules to run. In some cases we need to
			// refine the match found by running some refining regexes
			$propMatch = self::_checkPropertiesMatch($group[self::PROPERTY_MATCHER], $idProperties);

			if($propMatch) {
//				$ruleSet = $group[self::RULE_SET];
//				$ruleSetCount = $group[self::RULE_SET_COUNT];
				
				// in some cases we have multiple rulesets to choose from, if more
				// than 1 we need to run some additional refining regex rules.
				if(isset($group[self::RULE_SET][1])) {
					$rulesToRun = $this->_findRulesToRunByRegex($userAgent, $group[self::RULE_SET], self::REFINE_REGEX_ID);
				} else {
//					$rulesSet = $group[self::RULE_SET][0]; // 0th item... there should only be one...
					$rulesToRun = $group[self::RULE_SET][0][self::RULE_ARR];
				}
				
				if ($rulesToRun !== null)
					$rulesToRunA = array_merge($rulesToRunA, $rulesToRun);

//				break;
			}

		}
		
		return $rulesToRunA;
	}

	/**
	 * This functions checks all the properties in the property matcher branch of
	 * this rule group. This branch contains a list of properties and their values.
	 * All must match for this function to return true.
	 *
	 * In reality the properties and values are indexes to the main property and
	 * value arrays.
	 *
	 * @param array $propList The list of properties to check for matches
	 * @param array $idProperties The results of the tree walk, map of property id to value id
	 * @return TRUE if ALL properties match, false otherwise
	 */
	private function _checkPropertiesMatch(array $propList, &$idProperties) {
		$propMatch = false;
		
		// loop over propList and try and match ALL properties
		foreach($propList as $propId => $expectedValueId) {

			// get the value found via the tree walk
			if(isset($idProperties[$propId])) {

				// we can speed things up a little by just comparing the IDs!
				if($idProperties[$propId] == $expectedValueId) {
					$propMatch = true; // no break here as we want to check all properties
				} else {
					// there was code here to check actual values if the IDs did not match
					// but is was unnecessary. If the JSON generator is working correctly then 
					// just the ID check is sufficient.
					$propMatch = false;
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
	 * Search for the rules to run by checking the User-Agent with a regex. If there
	 * is a match the rule list is returned.
	 *
	 * @param string $userAgent The User-Agent to find properties for
	 * @return An array of rules to run against the User-Agent or NULL if no rules are found
	 */
	private function _findRulesByRegex($userAgent) {
		$rulesToRun = null;

		foreach($this->tree[$this->branch][self::RULE_GROUPS] as $group){
			$rulesToRun = $this->_findRulesToRunByRegex($userAgent, $group[self::RULE_SET], self::SEARCH_REGEX_ID);
			if ($rulesToRun !== null){
				break;
			}
		}

		return $rulesToRun;
	}




	/**
	 * Loop over a set of refining rules to try and determine the User-Agent type
	 * and so find the rules to run on it.
	 *
	 * @param string $userAgent The User-Agent to find properties for
	 * @param array $ruleSet The ruleset that contains the search regex id, refine regex id and the magical rulesToRun
	 * @param string $type The type of rule to run either Refine or Search
	 * @return An array of rules to run against the User-Agent or NULL if no rules are found
	 */
	private function _findRulesToRunByRegex($userAgent, array $ruleSet, $type) {
		$rulesToRun = null;

		// we want these to run in the order they appear. For some reason the Json
		// class uses a Hashmap to represent an array of items so we have to loop
		// based on the index of the HashMap
		foreach($ruleSet as $set) {

			// get refine / search id to run
			if(isset($set[$type])) {
				// now look up the pattern...
				if(preg_match($this->regexes[$set[$type]], $userAgent)) {
					$rulesToRun = $set[self::RULE_ARR]; // now get the rules to run!
					break;
				}
			}

		}

		return $rulesToRun;
	}
}
