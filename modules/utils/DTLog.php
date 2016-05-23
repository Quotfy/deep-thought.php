<?php
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

class DTLog{
	public static $error_fp = null; ///destination for error messages
	public static $info_fp = null; ///destination for info messages
	public static $debug_fp = null; ///destination for debug messages
	public static $last_backtrace = null;
	public static $colorize = null;
	
	/** emit major failure message */
	public static function error($msg){
		if(!isset(DTLog::$error_fp))
			DTLog::$error_fp = static::openOrCreate("error_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$error_fp,$fmt_msg,"error");
	}
	
	/** emit warnings **/
	public static function warn($msg){
		if(!isset(DTLog::$info_fp))
			DTLog::$info_fp = static::openOrCreate("info_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$info_fp,$fmt_msg,"warn");
	}
	
	/** emit useful information */
	public static function info($msg){
		if(!isset(DTLog::$info_fp))
			DTLog::$info_fp = static::openOrCreate("info_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$info_fp,$fmt_msg,"info");
	}
	
	/** emit success updates **/
	public static function success($msg){
		if(!isset(DTLog::$info_fp))
			DTLog::$info_fp = static::openOrCreate("info_log");
		$fmt_msg = static::formatMessage(func_get_args());
		return DTLog::write(DTLog::$info_fp,$fmt_msg,"success");
	}
	
	/** only emits message if debug */
	public static function debug($msg){
		if(!isset(DTLog::$debug_fp))
			DTLog::$debug_fp = static::openOrCreate("debug_log");;
		$fmt_msg = static::formatMessage(func_get_args());
		$e = new \Exception();
		static::$last_backtrace = $e->getTraceAsString();
		return DTLog::write(DTLog::$debug_fp,$fmt_msg,"debug");
	}
	
	protected static function formatMessage($args){
		$msg = array_shift($args);
		if(!is_string($msg))
			return static::colorize(is_array($msg)?json_encode($msg):(string)$msg,"INFO");
		$strings = array();
		foreach($args as $a)
			$strings[] = static::colorize(is_array($a)?json_encode($a):(string)$a,"INFO");
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
	protected static function write($fp,$msg,$code){
		$bt = debug_backtrace();
		$file = basename($bt[1]["file"]);
		$line = $bt[1]["line"];
		$timestamp = gmdate("D M d H:i:sP Y");
		$msg = is_array($msg)?json_encode($msg):(string)$msg;
		flock($fp,LOCK_EX | LOCK_NB);
		$meta = stream_get_meta_data($fp);
		if (static::$colorize=="pml"){ //get rid of colorize... for now
			$msg = preg_replace("/<dt-color color=[^>]*>/","",$msg);
			$msg = preg_replace("/<\/dt-color>/","",$msg);
			$msg = "[{$timestamp}] {$file}:{$line}:{$code}:{$msg}\n";
		}else if($meta["wrapper_type"]!="PHP" || static::isCLI()){ //wrap with log text
			$msg = preg_replace("/<dt-color color=green>/",chr(27)."[42m",$msg);
			$msg = preg_replace("/<dt-color color=red>/",chr(27)."[41m",$msg);
			$msg = preg_replace("/<dt-color color=yellow>/",chr(27)."[43m",$msg);
			$msg = preg_replace("/<dt-color color=blue>/",chr(27)."[44m",$msg);
			$msg = preg_replace("/<\/dt-color>/",chr(27)."[0m",$msg);
			$msg = "[{$timestamp}] {$file}:{$line}:{$code}:{$msg}\n";
		}else {
			$msg = preg_replace("/<dt-color color=green>/","<span style='background-color: green'>",$msg);
			$msg = preg_replace("/<dt-color color=red>/","<span style='background-color: red'>",$msg);
			$msg = preg_replace("/<dt-color color=yellow>/","<span style='background-color: yellow'>",$msg);
			$msg = preg_replace("/<dt-color color=blue>/","<span style='background-color: lightblue'>",$msg);
			$msg = preg_replace("/<\/dt-color>/","</span>",$msg);
		}
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
		static::$colorize = isset($config["logs"]["colorize"])?$config["logs"]["colorize"]:"pml";
		$file = isset($config,$config["logs"],$config["logs"][$log_type])
			?$config["logs"][$log_type]:"php://output";
		if(!substr_compare("php://", $file, 0)&&!file_exists($file)){
			touch($file);
			chmod($file,$config["logs"]["permissions"]);
		}
		return fopen($file,"a");
	}
	
	public static function colorize($text, $status="INFO") {
		$out = "";
		$status = strtoupper($status);
		switch($status) {
			case "SUCCESS":
				$out = "green";
				break;
			case "FAILURE":
			case "ERROR":
				$out = "red";
				break;
			case "WARN":
			case "WARNING":
				$out = "yellow";
				break;
			case "NOTE":
			case "INFO":
				$out = "blue";
				break;
			default:
				throw new \Exception("Invalid status: " . $status);
		}
		return "<dt-color color={$out}>{$text}</dt-color>";
	}
	
	public static function isCLI(){
		$sapi_type = php_sapi_name();
		return (substr($sapi_type, 0, 3) == 'cli');
	}
	
	public static function lastBacktrace(){
		return static::$last_backtrace;
	}
}