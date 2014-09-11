<?php namespace ExpressiveAnalytics\DeepThought\Tests;

use ExpressiveAnalytics\DeepThought\DTSettingsStorage;
use ExpressiveAnalytics\DeepThought\DTMySQLDatabase;

class DTMySQLDatabaseTest extends \PHPUnit_Framework_TestCase{
	protected $db = null;

	public function setup(){
		$settings = &DTSettingsStorage::sharedSettings();
		$settings = array(); //clear out any old shared settings
		DTSettingsStorage::initShared(__DIR__."/storage.json");
		try{
			$this->db = DTSettingsStorage::connect("test-db");
		}catch(\Exception $e){
			$this->markTestSkipped("You must create a valid MySQL connection in '".__DIR__."/storage.json'!");
		}
		
		$this->db->query("CREATE TABLE IF NOT EXISTS dt_unit_test ( id int auto_increment, primary key (id) );");
		$this->db->query("TRUNCATE dt_unit_test;");
		$this->db->query("INSERT INTO dt_unit_test VALUES ();");
		$this->db->query("INSERT INTO dt_unit_test VALUES ();");
	}
	
	public function testDisconnect(){
		$unconnected_db = new DTMySQLDatabase();
		try{
			$unconnected_db->disconnect();
			$this->assertTrue(false,"Expected an exception for unconnected store.");
		}catch(\Exception $e){
			$this->assertTrue(true);
		}
		
		$this->db->disconnect();
		try{
			$this->db->query("SELECT * FROM dt_unit_test");
			$this->assertTrue(false,"Expected to fail out on select.");
		}catch(\Exception $e){
			$this->assertTrue(true);
		}	
	}

	public function testConnect(){
		$this->assertEquals(2,count($this->db->select("SELECT * FROM dt_unit_test")));
	}
	
	public function testQueryAndSelect(){
		$this->assertEquals(2,count($this->db->select("SELECT * FROM dt_unit_test")));
	}
	
	public function testClean(){
		$val = "this unit's clean";
		$this->assertEquals("this unit\'s clean",$this->db->clean($val));
	}
	
	public function testInsertAndLastID(){
		$id = $this->db->insert("INSERT INTO dt_unit_test VALUES ();");
		$this->assertEquals(3,$id);
		$this->assertEquals($id,$this->db->lastInsertID());
	}
	
	public function testPlaceholder(){
		$this->assertEquals("?",$this->db->placeholder($params,"firstval"));
		$this->assertEquals("?",$this->db->placeholder($params,"secondval"));
		$this->assertEquals(2,count($params));
	}
	
	public function testPrepareStatements(){
		$name = "test_prepared_statement";
		$this->db->prepareStatement("SELECT * FROM dt_unit_test",$name);
		$this->assertEquals("test_prepared_statement",$name);
		
		$name = null;
		$prepared = $this->db->prepareStatement("SELECT * FROM dt_unit_test WHERE id=?",$name);
		$this->assertNotNull($name);
		$this->assertTrue($prepared!==false);
		
		try{
			$params = array(1);
			$rows = $this->db->execute($prepared,$params);
			$this->assertEquals(1,count($rows));
		}catch(Exception $e){$this->assertTrue(false,"Unexpected exception during execute().");}
	}
	
	public function testColumnsForTable(){
		$this->assertEquals(array("id"),$this->db->columnsForTable("dt_unit_test"));
	}
	
	public function testAllTables(){
		$this->assertEquals(array("dt_unit_test"),$this->db->allTables());
	}
	
	public function testForInjection(){
		$qb = $this->db->filter(array("id"=>$this->db->clean("\\DTSQLEXPR\\SELECT * FROM test")));
		$this->assertEquals(1,preg_match("/DTSQLEXPR/",$qb->selectStatement()),"Expected cleaned input to treat DTSQLEXPR as a string");
	}
}