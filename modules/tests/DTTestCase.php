<?php
/**
 * DTTestCase
 *
 * Copyright (c) 2013-2014, Expressive Analytics, LLC <info@expressiveanalytics.com>.
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @package    Deep Thought
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2014 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

class DTTestCase extends \PHPUnit_Framework_TestCase{
	protected $db=null; /** the test store, initialized before each test by +initSQL()+ */
	protected $production_store; /** reference to the first production store */
	
	function __construct(){
		@session_start(); // don't complain about tests trying to start the session after phpunit's output
		parent::__construct();
	}

	public function setup(){
		// swap out the production schema for our test schema
		$this->production_store = DTSettingsStorage::defaultStore();
		$this->db = DTSettingsStorage::defaultStore( DTSQLiteDatabase::init($this->initSQL()) );
	}

	/* @return returns +sql+ after adding initialization steps  */
	protected function initSQL($sql=""){
		return $sql;
	}
	
	/** test defined for all cases to verify that production (minimally) matches test schema */
	public function testProductionSchema(){
		if(isset($this->production_store)){
			$test_tables = $this->db->allTables();
			$prod_tables = $this->production_store->allTables();
			foreach($test_tables as $t){
				if(in_array($t, $prod_tables)){ // make sure this table exists in production
					$test_cols = $this->db->columnsForTable($t);
					$prod_cols = $this->production_store->columnsForTable($t);
					foreach($test_cols as $c)
						if(!in_array($c,$prod_cols)) // make sure all columns exist in production
							DTLog::warn("'".get_class($this)."' is not compatible with production schema (table '{$t}' missing column '{$c}')");
				}else
					DTLog::warn("'".get_class($this)."' is not compatible with production schema (missing table '{$t}')");
			}
		} // otherwise, production database is not set, so there's nothing to verify
	}
}