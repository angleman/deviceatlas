<?php

/**
 * @copyright Copyright (c) 2008-2013 by mTLD Top Level Domain Limited.  All rights reserved.
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
 * The JsonException is thrown by the Api class.
 * When there is an error parsing the Json.
 * 
 */
class Mobi_Mtld_Da_Exception_JsonException extends Exception {
    /**
     * PHP versions earlier than 5.2.3 are unable to parse JSON data as deep as the Device Atlas tree requires.
     * You will need to upgrade to PHP version 5.2.3 or later.
     */
	const  PHP_VERSION = 1;
    /**
     * The internal PHP function json_decode was unable to decode the supplied JSON.
     * Possible reasons for this may be that the data is corrupt. Ensure you have a complete version of the latest JSON data.
     */
	const  JSON_DECODE = 2;
	/**
	 * The data stored in the JSON cannot be used to build a valid Device Atlas data tree.
	 */
	const  BAD_DATA = 3;
    /**
     * The JSON data you are using is to old for this API.
     * Download a more recent version of the data.
     */
	const  JSON_VERSION = 4;
    /**
     * The path to the JSON file that was given cannot be resolved.
     * Ensure you have supplied the correct path.
     * Use an absolute pathname where you are unsure of the current working directory.
     */
	const  FILE_ERROR = 5;
    /**
     * The JSON file does not contain the Client Properties Section.
     * Ensure you have a valid JSON file.
     */
	const  NO_CLIENT_PROPERTIES_SECTION = 6;
}
