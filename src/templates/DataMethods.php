<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;

class DataMethods extends ClassTemplate
{
	public function __construct(ClassType $class, object $phpNamespace)
	{
		parent::__construct($class, $phpNamespace);
	}
	
	public function beforeFinalize() {}
}