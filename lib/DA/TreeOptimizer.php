<?php

/*
 *  @copyright Copyright © 2013 dotMobi. All rights reserved.
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Api.php';

/**
 * This class optimizes loading of the main JSON tree.
 * 
 * Optimization 1: we split main JSON tree based on the first character
 * in the User-Agent string. It creates several smaller json files
 * which are saved in the system temp directory.
 * 
 * There might be more optimizations coming in the future releases
 *
 * @author dotMobi
 */
class Mobi_Mtld_DA_TreeOptimizer {

	const SHARD_PREFIX = 'json.';
	
	/**
	 * When changing from true to false and vice versa
	 * be sure you clear your Mobi_Mtld_DA_TreeOptimizer cache directory.
	 * 
	 * Linux: /tmp/Mobi_Mtld_DA_TreeOptimizer
	 */
	static $use_subtree_optimization = true;
	
	/**
	 * Do <b>NOT</b> add any new paths to the list
	 * unless you are 110% aware how the subtree optimization works!
	 */
	static $subtree_optimization = array (
		77 => array(
			'iphone' => 'Mozilla/5.0 (i',
			'linux' => 'Mozilla/5.0 (Linux',
		)
	);
	
	/**
	 * Returns optimized tree from a JSON file
	 * Use an absolute path name to be sure of success if the current working directory is not clear.
	 * 
	 * @param string $path_to_json The location of the file to read in.
	 * @param string $user_agent string from the device's User-Agent header
	 * @param string $cache_dir uses sys_get_temp_dir() by default
	 * @return array tree
	 */
	public static function getTreeFromFile($path_to_json, $user_agent, $cache_dir = null) {
		
		// Check TreeOptimizer Cache
		$path_to_shard = self::_getTempRespositoryPath($cache_dir, true).self::getShardId($user_agent);
		if (self::_isShardUpToDate($path_to_shard, $path_to_json)){
			return json_decode(file_get_contents($path_to_shard), true);
		}

		// Split the original JSON file
		return self::_splitJsonFileByFirstLetter($path_to_json, $user_agent, $cache_dir);
	}

	/**
	 * Checks if we have up-to-date TreeOptimizer cache
	 * 
	 * @param path $cache_dir
	 * @param string $branch
	 * @return boolean
	 */
	private static function _isShardUpToDate($path_to_shard, $path_to_json){
		if (file_exists($path_to_shard)){
			if (filemtime($path_to_shard) > filemtime($path_to_json)){
				return true;
			}
		}
		return false;
	}

	private static function _getTempRespositoryPath($cache_dir, $with_prefix = false){
		if (is_null($cache_dir)){
			$cache_dir = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.__CLASS__;
		} else {
			$cache_dir = rtrim($cache_dir, '/\\');
		}
		return ($with_prefix) ? $cache_dir.DIRECTORY_SEPARATOR.self::SHARD_PREFIX : $cache_dir;
	}
	
	private static function _splitJsonFileByFirstLetter($path_to_json, $user_agent, $cache_dir) {
		// Load the original tree
		$tree = Mobi_Mtld_DA_Api::getTreeFromFile($path_to_json, true);
		$first_letter_value = self::_firstLetterValue($user_agent);
		$repository_path = self::_getTempRespositoryPath($cache_dir, true);
		$res = self::_createShard($tree, $first_letter_value, $repository_path, $user_agent);
		if ($res === true){
			return $tree;
		} else {
			return json_decode(file_get_contents($res), true);
		}
	}
	
