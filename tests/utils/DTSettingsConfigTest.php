<?php namespace ExpressiveAnalytics\DeepThought\Tests;

use ExpressiveAnalytics\DeepThought\DTSettingsConfig;

class DTSettingsConfigTest extends \PHPUnit_Framework_TestCase{
	
	public function testBaseURL(){
		$this->assertEquals("/myfile.php",DTSettingsConfig::baseURL("myfile.php"));
	
		$base_url = "test.org/base/url";
		DTSettingsConfig::sharedSettings(array("base_url"=>$base_url));
		$this->assertEquals("http://test.org/base/url/myfile.php",DTSettingsConfig::baseURL("myfile.php"));
	}
}