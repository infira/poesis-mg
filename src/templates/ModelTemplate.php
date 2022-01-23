<?php

namespace Infira\pmg\templates;


use Nette\PhpGenerator\ClassType;

class ModelTemplate extends ClassTemplate
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	public $constructor;
	
	protected $dataMethodsClass           = '';
	public    $tableName                  = '';
	public    $name                       = '';
	public    $modelDefaultConnectionName = '';
	public    $loggerEnabled              = false;
	
	private $columns = [];
	
	public function __construct(ClassType $class, object $phpNamespace)
	{
		parent::__construct($class, $phpNamespace);
		$this->constructor = $this->createMethod('__construct');
	}
	
	public function beforeFinalize()
	{
		if (!$this->dataMethodsClass) {
			$this->setDataMethodsClass('\Infira\Poesis\dr\DataMethods');
		}
		
		$select = $this->createMethod('select');
		$select->setReturnType($this->dataMethodsClass);
		$select->addParameter('columns')
			//->setType('array|string')
			->setDefaultValue(null);
		$select->addBodyLine('return parent::doSelect($columns, DataMethods::class)');
		$select->addComment('Select data from database');
		$select->addComment('@param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma');
		
		
		$this->constructor->addParameter('options', [])->setType('array');
		$this->constructor->addEqBodyLine('$this->Schema', Utils::literal("$this->name" . "Schema::class"));
		$this->constructor->addBodyLine('$this->Schema::construct()');
		$this->constructor->addEqBodyLine('$this->loggerEnabled', $this->loggerEnabled);
		$this->constructor->addEqBodyLine('$options[\'connection\']', Utils::literal('$options[\'connection\'] ?? \'' . $this->modelDefaultConnectionName . '\''));
		$this->constructor->addBodyLine('parent::__construct($options)');
		
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
				$method->addBodyLine('$this->add(\'' . $Col->Column_name . '\', $' . $Col->Column_name . ')');
			}
			$method->addBodyLine('return $this');
		}
	}
	
	public function setDataMethodsClass(string $dataMethodsClass): void
	{
		$this->import($dataMethodsClass, 'DataMethods');
		$this->dataMethodsClass = $dataMethodsClass;
	}
	
	public function setModelExtender(string $class)
	{
		$this->import($class, 'Model');
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
}