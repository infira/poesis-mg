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
	 * @var PhpFile
	 */
	private $pf = null;
	
	public function __construct(ClassType $class, PhpFile $phpFile, ?string $namespace = '')
	{
		$this->class = &$class;
		$this->pf    = &$phpFile;
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
		$this->pf->addUse($name, $alias);
	}
	
	public function setClassVariable(string $varName, bool $setUse, string $class, string $alias = null): void
	{
		if ($setUse) {
			$class = $this->addImportFromString($class, $alias);
		}
		$this->$varName = $class;
	}
	
	private function addImportFromString(string $class, string $alias = null): string
	{
		if ($class[0] == '\\') {
			$class = substr($class, 1);
		}
		$ex = explode('\\', $class);
		$this->import(join('\\', $ex), $alias);
		
		return $alias ?: end($ex);
	}
	
	public function setExtender(string $class, string $alias = null): self
	{
		$this->addImportFromString($class, $alias);
		$this->class->setExtends($class);
		
		return $this;
	}
}