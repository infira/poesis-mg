<?php

namespace Infira\pmg\templates;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PhpFile;

/**
 * @mixin ClassType
 */
abstract class ClassTemplate extends Magics
{
	/**
	 * @var ClassType
	 */
	protected $class;
	
	/**
	 * @var \Infira\pmg\templates\MethodTemplate[]
	 */
	private $methods = [];
	
	/**
	 * @var PhpNamespace|null
	 */
	private $ns = null;
	
	/**
	 * @var PhpFile|PhpNamespace
	 */
	private $phpf = null;
	
	public function __construct(ClassType $class, object $phpNamespace)
	{
		$this->class = &$class;
		$this->phpf  = &$phpNamespace;
		$this->setMagicVar('class');
	}
	
	public function finalise()
	{
		$this->beforeFinalize();
		array_walk($this->methods, function (&$method)
		{
			$method = $method->construct();
		});
		$this->class->setMethods($this->methods);
		$this->addComment('');
		$this->addComment('@author https://github.com/infira/poesis-mg');
	}
	
	public function createMethod(string $name)
	{
		$method          = $this->addMethod($name);
		$method          = new MethodTemplate($method);
		$this->methods[] = &$method;
		
		return $method;
	}
	
	public function addImports(array $imports)
	{
		array_walk($imports, [$this, 'import']);
	}
	
	public function import(string $name, ?string $alias = null)
	{
		$this->phpf->addUse($name, $alias);
	}
}