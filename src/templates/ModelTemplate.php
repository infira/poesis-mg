<?php

namespace Infira\pmg\templates;


use Illuminate\Support\Str;

class ModelTemplate extends ClassTemplate
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	public $constructor;
	
	protected $dataMethodsClass           = '';
	protected $modelColumnClassName       = '';
	protected $createModelClassName       = '';
	public    $tableName                  = '';
	public    $modelDefaultConnectionName = '';
	public    $loggerEnabled              = false;
	
	private $columns = [];
	
	
	public function __construct(string $name, ?string $namespace = '')
	{
		addExtraErrorInfo('model', $name);
		parent::__construct('class', $name, $namespace);
		$this->constructor = $this->createMethod('__construct');
		$this->constructor->addParameter('options', [])->setType('array');
		
		$select = $this->createMethod('select');
		$select->addParameter('columns')
			->setType($this->dataMethodsClass)
			->setDefaultValue(null);
		$select->addBodyLine('return parent::select($columns);');
		$select->addComment('Select data from database');
		$select->addComment('@param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma');
		
		$this->import('\Infira\Poesis\Poesis');
		$this->import('\Infira\Poesis\orm\node\Field');
		
	}
	
	public function beforeGetCode()
	{
		$this->constructor->addEqBodyLine('$this->Schema', "CLEAN=" . $this->createModelClassName . 'Schema::class');
		$this->constructor->addBodyLine('$this->Schema::construct()');
		$this->constructor->addEqBodyLine('$this->dataMethodsClassName', $this->dataMethodsClass);
		$this->constructor->addEqBodyLine('$this->modelColumnClassName', $this->modelColumnClassName);
		$this->constructor->addEqBodyLine('$this->loggerEnabled', $this->loggerEnabled);
		$this->constructor->addEqBodyLine('$options[\'connection\']', 'CLEAN=$options[\'connection\'] ?? \'' . $this->modelDefaultConnectionName . '\'');
		$this->constructor->addBodyLine('parent::__construct($options)');
		
		$this->addComment('ORM model for ' . $this->tableName);
		$this > $this->addComment(' ');
		$this->addComment('@property ' . $this->createModelClassName . ' $Where class where values');
		foreach ($this->columns as $columnName => $column)
		{
			addExtraErrorInfo('$columnName', [$columnName => $column]);
			$desc = $column['Type'] . ((isset($column["Comment"]) and strlen($column["Comment"]) > 0) ? ' - ' . $column["Comment"] : '');
			$this->addComment('@property ModelColumn $' . $columnName . ' ' . $desc);
			
			$methodName = ucfirst(Str::camel(Str::slug($columnName, '_')));
			if (is_numeric($methodName[0]))
			{
				$methodName = "_$methodName";
			}
			
			$method = $this->createMethod($methodName);
			
			$paramName = 'value';
			
			$method->addParameter($paramName)->setType(join('|', $column['types']));
			$method->setReturnType('ModelColumn');
			$method->addBodyLine('return $this->add(\'' . $columnName . '\', $' . $paramName . ');');
			
			$method->addComment('Set value for ' . $columnName);
			$commentTypes = join('|', $column['types']);
			$method->addComment('@param ' . $commentTypes . ' $' . $desc);
			$method->addComment('@return ' . $this->createModelClassName);
			clearExtraErrorInfo();
		}
	}
	
	public function setModelClassPath(string $modelClass): void
	{
		$ex                         = explode('\\', $modelClass);
		$this->createModelClassName = end($ex);
	}
	
	public function setDataMethodsClass(string $dataMethodsClass): void
	{
		$this->setClassVariable('dataMethodsClass', $dataMethodsClass, 'DataMethods');
	}
	
	public function setModelColumnClassName(string $modelColumnClassName): void
	{
		$this->setClassVariable('modelColumnClassName', $modelColumnClassName, 'ModelColumn');
	}
	
	public function setModelClass(string $class)
	{
		$this->setExtender($class, 'Model');
	}
	
	public function setColumn(string $columnName, array $column)
	{
		$type              = $column['fType'];
		$rep               = [];
		$rep["varchar"]    = "string";
		$rep["char"]       = "string";
		$rep["tinytext"]   = "string";
		$rep["mediumtext"] = "string";
		$rep["text"]       = "string";
		$rep["longtext"]   = "string";
		
		$rep["smallint"]  = "integer";
		$rep["tinyint"]   = "integer";
		$rep["mediumint"] = "integer";
		$rep["int"]       = "integer";
		$rep["bigint"]    = "integer";
		
		$rep["year"]      = "integer";
		$rep["timestamp"] = "integer|string";
		$rep["enum"]      = "string";
		$rep["set"]       = "string|array";
		$rep["serial"]    = "string";
		$rep["datetime"]  = "string";
		$rep["date"]      = "string";
		$rep["float"]     = "float";
		$rep["decimal"]   = "float";
		$rep["double"]    = "float";
		$rep["real"]      = "float";
		if (!isset($rep[$type]))
		{
			$column["types"] = ['mixed'];
		}
		else
		{
			$column["types"] = [$rep[$type]];
		}
		$column["types"][]          = 'Field';
		$this->columns[$columnName] = $column;
	}
}