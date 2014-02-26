<?php

/**
 * @package Mobi_Mtld_DA
 * @copyright Copyright (c) 2008-2013 by mTLD Top Level Domain Limited.  All rights reserved.
 * @version 1.3.1
 * 
 * Portions copyright (c) 2008 by Argo Interactive Limited.
 * Portions copyright (c) 2008 by Nokia Inc.
 * Portions copyright (c) 2008 by Telecom Italia Mobile S.p.A.
 * Portions copyright (c) 2008 by Volantis Systems Limited.
 * Portions copyright (c) 2002-2008 by Andreas Staeding.
 * Portions copyright (c) 2008 by Zandan.
 */

error_reporting(E_ALL | E_STRICT);

/**
 * Require: Mobi_Mtld_Da_Api
 */
require_once dirname(__FILE__) . '/Api.php';

/**
 * Provides some simple test harnesses for the DA API.
 * @author MTLD (dotMobi)
 * @version $Id: Test.php 2830 2008-05-13 10:48:55Z ahopebailie $
 */

class Mobi_Mtld_DA_Test
{

	/**
	 * Provides a command line argument to recognise a user agent multiple times.
	 *
	 * @param array $argv
	 */
	public static function main(array $argv)
	{
		array_shift($argv);

		$root_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
		$sample_dir = $root_dir . DIRECTORY_SEPARATOR . 'sample' . DIRECTORY_SEPARATOR;
		$tree_file = $sample_dir . 'json' . DIRECTORY_SEPARATOR . 'Sample.json';

		if (sizeof($argv) < 2) {
			print("Usage: php Test.php [options]");
			print("\r\n");
			print("  options are:\r\n");
			print("    -t \"tree\"      JSON tree file (defaults to $tree_file)\r\n");
			print("    -f \"file\"      recognise user agents in a file\r\n");
			print("    -u \"ua\"        recognise a single user agent (possibly in addition to the file)\r\n");
			print("    -p \"prop\"      return a particular property only, comma separated for more than 1\r\n");
			print("    -r n           repeat the JSON-load-recognition sequence n times\r\n");
			print("    -s m           repeat the recognition sequence m times\r\n");
			print("    -v true|false  verbose logging of properties\r\n");
			print("\r\n");
			print("Example:\r\n");
			print("    php Test.php -r 3 -s 10 -u \"Nokia6680\"\r\n");
			print("This will load up the JSON, and recognise the Nokia6680 10 times. It will repeat the whole sequence 3 times.\r\n");
			print("\r\n");
			print("Available properties (with data types):\r\n");
			try {
			  $tree = Mobi_Mtld_DA_Api::getTreeFromFile($tree_file);
			} catch (Mobi_Mtld_Da_Exception_JsonException $e) {
			  die($e->getMessage());
			}
			print("API revision: " . Mobi_Mtld_DA_Api::getApiRevision() . "\r\n");
			print("Tree revision: " . Mobi_Mtld_DA_Api::getTreeRevision($tree) . "\r\n");
			print("\r\n");

			print_r(Mobi_Mtld_DA_Api::listProperties($tree));
		} else {
			$file = "";
			$ua = "";
			$prop = "";
			$repeat = 1;
			$subrepeat = 1;
			$verbose = false;
			for ($i = 1; $i < sizeof($argv); $i+=2) {
				$flag = $argv[$i-1];
				$value = $argv[$i];
				switch ($flag{1}) {
					case 't':
						$tree_file = $value;
						break;
					case 'f':
						$file = $value;
						break;
					case 'u':
						$ua = $value;
						break;
					case 'p':
						$prop = $value;
						break;
					case 'r':
						$repeat = $value;
						break;
					case 's':
						$subrepeat = $value;
						break;
					case 'v':
						$verbose = $value;
						break;
					default:
						print("Unknown flag: " . $flag . "\r\n");
						return;
						break;
				}
			}

			if($file){
				$user_agents = file($file);
			} else {
				$user_agents = array();
			}
			$user_agents[] = $ua;
			
			$master_start = microtime(true);
			for($r = 0; $r < $repeat; $r++) {
				$load_start = microtime(true);
				try {
  				$tree = Mobi_Mtld_DA_Api::getTreeFromFile($tree_file);
				} catch (Mobi_Mtld_Da_Exception_JsonException $e) {
				  die($e->getMessage());
				}
				$load_end = microtime(true);
				$props = array();
				$prop_count = 0;
				$count_props = 0;
				$recognition_count = 0;
				$total_prop_count = 0;
				$total_prop_available = sizeof(Mobi_Mtld_DA_Api::listProperties($tree));
				$max_prop_count_per_device = 0;
				$min_prop_count_per_device = 10000;
				$no_match_count = 0;

        		$start = microtime(true);

				for($s = 0; $s < $subrepeat; $s++) {
					foreach($user_agents as $user_agent) {
						$user_agent = trim($user_agent);
						if ($user_agent!="") {
							$recognition_count++;
							if ($prop=="") {
								$properties = Mobi_Mtld_DA_Api::getPropertiesAsTyped($tree, $user_agent);
								$prop_count = sizeof($properties) - 2;
							} else {
								$properties = array();
								if ( $count_props == 1 ) {
								    try{
                                        $properties[$prop] = Mobi_Mtld_DA_Api::getProperty($tree, $user_agent, trim($props[0]));
								    } catch (Mobi_Mtld_Da_Exception_InvalidPropertyException $e){
    								    $properties[$prop] = "NULL";
								    }
								} else {
									for($p = 0; $p < count($props); $p++) {
                                        try{
                                            $properties[trim($props[$p])] = Mobi_Mtld_DA_Api::getProperty($tree, $user_agent, trim($props[$p]));
                                        } catch (Mobi_Mtld_Da_Exception_InvalidPropertyException $e){
                                            $properties[trim($props[$p])] = "NULL";
                                        }
									}
								}
								$propCount = count($properties);
							}
							if ($prop_count <= 0) {
								$prop_count = 0;
								$no_match_count++;
							}
							if ($prop_count > $max_prop_count_per_device) {
								$max_prop_count_per_device = $prop_count;
							}
							if ($prop_count < $min_prop_count_per_device) {
								$min_prop_count_per_device = $prop_count;
							}
							$total_prop_count += $prop_count;
						    if ($verbose) {
								print($prop_count . " properties for " . $user_agent . "\r\n");
								print_r($properties);
							}
						}
					}
				}
				$end = microtime(true);
				print("Repeat " . ($r + 1) . ":" . "\r\n");
				print("          Time taken: " . floor(($end - $start)*1000) . "ms" . "\r\n");
				print("   Total user-agents: " . $recognition_count . "\r\n");
				print("   User-agents per s: " . floor($recognition_count / ($end - $start)) . "\r\n");
				print("    Total properties: " . $total_prop_count . "\r\n");
				print("    Properties per s: " . floor($total_prop_count / ($end - $start)) . "\r\n");
				print("   Properties per UA: " . (0.01 * floor((100 * $total_prop_count / $recognition_count))) . "\r\n");
				print("Properties available: " . $total_prop_available . "\r\n");
				print("Max properties found: " . $max_prop_count_per_device . "\r\n");
				print("Min properties found: " . $min_prop_count_per_device . "\r\n");
				print("   Zero property UAs: " . $no_match_count . "\r\n");
				print("  Zero property UA %: " . (0.01 * floor((10000 * $no_match_count / $recognition_count))) . "\r\n");
				print("" . "\r\n");
			}
			$master_end = microtime(true);
			print("" . "\r\n");
			print("    Total time taken: " . floor(($master_end - $master_start) * 1000) . "ms" . "\r\n");
		}
	}

}

Mobi_Mtld_DA_Test::main($_SERVER['argv']);
