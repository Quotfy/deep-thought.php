<?php

class DTStorageTest extends \PHPUnit_Framework_TestCase{
	public function setup(){
		$settings = &DTStorage::shared();
		$settings = array(); //clear out any old shared settings
		DTStorage::shared(array(
			"test"=>array(
				"connector"=>"DTSQLiteDatabase",
				"dsn"=>"file://".sys_get_temp_dir()."/test_db.sqlite"),
			"other"=>array(
				"connector"=>"DTSQLiteDatabase",
				"dsn"=>"file://".sys_get_temp_dir()."/other_db.sqlite")
			)
		);
	}

	public function testConnect(){
		$this->assertNotNull(DTStorage::connect("test"));
		$this->assertNotNull(DTStorage::connect("other"));
	}

	public function testDefaultStore(){
		$test = DTStorage::connect("test");
		$this->assertEquals($test,DTStorage::defaultStore(),"Expected default store to be 'test'.");
	}
}
