<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class DataMethods extends ClassTemplate
{
	public function __construct(ClassType $class, PhpFile $phpFile)
	{
		parent::__construct($class, $phpFile);
	}
	
	public function beforeFinalize() {}
}