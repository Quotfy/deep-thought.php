<?php
/**
 * DTSettingsStorage
 *
 * Copyright (c) 2013-2014, Expressive Analytics, LLC <info@expressiveanalytics.com>.
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @package    Deep Thought
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2014 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

class DTSettingsStorage extends DTSettings{
	public static $shared_storage = array();
	protected static $_storage_connections = array(); //internal storage for singleton storage connections
	protected static $_default_store;
	
	public static function initShared($path){
		static::$_storage_connections = array(); //clear this out for new storage
		return static::$shared_storage = json_decode(file_get_contents($path),true);
	}
	
	public static function &sharedSettings(array $settings=null){
		if(isset($settings))
	 		static::$shared_storage = array_merge(static::$shared_storage,$settings);
		return static::$shared_storage;
	}
	
	/**
	 * retrieves/creates a singleton connection from the shared storage settings.
	 * 
	 * @access public
	 * @static
	 * @param string $store the name of the store in shared storage
	 * @retval DTStore a valid connection or throws an exception
	 */
	public static function connect($store){
		if(!isset(static::$_storage_connections[$store]) || static::$_storage_connections[$store]->conn==null){
			$storage = static::sharedSettings();
			if(!isset($storage,$storage[$store]))
				throw new \Exception("Connection '{$store}' not found in storage!");
			$connector = $storage[$store]["connector"];
			$dsn = $storage[$store]["dsn"];
			$readonly = isset($storage[$store]["readonly"])?$storage[$store]["readonly"]:false;
			static::$_storage_connections[$store] = new $connector($dsn,$readonly);
		}
		return static::$_storage_connections[$store];
	}
	
	/**
	 * returns the default store (the first store listed in storage)
	 * 
	 * @access public
	 * @static
	 * @param DTStore optionally sets the default store
	 * @return DTStore the default store
	 */
	public static function defaultStore(DTStore $default=null){
		if(isset($default))
			return static::$_default_store=$default;
		if(!isset(static::$_default_store)){
			$storage = static::sharedSettings();
			if($storage==null)
				return null;
			$store_names = array_keys($storage);
			static::$_default_store = static::connect($store_names[0]);
		}
		return static::$_default_store;
	}
}