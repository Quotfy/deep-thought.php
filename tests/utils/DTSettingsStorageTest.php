<?php namespace ExpressiveAnalytics\DeepThought\Tests;

use ExpressiveAnalytics\DeepThought\DTSettingsStorage;

class DTSettingsStorageTest extends \PHPUnit_Framework_TestCase{
	public function setup(){
		DTSettingsStorage::sharedSettings(array(
			"test"=>array(
				"connector"=>"ExpressiveAnalytics\\DeepThought\\DTSQLiteDatabase",
				"dsn"=>"file://".sys_get_temp_dir()."/test_db.sqlite"),
			"other"=>array(
				"connector"=>"ExpressiveAnalytics\\DeepThought\\DTSQLiteDatabase",
				"dsn"=>"file://".sys_get_temp_dir()."/other_db.sqlite")
			)
		);
	}
	
	public function testConnect(){
		$this->assertNotNull(DTSettingsStorage::connect("test"));
		$this->assertNotNull(DTSettingsStorage::connect("other"));
	}
	
	public function testDefaultStore(){
		$test = DTSettingsStorage::connect("test");
		$this->assertEquals($test,DTSettingsStorage::defaultStore(),"Expected default store to be 'test'.");
	}
}