<?php

namespace ExpressiveAnalytics\DeepThought\DTTestCase;

class DTTestCase extends PHPUnit_Framework_TestCase{
	protected $db=null;

	public function setup(){
		$this->db = DTSettings::$default_database = DTSQLiteDatabase::init($this->initSQL());
	}

	/* @return returns +sql+ after adding initialization steps  */
	protected function initSQL($sql=""){
		return $sql;
	}
	
	/** test defined for all cases to verify that production (minimally) matches test schema */
	public function testProductionSchema(){
		if(isset($this->db))
			try {
				/** @TODO production store**/
			
				$productiondb = DTSettingsStorage::connect($this->production_store);

				$test_tables = $this->db->allTables();
				$prod_tables = $productiondb->allTables();
				foreach($test_tables as $t){
					if(in_array($t, $prod_tables)){ //make sure this table exists in production
						$test_cols = $this->db->columnsForTable($t);
						$prod_cols = $productiondb->columnsForTable($t);
						foreach($test_cols as $c)
							if(!in_array($c,$prod_cols)) //make sure all columns exist in production
								DTLog::debug("class '".get_class($this)."' is not compatible with production schema (table '{$t}' missing column '{$c}')");
					}else
						DTLog::debug("class '".get_class($this)."' is not compatible with production schema (missing table '{$t}')");
				}
			} catch(Exception $e){} // database is not set, so there's nothing to verify
	}
}