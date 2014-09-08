<?php namespace ExpressiveAnalytics\DeepThought;
/**
 * DTSettingsConfig
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

class DTSettingsConfig extends DTSettings{
	public static $shared_config = array();
	
	public static function initShared(string $path){
		return static::$shared_config = new static(json_decode(file_get_contents($path)));
	}
	
	public static function &sharedSettings(array $settings=null){
		if(isset($settings))
	 		static::$shared_config = array_merge(static::$shared_config,$settings);
		return static::$shared_config;
	}
	
	/**
	 * gets the URL for a given suffix file using the configured base URL.
	 * 
	 * @access public
	 * @static
	 * @param string $suffix (optional)
	 * @return void
	 */
	public static function baseURL($suffix=''){
		$base = "";
		if(isset(static::$shared_config["base_url"]))  	// grab base_url from the config
			$base = static::$shared_config["base_url"];
		else if (isset($_SERVER['HTTP_HOST'])) 			//otherwise, try to use the default HTTP_HOST
			$base = $_SERVER['HTTP_HOST'];
		if(substr($base, -1)!="/") //make sure we add a trailing slash
			$base .= "/";
		if($base=="/") //we don't have any idea about a base url
			return "/{$suffix}";
		return sprintf(
		    "%s://%s%s",
		    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		    $base,
		    $suffix
	  );
	}
}