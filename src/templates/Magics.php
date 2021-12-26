<?php

namespace Infira\pmg\templates;

abstract class Magics
{
	private $magicVar;
	
	public function __call($name, $arguments)
	{
		$var = $this->magicVar;
		
		return $this->$var->$name(...$arguments);
	}
	
	public final function setMagicVar(string $var)
	{
		$this->magicVar = $var;
	}
	
}