	private static function _createShard(array &$tree, $first_letter_value, $repository_path, $user_agent = null) {
		static $lock_expiry = 10; // in sec.

		// Paths
		$filename_alt = $repository_path.$first_letter_value;
		$tree_deputy_filename = null;

		// Check lock
		$lock_file = $filename_alt.'.lock';
		if (file_exists($lock_file)){
			$mtime = @filemtime($lock_file);
			if($mtime + $lock_expiry > time()) {
				// There is probably an active lock, do nothing
				return true;
			}
		}
		
		// Create lock
		@file_put_contents($lock_file, '', LOCK_EX);

		// Find PostWalk User-Agents
		$fake_uas = array();
		if (isset($tree['cpr'])){
			$fake_uas = self::_findPostWalkUAs($tree['cpr']);
			foreach(array_keys($fake_uas) as $fake_ua){
				Mobi_Mtld_DA_Api::_seekPostWalkProperties($tree['t'], $fake_ua, $fake_uas[$fake_ua]);
			}
		}

		// Analyze structure
		foreach ($tree['t']['c'] as $key => &$branch){
			if (self::_firstLetterValue($key) != $first_letter_value){
				unset($tree['t']['c'][$key]);
			}
		}

		// Repository
		$dirname = dirname($repository_path);
		if (!is_dir($dirname)){
			@mkdir($dirname, 0755, true);
		}

		// Subtree optimization
		if (self::$use_subtree_optimization) {
			if (isset(self::$subtree_optimization[$first_letter_value])){
				foreach (self::$subtree_optimization[$first_letter_value] as $subtree_key => $subtree_path){
					
					// Clone a tree
					$subtree = $tree;
					unset($subtree['t']['c']);
					$matched = '';
					self::_extractSubTree($tree['t'], $subtree['t'], $subtree_path, $matched);
					
					// Clone PostWalk User-Agents
					if (isset($tree['cpr'])){
						$subtree_fake_uas = $fake_uas;
						foreach(array_keys($subtree_fake_uas) as $fake_ua){
							$length = min(strlen($fake_ua), strlen($subtree_path));
							if (substr($fake_ua, 0, $length) == substr($subtree_path, 0, $length)){
								unset($subtree_fake_uas[$fake_ua]);
							}
						}
					}
					
					// Save optimized subtree
					self::_optimizeTree($subtree, $subtree_fake_uas);
					$filename_alt_subtree = $filename_alt.'.'.$subtree_key;
					file_put_contents($filename_alt_subtree, json_encode($subtree), LOCK_EX);
					$subtree = '';

					// Check current
					if (!is_null($user_agent) && $subtree_path == substr($user_agent, 0, strlen($subtree_path))){
						$tree_deputy_filename = $filename_alt_subtree;
					}
				}
			}
		}
		
		// Remove unused PostWalk User-Agents
		if (isset($tree['cpr'])){
			foreach(array_keys($fake_uas) as $fake_ua){
				if (self::_firstLetterValue($fake_ua) == $first_letter_value){
					unset($fake_uas[$fake_ua]);
				}
			}
		}
		
		// Save optimized tree
		self::_optimizeTree($tree, $fake_uas);
		file_put_contents($filename_alt, json_encode($tree), LOCK_EX);
		
		// Remove lock
		@unlink($lock_file);
		
		// Return
		if (is_null($tree_deputy_filename)){
			return true;
		} else {
			$tree = '';
			return $tree_deputy_filename;
		}
	}
	
	private static function _extractSubTree(&$node, &$subtree, $string, &$matched){

		// Copy values
		if (isset($node['d'])){
			$subtree['d'] = $node['d'];
		}
		
		// Copy masks
		if (isset($node['m'])){
			$subtree['m'] = $node['m'];
		}
		
		// Seek the subtree
		if (isset($node['c'])) {
			$subtree['c'] = array();
			for($c = 1; $c < strlen($string) + 1; $c++) {
				$seek = substr($string, 0, $c);
				if(isset($node['c'][$seek])) {
					$matched .= $seek;
					$subtree['c'][$seek] = array();
					if ($string == $seek){
						// Final
						$subtree['c'][$seek] = $node['c'][$seek];
						unset($node['c'][$seek]);
						return;
					} else {
						self::_extractSubTree($node['c'][$seek], $subtree['c'][$seek], substr($string, $c), $matched);
					}
				}
			}
		}
	}

	private static function _optimizeTree(&$tree, $fake_uas = array()) {
		
		// UAR
		static $uar_values = null;
		if (is_null($uar_values)){
			$uar_values = self::_findPostWalkValues($tree['uar']);
		}
		
		// CPR
		static $cpr_values = null;
		if (isset($tree['cpr'])){
			if (is_null($cpr_values)){
				$cpr_values = self::_findPostWalkValues($tree['cpr'], true);
			}

			// Merge in fake UAs from the PostWalk rules
			if (!empty($fake_uas)){
				foreach ($fake_uas as $fake_ua => $d_node){
					$tree['t']['c'][$fake_ua]['d'] = $d_node;
				}
			}
		}
		
		// Remove unused values
		$used_values = array_unique(array_merge(self::_findValues($tree['t']['c']), $uar_values, $cpr_values));
		sort($used_values);
		$used_values = array_flip($used_values);
		foreach ($tree['v'] as $key => $value){
			if (!isset($used_values[$key])){
				unset($tree['v'][$key]);
			}
		}
		
		// Reset IDs
		$tree['v'] = array_values($tree['v']);

		// Fix IDs
		self::_fixUsedValues($tree['t']['c'], $used_values);
		self::_fixUsedValuesInPostWalk($tree['uar'], $used_values);
		if (isset($tree['cpr'])){
			self::_fixUsedValuesInPostWalk($tree['cpr'], $used_values, true);
		}
	}

	private static function _findValues(&$tree) {
		$ids = array();
		foreach ($tree as $key => $value){
			if (isset($tree[$key]['c'])){
				$ids = array_merge($ids, self::_findValues($tree[$key]['c']));
			}
			if (isset($tree[$key]['d'])) {
				$ids = array_merge($ids, array_values($tree[$key]['d']));
			}
			// There is no need to include the masks as all of them should occur in the 'd'
		}
		return array_unique($ids);
	}

