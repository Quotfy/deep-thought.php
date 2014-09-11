<?php namespace ExpressiveAnalytics\DeepThought\Tests;

use ExpressiveAnalytics\DeepThought\DTSettingsStorage;
use ExpressiveAnalytics\DeepThought\DTSQLiteDatabase;

class DTSQLiteDatabaseTest extends \PHPUnit_Framework_TestCase{
	protected $db = null;

	public function setup(){
		$this->db = DTSQLiteDatabase::init("CREATE TABLE test ( id int ); INSERT INTO test VALUES (1); INSERT INTO test VALUES (2);");
	}
	
	public function testDisconnect(){
		$unconnected_db = new DTSQLiteDatabase();
		try{
			$unconnected_db->disconnect();
			$this->assertTrue(false,"Expected an exception for unconnected store.");
		}catch(\Exception $e){
			$this->assertTrue(true);
		}
		
		$this->db->disconnect();
		try{
			$this->db->query("SELECT * FROM test");
			$this->assertTrue(false,"Expected to fail out on select.");
		}catch(\Exception $e){
			$this->assertTrue(true);
		}	
	}

	public function testConnect(){
		$path = dirname(__FILE__)."/test.sqlite";
		if(file_exists($path))
			unlink($path);
		$dsn = "file://{$path}";
		$db = new DTSQLiteDatabase($dsn);
		$db->query("CREATE TABLE test ( id int ); INSERT INTO test VALUES (1); INSERT INTO test VALUES (2);");
		$this->assertEquals(2,count($db->select("SELECT * FROM test")));
		unlink($path);
	}
	
	public function testQueryAndSelect(){
		$db = DTSQLiteDatabase::init();
		$db->query("CREATE TABLE test ( id int ); INSERT INTO test VALUES (1); INSERT INTO test VALUES (2);");
		$this->assertEquals(2,count($db->select("SELECT * FROM test")));
	}
	
	public function testClean(){
		$val = "this unit's clean";
		$this->assertEquals("this unit''s clean",$this->db->clean($val));
	}
	
	public function testInsertAndLastID(){
		$db = DTSQLiteDatabase::init();
		$this->assertEquals(0,$db->lastInsertID());
		
		$id = $this->db->insert("INSERT INTO test VALUES (3)");
		$this->assertEquals(3,$id);
		$this->assertEquals($id,$this->db->lastInsertID());
	}
	
	public function testPlaceholder(){
		$this->assertEquals(":1",$this->db->placeholder($params,"firstval"));
		$this->assertEquals(":2",$this->db->placeholder($params,"secondval"));
		$this->assertEquals(2,count($params));
	}
	
	public function testPrepareStatements(){
		$name = "test_prepared_statement";
		$this->db->prepareStatement("SELECT * FROM test",$name);
		$this->assertEquals("test_prepared_statement",$name);
		
		$name = null;
		$prepared = $this->db->prepareStatement("SELECT * FROM test WHERE id=:1",$name);
		$this->assertNotNull($name);
		$this->assertNotNull($prepared);
		
		try{
			$params = array(1);
			$rows = $this->db->execute($prepared,$params);
			$this->assertEquals(1,count($rows));
		}catch(Exception $e){$this->assertTrue(false,"Unexpected exception during execute().");}
	}
	
	public function testColumnsForTable(){
		$this->assertEquals(array("id"),$this->db->columnsForTable("test"));
	}
	
	public function testAllTables(){
		$this->assertEquals(array("test"),$this->db->allTables());
	}
	
}