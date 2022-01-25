<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;

class DbSchemaTemplate extends ClassTemplate
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	public $constructor;
	
	public  $tableName       = '';
	private $columns         = [];
	private $primaryColumns  = [];
	private $columnStructure = [];
	
	
	public function __construct(ClassType $class, object $phpNamespace)
	{
		parent::__construct($class, $phpNamespace);
		$this->setExtends('\Infira\Poesis\orm\DbSchema');
	}
	
	public function beforeFinalize()
	{
		$this->addProperty('structure', $this->columnStructure)->setProtected();
	}
	
	public function setColumn(string $table, string $column, string $type, bool $signed, $length, $default, array $allowedValues, bool $isNull, bool $isAi)
	{
		$index                                             = "$table.$column";
		$this->columns[]                                   = "'$index'";
		$this->columnStructure[$table][$column]['type']    = $type;
		$this->columnStructure[$table][$column]['signed']  = $signed;
		$this->columnStructure[$table][$column]['length']  = $length;
		$this->columnStructure[$table][$column]['default'] = $default;
		
		foreach ($allowedValues as &$value) {
			$value = str_replace("'", '', $value);
		}
		$this->columnStructure[$table][$column]['allowedValues'] = $allowedValues;
		
		$this->columnStructure[$table][$column]['isNull'] = $isNull;
		$this->columnStructure[$table][$column]['isAI']   = $isAi;
	}
}