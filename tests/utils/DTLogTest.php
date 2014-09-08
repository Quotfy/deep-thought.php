<?php
use ExpressiveAnalytics\DeepThought\DTLog;
use ExpressiveAnalytics\DeepThought\DTSettingsConfig;

class DTLogTest extends \PHPUnit_Framework_TestCase{
	public function testError(){
		DTLog::error("Testing stdout for error log...");
	}
	
	public function testDebug(){
		DTLog::debug("Testing stdout for debug log...",$backtrace);
		$this->assertNotNull($backtrace,"Expected backtrace to be returned.");
	}
	
	public function testFileWrite(){
		DTLog::$debug_fp = fopen(sys_get_temp_dir()."/debug.log","w");
		DTLog::debug(DTLog::colorize("Testing file for log...","error"));
		$config = array();
	}
	
	public function testInfo(){
		DTLog::info("Testing stdout for info log...");
	}
	
	public function testObjectAsMsg(){
		$obj = array("test"=>"success");
		DTLog::info($obj);
	}
	
	public function testFormattedMsg(){
		$obj = array("test"=>"success");
		DTLog::info("The contents of %s look correct",$obj);
	}
}