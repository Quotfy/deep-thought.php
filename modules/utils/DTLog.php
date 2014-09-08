<?php namespace ExpressiveAnalytics\DeepThought;
/**
 * DTLog
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
 
use ExpressiveAnalytics\DeepThought\DTSettingsConfig;

class DTLog{
	public static $error_fp = null; ///destination for error messages
	public static $info_fp = null; ///destination for info messages
	public static $debug_fp = null; ///destination for debug messages
	
	/** emit major failure message */
	public static function error($msg){
		if(!isset(DTLog::$error_fp))
			DTLog::$error_fp = static::openOrCreate("error_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$error_fp,$fmt_msg);
	}
	
	/** currently an alias for info **/
	public static function warn($msg){
		return DTLog::info($msg);
	}
	
	/** emit warnings/information */
	public static function info($msg){
		if(!isset(DTLog::$info_fp))
			DTLog::$info_fp = static::openOrCreate("info_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$info_fp,$fmt_msg);
	}
	
	/** only emits message if debug */
	public static function debug($msg,&$backtrace=null){
		if(!isset(DTLog::$debug_fp))
			DTLog::$debug_fp = static::openOrCreate("debug_log");;
		$fmt_msg = static::formatMessage(func_get_args());
		$e = new \Exception();
		$backtrace = $e->getTraceAsString();
		return DTLog::write(DTLog::$debug_fp,$fmt_msg);
	}
	
	protected static function formatMessage($args){
		$msg = array_shift($args);
		if(!is_string($msg))
			return static::colorize(is_array($msg)?json_encode($msg):(string)$msg,"INFO");
		$strings = array_map(function($a){
			return static::colorize(is_array($a)?json_encode($a):(string)$a,"INFO");
		},$args);
		return vsprintf($msg,$strings);
	}
	
	/**
	 * write a message to the given file.
	 * 
	 * @access protected
	 * @static
	 * @param resource $fp
	 * @param string $msg
	 * @return void
	 */
	protected static function write($fp,$msg){
		$bt = debug_backtrace();
		$file = basename($bt[1]["file"]);
		$line = $bt[1]["line"];
		$timestamp = date("D M d H:i:s Y");
		$msg = is_string($msg)?$msg:json_encode($msg);
		flock($fp,LOCK_EX);
		if(static::isCLI()) //wrap with log text
			$msg = "[{$timestamp}] {$file}:{$line}:{$msg}\n";
		if(fwrite($fp,$msg)===false)
			error_log("DTLog::write():Could not write to log!");
		flock($fp,LOCK_UN);
		return $msg;
	}
	
	/**
	 * openOrCreate function.
	 * 
	 * @access protected
	 * @static
	 * @param string $log_type either "info_log","error_log", or "debug_log"
	 * @return resource the created/opened file
	 */
	protected static function openOrCreate($log_type){
		$config = DTSettingsConfig::sharedSettings();
		$file = isset($config,$config["logs"],$config["logs"][$log_type])
			?$config["logs"][$log_type]:"php://output";
		if(!substr_compare("php://", $file, 0)&&!file_exists($file)){
			touch($file);
			chmod($file,$config["logs"]["permissions"]);
		}
		return fopen($file,"a");
	}
	
	public static function colorize($text, $status) {
		$out = "";
		$cli = static::isCLI();
		$status = strtoupper($status);
		switch($status) {
			case "SUCCESS":
				$out = $cli?"[42m":"green";
				break;
			case "FAILURE":
			case "ERROR":
				$out = $cli?"[41m":"red";
				break;
			case "WARN":
			case "WARNING":
				$out = $cli?"[43m":"yellow";
				break;
			case "NOTE":
			case "INFO":
				$out = $cli?"[44m":"blue";
				break;
			default:
				throw new \Exception("Invalid status: " . $status);
		}
		if($cli)
			return chr(27)."{$out}{$text}".chr(27)."[0m";
		else
			return "<span style='background:{$out}'>{$text}</span>";
	}
	
	public static function isCLI(){
		$sapi_type = php_sapi_name();
		return (substr($sapi_type, 0, 3) == 'cli');
	}
}