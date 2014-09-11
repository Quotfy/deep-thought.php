<?php

class DTQueryBuilderTest extends \PHPUnit_Framework_TestCase{
	public function testSelectStatement(){
		$qb = new DTQueryBuilder(null);
		$qb->from("test_table t1");
		$qb->join("another_table t2","t1.id=t2.t1_id");
		$qb->leftJoin("third_table t3","t2.id=t3.t2_id");
		$qb->where("test=success");
		$qb->having("1=1");
		$qb->groupBy("test_col");
		$qb->orderBy("test_col DESC");
		$qb->limit(10);
		$this->assertEquals("SELECT test_col FROM test_table t1  JOIN another_table t2 ON (t1.id=t2.t1_id) LEFT JOIN third_table t3 ON (t2.id=t3.t2_id) WHERE (test=success) GROUP BY test_col HAVING (1=1) ORDER BY test_col DESC LIMIT 10",$qb->selectStatement("test_col"));
	}
	
	public function testFormatValue(){
		$qb = new DTQueryBuilder(null);
		$this->assertEquals("NULL",$qb->formatValue(null));
		$this->assertEquals('\'{"test":"success"}\'',$qb->formatValue(array("test"=>"success")));
		$this->assertEquals('SELECT * FROM test',$qb->formatValue("\\DTSQLEXPR\\SELECT * FROM test"));
		$this->assertEquals('\'Any old string\'',$qb->formatValue("Any old string"));
		
		$qb->filter(array("id"=>"1 OR 1=1"));
		$this->assertEquals(0,preg_match("/id=1 OR 1=1/",$qb->selectStatement())); //simple sql injection test
	}
}