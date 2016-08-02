<?php

class DTSettingsTest extends \PHPUnit_Framework_TestCase{

	public function testNewSettings(){
		DTSettings::shared(array("test"=>true));
		$settings = DTSettings::shared();
		$this->assertTrue($settings["test"]);
	}

	public function testSharedReference(){
		$settings = &DTSettings::shared();
		$settings["test"] = true;
		$settings = &DTSettings::shared();
		$this->assertTrue($settings["test"]);
	}
}
