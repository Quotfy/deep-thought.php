<?php
class DTPatterns{
	public static function singleton(&$var,$val_f){
		if(!isset($var))
			$var = $val_f();
		return $var;
	}
}