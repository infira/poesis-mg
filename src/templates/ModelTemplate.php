<?php

namespace Infira\pmg\templates;


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
	
	
	public function __construct(string $model, ?string $namespace = '')
	{
		parent::__construct($model, $namespace);
		$this->constructor = $this->createMethod('__construct');
		$this->constructor->addParameter('options', [])->setType('array');
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
			$desc = $column['Type'] . ((isset($column["Comment"]) and strlen($column["Comment"]) > 0) ? ' - ' . $column["Comment"] : '');
			$this->addComment('@property ModelColumn $' . $columnName . ' ' . $desc);
		}
		$this->addComment('@author https://github.com/infira/poesis-mg');
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
		$this->columns[$columnName] = $column;
	}
}