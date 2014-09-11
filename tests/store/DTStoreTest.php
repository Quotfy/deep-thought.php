<?php
use \ExpressiveAnalytics\DeepThought\DTLog;
use \ExpressiveAnalytics\DeepThought\DTStore;

class DTStoreTest extends \PHPUnit_Framework_TestCase{
	
//! Date methods
///@name Date methods
///@{
	public function testNow(){
		$this->assertEquals(time(),strtotime(DTStore::now()),"'Now' is too distant.",1);
	}
	
	public function testDate(){
		$tz = date_default_timezone_get();
		date_default_timezone_set("America/Chicago"); //assume we are in Chicago
		$time = strtotime("January 1 1970 00:00:00");
		$this->assertEquals("1970-01-01 00:00:00 America/Chicago",DTStore::date($time));
		date_default_timezone_set($tz);
	}
	
	public function testGMDate(){
		$tz = date_default_timezone_get();
		date_default_timezone_set("America/Chicago"); //assume we are in Chicago
		$time = strtotime("January 1 1970 00:00:00");
		$this->assertEquals("1970-01-01 06:00:00 UTC",DTStore::gmdate($time));
		date_default_timezone_set($tz);
	}
	
	public function testLocalizedDateTime(){
		$ts = strtotime("January 1 1970 00:00:00");
		$this->assertNotNull(DTStore::localizedDate());
		$this->assertNotNull(DTStore::localizedDate($ts));
		$this->assertNotNull(DTStore::localizedTime());
		$this->assertNotNull(DTStore::localizedTime($ts));
	}
///@}
}