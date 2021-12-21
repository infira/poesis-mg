<?php

namespace Infira\pmg\templates;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * @mixin ClassType
 */
abstract class ClassTemplate extends Template
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
	
	public function __construct(string $type, string $name, ?string $namespace = '')
	{
		parent::__construct(ClassType::$type($name), 'class');
		$this->ns = new PhpNamespace($namespace);
	}
	
	public function getCode(): string
	{
		$this->beforeGetCode();
		array_walk($this->methods, function (&$method)
		{
			$method = $method->construct();
		});
		$this->class->setMethods($this->methods);
		$this->addComment('@author https://github.com/infira/poesis-mg');
		
		$printable = $this->class;
		if ($this->ns !== null)
		{
			$this->ns->add($this->class);
			//$this->ns->addClass($this->class->getName());
			
			$printable = $this->ns;
		}
		
		return $printable->__toString();
	}
	
	public function createMethod(string $name)
	{
		$method          = new MethodTemplate($name);
		$this->methods[] = &$method;
		
		return $method;
	}
	
	public function addImports(array $imports)
	{
		array_walk($imports, [$this, 'import']);
	}
	
	public function import(string $name, ?string $alias = null)
	{
		$this->ns->addUse($name, $alias);
	}
	
	public function addTraits(array $imports)
	{
		array_walk($imports, [$this, 'addTrait']);
	}
	
	
	public function setClassVariable(string $varName, string $class, string $alias = null): void
	{
		$class          = $this->addImportFromString($class, $alias);
		$this->$varName = "CLEAN=$class::CLASS";
	}
	
	private function addImportFromString(string $class, string $alias = null): string
	{
		if ($class[0] == '\\')
		{
			$class = substr($class, 1);
		}
		$ex = explode('\\', $class);
		$this->import(join('\\', $ex), $alias);
		
		return $alias ?: end($ex);
	}
	
	public function setExtender(string $class, string $alias = null): self
	{
		//$this->class->setExtends($this->addImportFromString($class));
		$this->addImportFromString($class, $alias);
		$this->class->setExtends($class);
		
		return $this;
	}
	
	abstract public function beforeGetCode();
}