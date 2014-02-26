<?php
/**
 * @package Mobi_Mtld_DA
 * @copyright Copyright © 2008 by mTLD Top Level Domain Limited.  All rights reserved.
 * Portions copyright © 2008 by Argo Interactive Limited.
 * Portions copyright © 2008 by Nokia Inc.
 * Portions copyright © 2008 by Telecom Italia Mobile S.p.A.
 * Portions copyright © 2008 by Volantis Systems Limited.
 * Portions copyright © 2002-2008 by Andreas Staeding.
 * Portions copyright © 2008 by Zandan.
 * @version 1.3.1
 */

/**
 * Provides some simple test harnesses for the DA API.
 * @author MTLD (dotMobi)
 * @version $Id: Test.php 2830 2008-05-13 10:48:55Z ahopebailie $
 */

error_reporting(E_ALL | E_STRICT);

/**
 * Require: Mobi_Mtld_Da_Api
 */
require_once dirname(__FILE__) . '/Api.php';

class Mobi_Mtld_DA__Benchmarking
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
			print("    -e \"csv\"      print report in CSV format. Other formats mights be available in the future\r\n");
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
			$export = false;
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
					case 'e':
						$export = $value;
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
				if ( $export ) {
					switch ($export){
						case 'csv':
							$props = explode(",", $prop);
							$count_props = count($props);
							echo '"User-Agent", ';
							foreach($props as $one_prop) {
								$one_prop = trim($one_prop);
								echo "\"$one_prop\", ";
							}
							echo "\r\n";
							break;
						case 'csv-bench':
                            $props = explode(",", $prop);
                            $count_props = count($props);
                            break;
						default:
							print(" API revision: " . Mobi_Mtld_DA_Api::getApiRevision() . "\r\n");
							print("Tree revision: " . Mobi_Mtld_DA_Api::getTreeRevision($tree) . "\r\n");
							print("\r\n");
					}
				}

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
							    $prop_count = 0;
								$properties = array();
								if ( $count_props == 1 ) {
								    try{
                                        $properties[$prop] = Mobi_Mtld_DA_Api::getProperty($tree, $user_agent, trim($props[0]));
                                        $prop_count++;
								    } catch (Mobi_Mtld_Da_Exception_InvalidPropertyException $e){
    								    $properties[$prop] = "NULL";
								    }
								} else {
									for($p = 0; $p < count($props); $p++) {
                                        try{
                                            $properties[trim($props[$p])] = Mobi_Mtld_DA_Api::getProperty($tree, $user_agent, trim($props[$p]));
                                            $prop_count++;
                                        } catch (Mobi_Mtld_Da_Exception_InvalidPropertyException $e){
                                            $properties[trim($props[$p])] = "NULL";
                                        }
									}
								}
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
							if ( $export ) {
								switch ($export) {
									case 'csv':
									    $ua_to_print = str_replace('"', '\"', $user_agent);
										$ua_to_print = str_replace(',', '_', $ua_to_print); //Avoid commas that would break CSV
										echo "\"$ua_to_print\", ";
										foreach($properties as $prop_value) {
											$prop_to_print = str_replace('"', '\"', $prop_value);
											$prop_to_print = str_replace(',', '_', $prop_to_print); //Avoid commas that would break CSV
											echo "\"$prop_to_print\", ";
										}
										echo "\r\n";
										break;
								}
							} else if ($verbose) {
								print($prop_count . " properties for " . $user_agent . "\r\n");
								print_r($properties);
							}
						}
					}
				}
				$end = microtime(true);
				if($export){
					if($export == "csv-bench"){
						print('"PHP (OO)",');
						print('"' . Mobi_Mtld_DA_Api::getApiRevision() . '",');
						print('"' . Mobi_Mtld_DA_Api::getTreeRevision($tree) . '",');
						print('"' . gmdate(DATE_W3C) . '",');
						print('"' . ($r + 1) . "\",");
                        print('"' . (number_format($load_end - $load_start, 3)) . "\",");
                        print('"' . (number_format($end - $start, 3)) . "\",");
                        print('"' . $recognition_count . "\",");
						print('"' . floor($recognition_count / ($end - $start)) . "\",");
						print('"' . $total_prop_count . "\",");
						print('"' . floor($total_prop_count / ($end - $start)) . "\",");
						print('"' . (0.01 * floor((100.0 * $total_prop_count / $recognition_count))) . "\",");
						print('"' . $total_prop_available . "\",");
						print('"' . $max_prop_count_per_device . "\",");
						print('"' . $min_prop_count_per_device . "\",");
						print('"' . $no_match_count . "\",");
						print('"' . (0.01 * floor((10000.0 * $no_match_count / $recognition_count))) . "\"\r\n");					
					}
				} else {
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
			}
			$master_end = microtime(true);
			if(!$export){
				print("" . "\r\n");
				print("    Total time taken: " . floor(($master_end - $master_start) * 1000) . "ms" . "\r\n");
			}
		}
	}

}

Mobi_Mtld_DA__Benchmarking::main($_SERVER['argv']);
?>