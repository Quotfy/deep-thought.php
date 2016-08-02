<?php
/**
 * DTSettings
 *
 * Copyright (c) 2013-2016, Expressive Analytics, LLC <info@expressiveanalytics.com>.
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
 * @package    Deep-Thought
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2016 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 2.0.0
 */

class DTSettings{
	/// @var dictionary $shared_settings the set of shared namespaces
	protected static $shared_settings = array();
	/// @var string $namespace override to create dedicated namespaces
	public static $namespace = "dt";

	/**
	 * read the settings from +$path+ into the namespace
	 * @var string $path the path to the settings file
	 * @retval dictionary the shared settings
	 */
	public static function read($path){
		return static::shared(static::unserialize(file_get_contents($path)));
	}

	/**
	 * loads settings from $str
	 * @var string $str the settings to unserialize
	 * @retval dictionary the unserialized settings
	 */
	public static function unserialize($str){
		return json_decode($str,true);
	}

	/**
	 * get the shared settings from the designated namespace
	 * @var dictionary $settings any new settings that should be applied
	 * @retval DTSettings a reference to the shared settings
	 */
	public static function &shared(array $settings=null){
		if(isset($settings))
			static::$shared_settings[static::$namespace] =
				array_merge(static::$shared_settings,$settings);
		return static::$shared_settings[static::$namespace];
	}
}
