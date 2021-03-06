<?php

namespace Infira\pmg\templates;

abstract class Template
{
	private $magicVar;
	
	public function __construct(object &$mixer, string $varName)
	{
		$this->magicVar = $varName;
		$this->$varName = $mixer;
	}
	
	public function __call($name, $arguments)
	{
		$var = $this->magicVar;
		
		return $this->$var->$name(...$arguments);
	}
	
}