<?php
/**
 * DTResponse
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
 * @package    Deep Thought (HTTP)
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2014 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

define("DT_ERR_NONE",0);
define("DT_ERR_INVALID_KEY",1);
define("DT_ERR_FAILED_QUERY",2);
define("DT_ERR_PROHIBITED_ACTION",3);
define("DT_ERR_UNAUTHORIZED_TOKEN",4);

class DTResponse{
	public $obj;
	protected $err = 0;

	function __construct($obj=null,$err=0){
		$this->obj = $obj;
		$this->err = $err;
	}

	public function setResponse($obj){
		$this->obj = $obj;
	}

	public function error($code=null){
		if(isset($code))
			$this->err = intval($code);
		return $this->err;
	}

	/** Converts the +obj+ to a form it can be rendered.
		For DTModels, these are only the public properties. */
	public static function objectAsRenderable($obj=null){
		$renderable = array();
		if($obj instanceof DTModel)
			$renderable = $obj->publicProperties();
		else if(is_array($obj))
			foreach($obj as $k=>$v) //traverse list
				$renderable[$k] = static::objectAsRenderable($v);
		else
			$renderable = $obj;
		return $renderable;
	}

//===================
//! Rendering Methods
//===================
	public function renderAsDTR(){
		$response = array("fmt"=>"DTR","err" => $this->err,"obj"=>$this->objectAsRenderable($this->obj));
		$this->render(json_encode($response));
	}

	public function renderAsJSON(){
		$this->render(json_encode($this->objectAsRenderable($this->obj)));
	}

	public static function render($str){
		if(isset($_REQUEST["callback"])){ //handle jsonp
			header("Content-Type:application/javascript");
			$str = htmlspecialchars($_REQUEST["callback"])."( {$str} )";
		}else
			header('Content-Type: application/json; charset=utf-8');
		echo $str;
	}

	public function respond(array $params=array()){
		$fmt = isset($params["fmt"])?$params["fmt"]:"dtr";
		if($this->err >= 400) // put this in the header
			http_response_code($this->err);
		switch($fmt){
			case "json":
				$this->renderAsJSON();
				break;
			default:
				$this->renderAsDTR();
		}
	}
}
