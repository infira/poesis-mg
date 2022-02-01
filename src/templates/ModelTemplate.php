<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;

class ModelTemplate extends ClassTemplate
{
	protected $dataMethodsClass = '';
	protected $columnClass      = '';
	public    $name             = '';
	
	private $columns   = [];
	public  $tableName = '';
	public  $modelName = '';
	private $modelProperties;
	
	public function __construct(ClassType $class, object $phpNamespace)
	{
		parent::__construct($class, $phpNamespace);
	}
	
	public function beforeFinalize()
	{
		if ($this->dataMethodsClass !== '\Infira\Poesis\dr\DataMethods') {
			//$this->setDataMethodsClass('\Infira\Poesis\dr\DataMethods');
			$select = $this->createMethod('select');
			$select->addParameter('columns')
				//->setType('array|string')
				->setDefaultValue(null);
			$select->addBodyLine('return parent::doSelect($columns, %s)', Utils::extractClass($this->dataMethodsClass));
			$select->addComment('Select data from database');
			$select->addComment('@param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma');
			$select->addComment('@return ' . $this->dataMethodsClass);
		}
		
		if (!$this->columnClass) {
			$this->columnClass = '\Infira\Poesis\clause\ModelColumn';
		}
		$this->import($this->columnClass, 'ModelColumn');
		
		if ($this->columnClass !== '\Infira\Poesis\clause\ModelColumn') {
			$this->addSchemaProperty('columnClass', Utils::literal("ModelColumn::class"));
		}
		
		foreach ($this->modelProperties as $name => $value) {
			$this->addProperty($name, $value)->setProtected();
		}
		$this->addComment('ORM model for ' . $this->tableName);
		$this > $this->addComment(' ');
		$this->addComment('@property ' . $this->name . ' $Where class where values');
		$this->addComment('@method ' . $this->name . ' model(array $options = [])');
		foreach ($this->columns as $columnName => $column) {
			addExtraErrorInfo('$columnName', [$columnName => $column]);
			$desc         = $column['Type'] . ((isset($column["Comment"]) and strlen($column["Comment"]) > 0) ? ' - ' . $column["Comment"] : '');
			$commentTypes = join('|', $column['types']);
			$paramName    = Utils::varName($column['types'][0]);
			
			$this->addComment('@property ModelColumn $' . $columnName . ' ' . $desc);
			$this->addComment('@method ' . $this->name . ' ' . $columnName . '(' . $commentTypes . ' $' . $paramName . ')');
			
			/*
			$method = $this->createMethod(Utils::methodName($columnName));
			$method->setPublic();
			$method->setReturnType('self');
			$method->addParameter($paramName);//->setType(join('|', $column['types']));
			$method->addBodyLine('return $this->add(\'' . $columnName . '\', $' . $paramName . ')');
			$method->addComment('Set value for ' . $columnName);
			$method->addComment('@param ' . $commentTypes . ' $' . $paramName . ' - ' . $desc);
			$method->addComment('@return self');
			*/
			clearExtraErrorInfo();
		}
	}
	
	public function addIndexMethods(array $indexMethods): void
	{
		foreach ($indexMethods as $indexName => $columns) {
			$columnComment = [];
			$method        = $this->createMethod(Utils::methodName($indexName) . '_index');
			$method->addComment('Set value for ' . join(', ', $columnComment) . ' index');
			$method->addComment('');
			foreach ($columns as $Col) {
				$columnComment[] = $Col->Column_name;
				$method->addParameter($Col->Column_name);
				
				$column = $this->columns[$Col->Column_name];
				$desc   = $column['Type'] . ((isset($column["Comment"]) and strlen($column["Comment"]) > 0) ? ' - ' . $column["Comment"] : '');
				
				$commentTypes = join('|', $column['types']);
				$method->addComment('@param ' . $commentTypes . ' $' . $Col->Column_name . ' - ' . $desc);
				$method->addBodyLine('$this->add2Clause($this->value2ModelColumn(\'%s\', $%s));', $Col->Column_name, $Col->Column_name);
			}
			$method->addBodyLine('return $this');
		}
	}
	
	public function setDataMethodsClass(string $dataMethodsClass): void
	{
		$this->import($dataMethodsClass);
		$this->dataMethodsClass = $dataMethodsClass;
	}
	
	public function setModelExtender(string $class)
	{
		$this->import($class);
		$this->class->setExtends($class);
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
	
	public function addPrimaryColumn(string $name)
	{
		$this->modelProperties['primaryColumns'][] = $name;
	}
	
	public function setColumnClass(string $columnClass): void
	{
		$this->columnClass = $columnClass;
	}
	
	public function addSchemaProperty(string $name, $value)
	{
		$this->modelProperties[$name] = $value;
	}
}