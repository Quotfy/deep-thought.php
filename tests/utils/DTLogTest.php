<?php

class DTLogTest extends \PHPUnit_Framework_TestCase{
	public function testError(){
		DTLog::$error_fp = fopen("/dev/null","w");
		$err_msg = DTLog::error("Testing stdout for error log...");
		$this->assertTrue(strpos($err_msg, "Testing stdout for error log...")!=false);
	}
	
	public function testDebug(){
		DTLog::$debug_fp = fopen("/dev/null","w");
		DTLog::debug("Testing stdout for debug log...");
		$debug_msg = DTLog::lastBacktrace();
		$this->assertTrue(strpos($debug_msg, "Testing stdout for debug log...")!=false);
		$this->assertNotNull($backtrace,"Expected backtrace to be returned.");
	}
	
	public function testFileWrite(){
		DTLog::$debug_fp = fopen(sys_get_temp_dir()."/debug.log","w");
		$debug_msg = DTLog::debug(DTLog::colorize("Testing file for log...","error"));
		$this->assertTrue(strpos($debug_msg,"Testing file for log...")!=false);
	}
	
	public function testInfo(){
		DTLog::$info_fp = fopen("/dev/null","w");
		$info_msg = DTLog::info("Testing stdout for info log...");
		$this->assertTrue(strpos($info_msg,"Testing stdout for info log...")!=false);
	}
	
	public function testObjectAsMsg(){
		$obj = array("test"=>"success");
		$info_msg = DTLog::info($obj);
		$this->assertTrue(strpos($info_msg,"{\"test\":\"success\"}")!=false);
	}
	
	public function testFormattedMsg(){
		$obj = array("test"=>"success");
		$info_msg = DTLog::info("The contents of %s look correct",$obj);
		DTLog::info($info_msg);
		$this->assertTrue(preg_match("/The contents of .*{\"test\":\"success\"}.* look correct/",$info_msg)!=false);
	}
}