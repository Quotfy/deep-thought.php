<?php
use ExpressiveAnalytics\DeepThought\DTSettings;

class DTSettingsTest extends \PHPUnit_Framework_TestCase{
	
	public function testNewSettings(){
		DTSettings::sharedSettings(array("test"=>true));
		$settings = DTSettings::sharedSettings();
		$this->assertTrue($settings["test"]);
	}
	
	public function testSharedReference(){
		$settings = &DTSettings::sharedSettings();
		$settings["test"] = true;
		$settings = &DTSettings::sharedSettings();
		$this->assertTrue($settings["test"]);
	}
}