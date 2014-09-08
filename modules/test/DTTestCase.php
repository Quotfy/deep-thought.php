<?php namespace ExpressiveAnalytics\DeepThought;
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


use ExpressiveAnalytics\DeepThought\DTSQLiteDatabase;
use ExpressiveAnalytics\DeepThought\DTSettings;

class DTTestCase extends \PHPUnit_Framework_TestCase{
	protected $db=null;

	public function setup(){
		$this->db = DTSQLiteDatabase::init($this->initSQL());
	}

	/* @return returns +sql+ after adding initialization steps  */
	protected function initSQL($sql=""){
		return $sql;
	}
	
	/** test defined for all cases to verify that production (minimally) matches test schema */
	public function testProductionSchema(){
		if(isset($this->db))
			try {
				$productiondb = DTSettingsStorage::defaultStore();
				if(!isset($productiondb))
					return;

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