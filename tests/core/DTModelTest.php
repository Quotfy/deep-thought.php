<?php 
class DTModelTest extends DTTestCase{
	public function initSQL($sql=""){
		return $sql .= <<<END

CREATE TABLE test_models (
	id integer PRIMARY KEY autoincrement,
	name text,
	status text
);
INSERT INTO test_models (id,name,status) VALUES (1,'A1','FAILURE');
INSERT INTO test_models (id,name,status) VALUES (2,'A2','SUCCESS');

CREATE TABLE table_a (
	id integer PRIMARY KEY autoincrement,
	name text
);
INSERT INTO table_a (id,name) VALUES (1,'A1');

CREATE TABLE table_b (
	id integer PRIMARY KEY autoincrement,
	name text,
	a_id int
);
INSERT INTO table_b (id,name,a_id) VALUES (1,'B1',1);
INSERT INTO table_b (id,name,a_id) VALUES (2,'B2',1);

CREATE TABLE table_a_to_b (
	id integer PRIMARY KEY autoincrement,
	name text,
	a_id int,
	b_id int
);
INSERT INTO table_a_to_b (id,name,a_id,b_id) VALUES (1,'AB1',1,1);
INSERT INTO table_a_to_b (id,name,a_id,b_id) VALUES (2,'AB2',1,2);

CREATE TABLE table_c (
	id integer PRIMARY KEY autoincrement,
	name text,
	b_id int	
);
INSERT INTO table_c (id,name,b_id) VALUES (1,'C1',1);
INSERT INTO table_c (id,name,b_id) VALUES (2,'C2',1);
INSERT INTO table_c (id,name,b_id) VALUES (3,'C3',1);

CREATE TABLE table_d (
	id integer PRIMARY KEY autoincrement,
	name text,
	a_id int,
	a2_id int
);
INSERT INTO table_d (id,name,a_id,a2_id) VALUES (1,'D1',1,1);
INSERT INTO table_d (id,name,a_id,a2_id) VALUES (2,'D2',1,2);

END;
	}
	
	public function testConstructor(){
		$test_str = '{"fruit": "apple", "color": "red"}';
		$obj = new DTModel(json_decode($test_str,true));
		$this->assertEquals("apple",$obj["fruit"],json_encode($obj));
		$this->assertEquals("red",$obj["color"]);
	}
	
	public function testUpsertFromStorage(){
		// 1. test recovery of parameters
		$test = TestModel::upsert($this->db->filter(array("id"=>1)),array());
		$this->assertEquals("FAILURE",$test["status"]);
		$test = TestModel::upsert($this->db->filter(array("id"=>2)),array());
		$this->assertEquals("SUCCESS",$test["status"]);
	}
	
	public function testUpsertSetter(){
		// 1. test newly upserted setter
		$test = TestModel::upsert(
			$this->db->filter(array(1=>0)),
			array("status"=>"FAILURE")); //overridden by setter method
		$this->assertEquals("SUCCESS",$test["status"]);
		
		// 2. test existing upsert setter
		$test = TestModel::upsert(
			$this->db->filter(array("id"=>1)),
			array("status"=>"FAILURE")); //overridden by setter method
		$this->assertEquals("SUCCESS",$test["status"]);
	}
	
	public function testGetOne(){
		// 1. test B.a_id->A
		$test = new ModelB($this->db->filter(array("id"=>1)));
		$this->assertEquals("A1",$test["a"]["name"]);
		
		// 2. test AB.a_id->A
		$test = new ModelAB($this->db->filter(array("id"=>1)));
		$this->assertEquals("A1",$test["a"]["name"]);
	}
	
	public function testGetMany(){
		// 1. test A->B.a_id (one-to-many)
		$test = new ModelA($this->db->filter(array("id"=>1)));
		$this->assertEquals("B1",$test["b_list"][0]["name"]);
		$this->assertEquals("B2",$test["b_list"][1]["name"]);
		
		// 2. test A->AB->B (many-to-many)
		$test = new ModelA($this->db->filter(array("id"=>1)));
		$this->assertEquals("B1",$test["b_list_weak"][0]["name"]);
		$this->assertEquals("B2",$test["b_list_weak"][1]["name"]);
		
		// 3. test A->AB->B->C (many-to-many+)
		$test = new ModelA($this->db->filter(array("id"=>1)));
		$this->assertEquals(3,count($test["c_list"]));
		$this->assertEquals("C1",$test["c_list"][0]["name"]);
		$this->assertEquals("C2",$test["c_list"][1]["name"]);
		$this->assertEquals("C3",$test["c_list"][2]["name"]);
		
		// 4. test shortcut A->AB->C (many-to-many*)
		$test = new ModelA($this->db->filter(array("id"=>1)));
		$this->assertEquals("C1",$test["c_list_optimized"][0]["name"]);
		$this->assertEquals("C2",$test["c_list_optimized"][1]["name"]);
		$this->assertEquals("C3",$test["c_list_optimized"][2]["name"]);
		
		// 5. test A->D.a2_id
		$test = new ModelA($this->db->filter(array("id"=>1)));
		DTLog::debug($test["d_list"]);
		$this->assertEquals("D1",$test["d_list"][0]["name"]);
		$this->assertEquals("D2",$test["d_list"][1]["name"]);
	}
	
