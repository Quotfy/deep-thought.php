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

CREATE TABLE table_aa (
	id integer primary key autoincrement,
	a_parent_id integer
);
INSERT INTO table_aa (id, a_parent_id) VALUES (3, 1);

CREATE TABLE table_aaa (
	id integer primary key autoincrement,
	aa_parent_id integer
);
INSERT INTO table_aaa (id, aa_parent_id) VALUES (4, 3);

CREATE TABLE table_e (
	id integer PRIMARY KEY autoincrement,
	name text,
	aa_id int
);
INSERT INTO table_e (id,name,aa_id) VALUES (1,'E1',3);
INSERT INTO table_e (id,name,aa_id) VALUES (2,'E2',3);

END;
	}
	
	public function testConstructor(){
		$test_str = '{"fruit": "apple", "color": "red"}';
		$obj = new DTModel(json_decode($test_str,true));
		$this->assertEquals("apple",$obj["fruit"],json_encode($obj));
		$this->assertEquals("red",$obj["color"]);
	}
	
	public function testIsDirty(){
		$obj = new TestModel($this->db->filter(array("id"=>1)));
		$this->assertFalse($obj->isDirty("new_key"));
		$obj["new_key"] = "dirty";
		$this->assertTrue($obj->isDirty("new_key"));
	}
	
	public function testSetToNull(){
		$obj = new TestModel($this->db->filter(array("id"=>1)));
		$obj["name"] = null;
		$properties = $obj->storageProperties($this->db);
		$this->assertTrue(in_array("name", array_keys($properties))&&$obj["name"]===null);
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
		$this->assertEquals(1,count($test["d_list"]));
		$this->assertEquals("D1",$test["d_list"][0]["name"]);
	}
	
	public function testSetOne(){
		$a = ModelA::upsert($this->db->qb()->fail(),array("name"=>"testA"));
		$test = new ModelB($this->db->filter(array("id"=>1)));
		$test->setA("a",array("name"=>"testA"));
		$this->assertEquals("testA",$test["a"]["name"]);
	}
	
	public function testUpsertManyByID(){
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
		$this->assertEquals("4",$test["c_list"][2]["id"]);
	}
	
	public function testUpsertManyByIDOptimized(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		// test A->AB->C (shortcut)
		ModelA::upsert($a_filter,array("c_list_optimized"=>array(1,2,4)));
		$this->assertEquals("C1",$test["c_list_optimized"][0]["name"]);
		$this->assertEquals("C2",$test["c_list_optimized"][1]["name"]);
		$this->assertEquals("4",$test["c_list_optimized"][2]["id"]);
	}
	
	public function testUpsertManyByIDDList(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		// test A->D.a2_id
		ModelA::upsert($a_filter,array("d_list"=>array(1,3)));
		$this->assertEquals("D1",$test["d_list"][0]["name"]);
		$this->assertNotEquals("D2",$test["d_list"][1]["name"]);
	}
	
	public function testUpsertManyByIDWithParams(){
		$a_filter = $this->db->filter(array("id"=>1));
		ModelA::upsert($a_filter,array("c_list"=>array(
			array("id"=>1,"name"=>"C1"),
			array("id"=>2,"name"=>"C2"),
			array("id"=>3,"name"=>"C4")
		)));
		$test = new ModelA($a_filter);
		$this->assertEquals("C1",$test["c_list"][0]["name"]);
		$this->assertEquals("C2",$test["c_list"][1]["name"]);
		$this->assertNotEquals("C3",$test["c_list"][2]["name"]);
	}
	
	public function testUpsertManyByParams(){
		$a_filter = $this->db->filter(array("id"=>1));
		$test = new ModelA($a_filter);
		ModelA::upsert($a_filter,array("c_list_tags"=>array("C1","C2","C4")));
		$this->assertEquals("C1",$test["c_list"][0]["name"]);
		$this->assertEquals("C2",$test["c_list"][1]["name"]);
		$this->assertNotEquals("C3",$test["c_list"][2]["name"]);
	}
	
	
	/// test whether we capture the attributes of our parent
	public function testParent(){
		$aa_filter = $this->db->filter(array("ModelAA.id"=>3));
		$test = new ModelAA($aa_filter);
		$this->assertEquals("A1",$test["name"]);
	}

	/// test whether we capture the attributes of our grandparent
	public function testGrandparent(){
		$aaa_filter = $this->db->filter(array("ModelAAA.id"=>4));
		$test = new ModelAAA($aaa_filter);
		$this->assertEquals("A1",$test["name"]);
	}
	
	public function testManyToManyViaParent(){
		$aa_filter = $this->db->filter(array("ModelAA.id"=>3));
		$test = new ModelAA($aa_filter);
		$this->assertEquals("B1",$test["b_list"][0]["name"]);
		$this->assertEquals("B2",$test["b_list"][1]["name"]);
	}
	
	public function testManyToManyViaGrandparent(){
		$aaa_filter = $this->db->filter(array("ModelAAA.id"=>4));
		$test = new ModelAAA($aaa_filter);
		
		// this comes from the grandparent class
		$this->assertEquals("B1",$test["b_list"][0]["name"]);
		$this->assertEquals("B2",$test["b_list"][1]["name"]);
		
		// only the parent knows how to do this one
		$this->assertEquals("E1",$test["e_list"][0]["name"]);
		$this->assertEquals("E2",$test["e_list"][1]["name"]);
	}
	
	public function testClosure(){
		$a = new ModelA($this->db->filter(array("id"=>1)));
		
		$default = array();
		$closure = $a->closure(array("ModelAB","ModelB"),$default);
		$this->assertEquals('{"ModelA":{"1":1},"ModelAB":{"1":1,"2":1},"ModelB":{"2":2,"1":1}}',json_encode($closure));
		
		$a["b_list_weak"] = array(1);
		$b = new ModelB($this->db->filter(array("name"=>"B2")));
		$this->assertEquals("2",$b["id"]); // make sure we haven't wiped out the B list
	}
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
		return $this->getMany("c_list",$this->db->qb()->orderBy("ModelC.id"));
	}
	
	public function CListOptimized(){
		return $this->getMany("c_list_optimized",$this->db->qb()->orderBy("ModelC.id"));
	}
	
	public function setCListTags($vals){
		return $this->setMany("c_list",$vals,function($out,$i){
			$out[] = array("name"=>$i); return $out;
		});
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

class ModelAA extends ModelA{
	protected static $storage_table = "table_aa";
	protected static $is_a_manifest = array(
		"a_parent_id"=>"ModelA"
	);
	protected static $has_many_manifest = array(
		"e_list"=>array("ModelE")	
	);
}

class ModelAAA extends ModelAA{
	protected static $storage_table = "table_aaa";
	protected static $is_a_manifest = array(
		"aa_parent_id"=>"ModelAA"
	);
}

class ModelE extends DTModel{
	protected static $storage_table = "table_e";
	protected static $has_a_manifest = array(
		"aa"=>array("ModelAA","aa_id")
	);
	public $name;
	public $aa;
}