	/**
	 * 
	 * @param array $tree
	 * @param booloean $ignore_p_arrays - cpr optimization
	 * @return array
	 */
	private static function _findPostWalkValues(&$tree, $ignore_p_arrays = false) {
		$ids = array();
		foreach ($tree as $key => $value){
			if (($key === 'p') && is_array($value) && !$ignore_p_arrays) {
				$ids = array_merge($ids, array_values($value));
			} elseif (($key === 'v') && is_numeric($value)){
				$ids[] = $value;
			} elseif (is_array($tree[$key])){
				$ids = array_merge($ids, self::_findPostWalkValues($tree[$key], $ignore_p_arrays));
			}
		}
		return array_unique($ids);
	}

	private static function _findPostWalkUAs(&$tree) {
		$uas = array();
		foreach ($tree as $key => $value){
			if ($key === 'ua') {
				if (!isset($uas[$value])){
					$uas[$value] = array();
				}
			} elseif (is_array($tree[$key])){
				$uas += self::_findPostWalkUAs($tree[$key]);
			}
		}
		return $uas;
	}
	
	private static function _fixUsedValues(&$tree, &$used_values) {
		foreach ($tree as $key => &$value){
			if (isset($tree[$key]['c'])){
				self::_fixUsedValues($tree[$key]['c'], $used_values);
			}

			// Values
			if (isset($tree[$key]['d'])) {
				foreach ($tree[$key]['d'] as $property_id => $value_id){
					$tree[$key]['d'][$property_id] = $used_values[$value_id];
				}
			}

			// Masks
			if (isset($tree[$key]['m'])) {
				foreach ($tree[$key]['m'] as $property_id => $value_id){
					$tree[$key]['m'][$property_id] = $used_values[$value_id];
				}
			}
		}
	}

	private static function _fixUsedValuesInPostWalk(&$tree, &$used_values, $ignore_p_arrays = false) {
		foreach ($tree as $key => $value){
			if (($key === 'p') && is_array($value) && !$ignore_p_arrays) {
				foreach ($tree[$key] as $property_id => $value_id){
					$tree[$key][$property_id] = $used_values[$value_id];
				}
			} elseif (($key === 'v') && is_numeric($value)){
				$tree[$key] = $used_values[$value];
			} elseif (is_array($tree[$key])){
				self::_fixUsedValuesInPostWalk($tree[$key], $used_values, $ignore_p_arrays);
			}
		}
	}
	
	private static function _firstLetterValue($key){
		return ord(substr($key, 0, 1));
	}
	
	public static function getShardId($user_agent){
		$first_letter_value = self::_firstLetterValue($user_agent);
		if (self::$use_subtree_optimization){
			if (isset(self::$subtree_optimization[$first_letter_value])){
				foreach (self::$subtree_optimization[$first_letter_value] as $subtree_key => $subtree_path){
					if ($subtree_path == substr($user_agent, 0, strlen($subtree_path))){
						return $first_letter_value.'.'.$subtree_key;
					}
				}
			}
		}
		
		return $first_letter_value;
	}
	
	public static function populateCache($path_to_json, $cache_dir = null, $force = false){

		// Load the original tree
		$tree = Mobi_Mtld_DA_Api::getTreeFromFile($path_to_json, true);
		$branches = array_keys($tree['t']['c']);
		sort($branches);
		$last_branch = '';
		
		// Creates tree shards
		$repository_path = self::_getTempRespositoryPath($cache_dir, true);
		foreach ($branches as $branch_name){
			$first_letter_value = self::_firstLetterValue($branch_name);
			if ($first_letter_value !== $last_branch){
				if (!$force){
					$path_to_shard = $repository_path.self::getShardId($branch_name);
					if (self::_isShardUpToDate($path_to_shard, $path_to_json)){
						// Nothing to do...
						continue;
					}
				}
				$tree_copy = $tree;
				self::_createShard($tree_copy, $first_letter_value, $repository_path);
				$last_branch = $first_letter_value;
			}
		}

		return $tree;
	}

	public static function clearCache($cache_dir = null){

		// open the directory
		$dir = self::_getTempRespositoryPath($cache_dir);
		$handle = opendir($dir);
		while (FALSE !== ($item = readdir($handle))){
			if($item != '.' && $item != '..'){
				$path = $dir.DIRECTORY_SEPARATOR.$item;
				if(!is_dir($path)){
					// check filename
					if(preg_match("/^json\.\d+(\.[a-z]+)?(\.lock)?$/", $item)){
						@unlink($path);
					}
				}
			}
		}

		// close the directory
		closedir($handle);
	}
}