	public function testSetOne(){
		$a = new ModelA(array("name"=>"testA"));
		$test = new ModelB($this->db->filter(array("id"=>1)));
		$test["a"] = $a;
		$this->assertEquals("testA",$test["a"]["name"]);
	}
	
	/*public function testUpsertManyByID(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($this->db->filter(array("id"=>1)));
		// test A->B
		ModelA::upsert($a_filter,array("b_list"=>array(1,3)));
		$this->assertEquals("B1",$test["b_list"][0]["name"]);
		$this->assertNotEquals("B2",$test["b_list"][1]["name"]);
	}
	
	public function testUpsertManyByIDBListWeak(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($this->db->filter(array("id"=>1)));
		// 2. test A->AB->B
		ModelA::upsert($a_filter,array("b_list_weak"=>array(1,3)));
		$this->assertEquals("B1",$test["b_list_weak"][0]["name"]);
		$this->assertNotEquals("B2",$test["b_list_weak"][1]["name"]);
	}
	
	public function testUpsertManyByIDCList(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		// test A->AB->B->C
		ModelA::upsert($a_filter,array("c_list"=>array(1,2,4)));
		$this->assertEquals("C1",$test["c_list"][0]["name"]);
		$this->assertEquals("C2",$test["c_list"][1]["name"]);
		$this->assertNotEquals("C3",$test["c_list"][2]["name"]);
	}
	
	public function testUpsertManyByIDOptimized(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		// test A->AB->C (shortcut)
		ModelA::upsert($a_filter,array("c_list_optimized"=>array(1,2,4)));
		$this->assertEquals("C1",$test["c_list_optimized"][0]["name"]);
		$this->assertEquals("C2",$test["c_list_optimized"][1]["name"]);
		$this->assertNotEquals("C3",$test["c_list_optimized"][2]["name"]);
	}*/
	
	public function testUpsertManyByIDDList(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		// test A->D.a2_id
		ModelA::upsert($a_filter,array("d_list"=>array(1,3)));
		DTLog::debug($test["d_list"]);
		$this->assertEquals("D1",$test["d_list"][0]["name"]);
		$this->assertNotEquals("D2",$test["d_list"][1]["name"]);
	}
	
	/*public function testUpsertManyByIDWithParams(){
		// 1. test A->C by ids+params
		$cs = array(
			new ModelC(array("id"=>1,"name"=>"C1")),
			new ModelC(array("id"=>2,"name"=>"C2")),
			new ModelC(array("id"=>4,"name"=>"C4"))
		);
		
		ModelA::upsert($this->db->filter(array("id"=>1)),$cs);
	}
	
	public function testUpsertManyByParams(){
		// 1. test A->C by params
		$cs = array(
			new ModelC(array("name"=>"C1")),
			new ModelC(array("name"=>"C2")),
			new ModelC(array("name"=>"C4"))
		);
		
		ModelA::upsert($this->db->filter(array("id"=>1)),$cs);
	}
*/
}

class TestModel extends DTModel{
	protected static $storage_table = "test_models";
	public $status;
	
	public function setStatus($val){
		$this->status = "SUCCESS";
	}
}

class ModelA extends DTModel{
	protected static $storage_table = "table_a";
	protected static $has_many_manifest = array(
		"b_list"=>array("ModelB"),
		"b_list_weak"=>array("ModelAB","ModelB"),
		"c_list"=>array("ModelAB","ModelB","ModelC"),
		"c_list_optimized"=>array("ModelAB.b_id","ModelC.b_id"),
		"d_list"=>array("ModelD.a2_id")
	);
	public $name;
	public $c_list;
	
	public function CList(){
		$manifest = $this->hasManyManifest();
		$qb = $this->db->filter()->orderBy("ModelC.id");
		return $this->getMany($manifest["c_list"],$qb);
	}
	
	public function CListOptimized(){
		$manifest = $this->hasManyManifest();
		$qb = $this->db->filter()->orderBy("ModelC.id");
		return $this->getMany($manifest["c_list_optimized"],$qb);
	}
}

class ModelB extends DTModel{
	protected static $storage_table = "table_b";
	protected static $has_a_manifest = array(
		"a"=>array("ModelA","a_id")
	);
	protected static $has_many_manifest = array(
		"c_list"=>array("ModelC")
	);
	public $name;
	public $a;
}

class ModelAB extends DTModel{
	protected static $storage_table = "table_a_to_b";
	protected static $has_a_manifest = array(
		"a"=>array("ModelA","a_id"),
		"b"=>array("ModelB","b_id")
	);
	public $name;
	public $a_id;
	public $b_id;
}

class ModelC extends DTModel{
	protected static $storage_table = "table_c";
	protected static $has_a_manifest = array(
		"b"=>array("ModelB","b_id")
	);
	public $name;
}

class ModelD extends DTModel{
	protected static $storage_table = "table_d";
	protected static $has_a_manifest = array(
		"a"=>array("ModelA","a_id"),
		"a2"=>array("ModelA","a2_id")
	);
	public $name;
	public $a_id;
	public $a2_id;
}