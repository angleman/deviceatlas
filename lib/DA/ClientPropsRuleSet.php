<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

class Mobi_Mtld_DA_ClientPropsRuleSet {
	private $userAgent;
	private $ruleSet;

	public function __construct($userAgent, array $ruleSet) {
		$this->userAgent = $userAgent;
		$this->ruleSet = $ruleSet;
	}
	
	public function getUserAgent() {
		return $this->userAgent;
	}
	
	public function getRuleSet() {
		return $this->ruleSet;
	}
}
