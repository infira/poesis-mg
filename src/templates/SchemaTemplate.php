<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;

class SchemaTemplate extends ClassTemplate
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	public $constructor;
	
	public $tableName = '';
	public $modelName = '';
	public $aiColumn  = null;
	public $TIDColumn = null;
	public $isView    = null;
	
	private $columns        = [];
	private $primaryColumns = [];
	
	
	public function __construct(ClassType $class, object $phpNamespace)
	{
		parent::__construct($class, $phpNamespace);
		$this->constructor = $this->createMethod('construct');
		$this->constructor->setStatic();
		$this->class->addTrait('\Infira\Poesis\orm\Schema');
	}
	
	public function beforeFinalize()
	{
		$this->constructor->addEqBodyLine('self::$tableName', $this->tableName);
		$this->constructor->addEqBodyLine('self::$modelName', $this->modelName);
		$this->constructor->addEqBodyLine('self::$modelClass', Utils::literal("$this->modelName::class"));
		$this->constructor->addEqBodyLine('self::$columns', $this->columns);
		$this->constructor->addEqBodyLine('self::$primaryColumns', $this->primaryColumns);
		$this->constructor->addEqBodyLine('self::$aiColumn', $this->aiColumn);
		$this->constructor->addEqBodyLine('self::$TIDColumn', $this->TIDColumn);
		$this->constructor->addEqBodyLine('self::$isView', $this->isView);
	}
	
	public function addPrimaryColumn(string $name)
	{
		$this->primaryColumns[] = "'$name'";
	}
	
	public function setColumn(string $column, string $type, bool $signed, $length, $default, array $allowedValues, bool $isNull, bool $isAi)
	{
		$this->columns[]            = "'$column'";
		$structure['type']          = $type;
		$structure['signed']        = $signed;
		$structure['length']        = $length;
		$structure['default']       = $default;
		$structure['allowedValues'] = $allowedValues;
		$structure['isNull']        = $isNull;
		$structure['isAI']          = $isAi;
		foreach ($structure as $name => $value) {
			$structure[$name] = "'$name'=>" . sprintf(...Utils::parseValueFormat($value));
		}
		$this->constructor->addEqBodyLine('self::$columnStructure[\'' . $column . '\']', $structure);
	}
}