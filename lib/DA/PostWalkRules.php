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
abstract class Mobi_Mtld_DA_PostWalkRules {

	const RULE_GROUPS = "rg";
	const PROPERTY_MATCHER = "p";
	const PROPERTY = "p";
	const PROPERTY_VALUE = "v";
	const OPERATOR = "o";
	const RULE_ARR = "r";
	const RULE_PROP_IDS_IN_USE = "rpids";  // calculated in init
	const MATCHER_PROP_IDS_IN_USE = "mpids";  // calculated in init
	const RULE_SET = "t";
	const RULE_SET_COUNT = "tc"; // calculated in init

	protected $tree;
	protected $branch; // it stores only the branch name - we can't affor a double tree reference in php
	protected $propertyNameToId;
	protected $propertyIdToType;
	private $propMatcherIdsInUse; // propertyId => 1
	private $rulePropIdsInUse; // propertyId => 1

	public function __construct(array &$tree, $type) {
		$this->tree = &$tree; // main branch
		$this->branch = $type;

		$this->propMatcherIdsInUse = array();
		$this->rulePropIdsInUse = array();

		$this->_init();
	}

	/**
	 * 
	 */
	private function _init() {
		$this->propertyNameToId = $this->tree[Mobi_Mtld_DA_Api::PROPERTIES_WITHOUT_TYPE_TO_ID]; // property name -> id
		$this->propertyIdToType = $this->tree[Mobi_Mtld_DA_Api::ID_TO_PROPERTIES_WITH_TYPE]; // property id -> type
		// loop over all rule groups
		foreach ($this->tree[$this->branch][self::RULE_GROUPS] as $group) {
			
			// We want to keep a list of all the properties that are used because when
			// a user calls getProperty we need to fetch additional properties other than
			// the property they want to optimize the User-Agent string rules.
			$this->propMatcherIdsInUse = $this->_initGetMatcherPropertyIds($group, $this->propMatcherIdsInUse);
			$sets = $this->_initRuleSets($group);

			// also keep a list of all the property IDs that can be output
			$this->rulePropIdsInUse = $this->_initGetRulePropertyIds($sets, $this->rulePropIdsInUse);
		}
	}

	/**
	 * Find all the properties that are used in the final rules. This is needed to
	 * optimise the Api.getProperty() function.
	 *
	 * @param array $sets The rule set from the main rule group
	 * @param array $rulePropIds The list of found property IDs
	 * @return An updated set of property IDs
	 */
	private function _initGetRulePropertyIds(array $sets, $rulePropIds) {
		// loop over all items in the rule set and find all the property ids
		// used in the rules
		foreach ($sets as $items) {

			// now loop over the actual rule array
			foreach ($items[self::RULE_ARR] as $ruleDetails){
				$propId = $ruleDetails[self::PROPERTY];
				if (!isset($rulePropIds[$propId])) {
					$rulePropIds[$propId] = 1;
				}
			}
		}

		return $rulePropIds;
	}
	
	/**
	 * Find all the properties that are used for matching. This is needed in case
	 * the Api.getProperty() function is called as we need these properties for
	 * the rules to work correctly
	 * 
	 * @param array $group The rule group that can contain a property matcher
	 * @param array $propIds The list of found property IDs
	 * @return An updated set of property IDs
	 */
	protected abstract function _initGetMatcherPropertyIds($group, $propIds);

	/**
	 * Prepare the rule set
	 * 
	 * @param array $group The current parent group.
	 * @return A list of all rule sets
	 */
	protected abstract function _initRuleSets($group);

	/**
	 * Check if the property is used in the rules and so can be found from them.
	 * This is used in Api.getProperty() to avoid calling the methods in the class
	 * if the property that is being looked for cannot be found here.
	 * 
	 * @param integer $propertyId The ID of the property that is sought
	 * @return TRUE if the propertyId is used, FALSE otherwise
	 */
	public function propIsOutput($propertyId) {
		return isset($this->rulePropIdsInUse[$propertyId]);
	}

	/**
	 * Get a list of all the required properties that are needed for this class
	 * to properly run its rules.
	 * 
	 * structure:
	 * 
	 * $propertyId => 1
	 * 
	 * Used with $sought
	 * 
	 * @return The list of required properties.
	 */
	public function getRequiredProperties() {
		return $this->propMatcherIdsInUse;
	}
}