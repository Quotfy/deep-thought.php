<?php
/**
 * DTSettings
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

class DTSettings{
	private static $shared_settings = array();

	/**
	 * initialize the shared settings from +$path+
	 *	@static
	 *	@param string $path the path to the shared settings
	 *	@retval DTSettings the shared settings
	*/
	public static function initShared($path){
		return static::$shared_settings = new static(json_decode(file_get_contents($path)));
	}
	
	/**
	 * get the shared settings for the concrete subclass
	 * 
	 * @access public
	 * @abstract
	 * @static
	 * @param $settings any new settings that should be applied
	 * @retval DTSettings a reference to the shared settings
	 */
	 public static function &sharedSettings(array $settings=null){
	 	if(isset($settings))
	 		static::$shared_settings = array_merge(static::$shared_settings,$settings);
	 	return static::$shared_settings;
	 }
}