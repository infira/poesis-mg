<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class ModelTemplate extends ClassTemplate
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	public $constructor;
	
	protected $dataMethodsClass           = '';
	protected $columnClass                = '';
	protected $modelClass                 = '';
	public    $tableName                  = '';
	public    $name                       = '';
	public    $modelDefaultConnectionName = '';
	public    $loggerEnabled              = false;
	
	private $columns = [];
	
	public function __construct(ClassType $class, PhpFile $phpFile)
	{
		parent::__construct($class, $phpFile);
		$this->constructor = $this->createMethod('__construct');
	}
	
	public function beforeFinalize()
	{
		if (!$this->dataMethodsClass) {
			$this->setDataMethodsClass('\Infira\Poesis\dr\DataMethods');
		}
		if (!$this->modelClass) {
			$this->setModelClass('\Infira\Poesis\orm\Model');
		}
		if (!$this->columnClass) {
			$this->setColumnClass('\Infira\Poesis\orm\ModelColumn');
		}
		$this->setExtends($this->modelClass);
		
		$select = $this->createMethod('select');
		$select->addParameter('columns')
			//->setType('array|string')
			->setDefaultValue(null);
		$select->addBodyLine('return parent::doSelect($columns, ' . $this->dataMethodsClass . '::class)');
		$select->addComment('Select data from database');
		$select->addComment('@param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma');
		
		
		$this->constructor->addParameter('options', [])->setType('array');
		$this->constructor->addEqBodyLine('$this->Schema', "CLEAN=$this->name" . "Schema::class");
		$this->constructor->addBodyLine('$this->Schema::construct()');
		$this->constructor->addEqBodyLine('$this->modelColumnClassName', "CLEAN=$this->columnClass::class");     //TODO className peaks olema lihtsalt class
		$this->constructor->addEqBodyLine('$this->loggerEnabled', $this->loggerEnabled);
		$this->constructor->addEqBodyLine('$options[\'connection\']', 'CLEAN=$options[\'connection\'] ?? \'' . $this->modelDefaultConnectionName . '\'');
		$this->constructor->addBodyLine('parent::__construct($options)');
		
		$this->addComment('ORM model for ' . $this->tableName);
		$this > $this->addComment(' ');
		$this->addComment('@property ' . $this->name . ' $Where class where values');
		foreach ($this->columns as $columnName => $column) {
			addExtraErrorInfo('$columnName', [$columnName => $column]);
			$desc = $column['Type'] . ((isset($column["Comment"]) and strlen($column["Comment"]) > 0) ? ' - ' . $column["Comment"] : '');
			$this->addComment('@property ModelColumn $' . $columnName . ' ' . $desc);
			
			$method = $this->createMethod(Utils::fixClassVarName($columnName));
			$method->setPublic();
			$method->setReturnType('self');
			
			$paramName = Utils::fixClassVarName($column['types'][0]);
			
			$method->addParameter($paramName);//->setType(join('|', $column['types']));
			$method->addBodyLine('return $this->add(\'' . $columnName . '\', $' . $paramName . ')');
			
			$method->addComment('Set value for ' . $columnName);
			$commentTypes = join('|', $column['types']);
			$method->addComment('@param ' . $commentTypes . ' $' . $paramName . ' - ' . $desc);
			$method->addComment('@return self');
			clearExtraErrorInfo();
		}
	}
	
	public function setDataMethodsClass(string $dataMethodsClass, bool $setUse = true): void
	{
		$this->setClassVariable('dataMethodsClass', $setUse, $dataMethodsClass, 'DataMethods');
	}
	
	public function setColumnClass(string $columnClass, bool $setUse = true): void
	{
		$this->setClassVariable('columnClass', $setUse, $columnClass, 'ModelColumn');
	}
	
	public function setModelClass(string $modelClass, bool $setUse = true): void
	{
		$this->setClassVariable('modelClass', $setUse, $modelClass, 'Model');
	}
	
	public function setColumn(string $columnName, array $column)
	{
		$type            = $column['fType'];
		$column["types"] = [];
		if (in_array($type, ['varchar', 'char', 'tinytext', 'mediumtext', 'text', 'longtext', 'enum', 'serial', 'datetime', 'date', '', '', '', '', '', ''])) {
			$column["types"][] = 'string';
		}
		elseif (in_array($type, ['smallint', 'tinyint', 'mediumint', 'int', 'bigint', 'year'])) {
			$column["types"][] = 'integer';
		}
		elseif (in_array($type, ['float', 'decimal', 'double', 'real'])) {
			$column["types"][] = 'float';
		}
		elseif ($type == 'set') {
			$column["types"][] = 'array';
			$column["types"][] = 'string';
		}
		elseif ($type == 'timestamp') {
			$column["types"][] = 'integer';
			$column["types"][] = 'string';
		}
		else {
			$column["types"] = ['mixed'];
		}
		$column["types"][]          = 'Field';
		$this->columns[$columnName] = $column;
	}
}