<?php

class DTPgSQLDatabaseTest extends \PHPUnit_Framework_TestCase{
	protected $db = null;

	public function setup(){
		$settings = DTStorage::shared();
		$settings = array(); //clear out any old shared settings
		DTStorage::read(__DIR__."/storage.json");
		try{
			$this->db = DTStorage::connect("test-db");
		}catch(\Exception $e){
			$this->markTestSkipped("You must create a valid PostgreSQL connection in '".__DIR__."/storage.json'!");
		}


		$init_sql =<<<END
CREATE TABLE IF NOT EXISTS dt_unit_test ( id SERIAL, primary key (id) );
TRUNCATE dt_unit_test;
ALTER SEQUENCE dt_unit_test_id_seq RESTART WITH 1;
INSERT INTO dt_unit_test DEFAULT VALUES;
INSERT INTO dt_unit_test DEFAULT VALUES;
END;
	$this->db->query($init_sql);
	}

	public function testDisconnect(){
		$unconnected_db = new DTPgSQLDatabase();
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
		$this->assertEquals("this unit''s clean",$this->db->clean($val));
	}

	public function testInsertAndLastID(){
		$id = $this->db->insert("INSERT INTO dt_unit_test DEFAULT VALUES;");
		$this->assertEquals(3,$id);
		$this->assertEquals($id,$this->db->lastInsertID());
	}

	public function testPlaceholder(){
		$this->assertEquals("\$1",$this->db->placeholder($params,"firstval"));
		$this->assertEquals("\$2",$this->db->placeholder($params,"secondval"));
		$this->assertEquals(2,count($params));
	}

	public function testPrepareStatements(){
		$name = "test_prepared_statement";
		$this->db->prepareStatement("SELECT * FROM dt_unit_test",$name);
		$this->assertEquals("test_prepared_statement",$name);

		$name = null;
		$prepared = $this->db->prepareStatement("SELECT * FROM dt_unit_test WHERE id=\$1",$name);
		$this->assertNotNull($name);
		$this->assertNotNull($prepared);

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
}
