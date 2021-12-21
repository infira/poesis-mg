<?php

namespace Infira\pmg;


use Symfony\Component\Console\Input\InputArgument;
use Infira\pmg\helper\Db;
use Infira\pmg\helper\Options;
use Infira\Utils\Dir;
use Infira\Utils\Variable;
use Infira\Utils\Regex;
use Infira\Utils\File;
use Illuminate\Support\Str;
use Infira\console\Command;
use Infira\pmg\templates\SchemaTemplate;
use Infira\pmg\templates\ModelTemplate;

class Pmg extends Command
{
	const REMOVE_EMPTY_LINE = '[REMOVE_EMPTY_LINE]';
	private $dbTablesMethods = '';
	private $dbName          = '';
	
	/**
	 * @var \Infira\pmg\helper\Db
	 */
	private $db;
	/**
	 * @var \Infira\pmg\helper\Options
	 */
	private $opt;
	
	public function __construct()
	{
		parent::__construct('create');
	}
	
	public function configure(): void
	{
		$this->addArgument('yaml', InputArgument::REQUIRED);
	}
	
	/**
	 * @throws \Exception
	 */
	public function runCommand()
	{
		$yamlFile = $this->input->getArgument('yaml');
		
		if (!file_exists($yamlFile))
		{
			$this->error('Config files does not exist');
		}
		
		$this->opt = new Options($yamlFile);
		
		$destinationPath = $this->opt->getDestinationPath();
		if (!is_dir($destinationPath))
		{
			$this->error("create path $destinationPath not found");
		}
		if (!is_writable($destinationPath))
		{
			$this->error('create path not writable');
		}
		$destinationPath = Dir::fixPath($destinationPath);
		
		
		$connection   = (object)$this->opt->get('connection');
		$this->db     = new Db('pmg', $connection->host, $connection->user, $connection->pass, $connection->db, $connection->port);
		$this->dbName = $connection->db;
		$this->opt->scanExtensions();
		
		$madeFiles    = $this->makeTableClassFiles($destinationPath);
		$vars         = [];
		$vars['body'] = '';
		if ($traits = $this->opt->getShortcutImports())
		{
			foreach ($traits as $trait)
			{
				$vars['body'] .= 'use ' . $trait . ';' . "\n";
			}
		}
		$vars['body']              .= $this->dbTablesMethods;
		$vars['shortcutName']      = $this->opt->getShortcutName();
		$vars['useNamespace']      = '';
		$vars['shortcutNamespace'] = self::REMOVE_EMPTY_LINE;
		if ($this->opt->getNamespace())
		{
			$vars['shortcutNamespace'] = 'namespace ' . $this->opt->getNamespace() . ';';
		}
		$template    = Variable::assign($vars, $this->getTemplate("ModelShortcut_Template.txt"));
		$madeFiles[] = $this->makeFile($destinationPath . $this->getShortcutTraitFileName(), $template);
		
		$this->output->region('Made models', function () use ($madeFiles)
		{
			$this->output->dumpArray($madeFiles);
		});
	}
	
	/**
	 * PPrivate method to construct a php class name by database table name
	 *
	 * @param string $tableName
	 * @return string
	 */
	private function constructModelName(string $tableName): string
	{
		return Str::ucfirst(Str::camel($this->opt->getModelClassNamePrefix() . $tableName));
	}
	
	private function constructFullName(string $model): string
	{
		return $this->opt->getNamespace() ? $this->opt->getNamespace() . '\\' . $model : $model;
	}
	
	private function makeFile(string $fileName, $content): string
	{
		File::delete($fileName);
		$newLines = [];
		foreach (explode("\n", $content) as $line)
		{
			if (strpos($line, self::REMOVE_EMPTY_LINE) === false)
			{
				$newLines[] = $line;
			}
		}
		File::create($fileName, join("\n", $newLines), "w+", 0777);
		
		return $fileName;
	}
	
	/**
	 * @throws \Exception
	 */
	private function makeTableClassFiles(string $installPath): array
	{
		$collectedFiles = [];
		//$model             = new Model(['isGenerator' => true]);
		$notAllowedColumns = [];//get_class_methods($model);
		
		$tables = $this->db->query("SHOW FULL TABLES");
		if ($tables)
		{
			$tablesData = [];
			while ($Row = $tables->fetch_object())
			{
				$columnName = "Tables_in_" . $this->dbName;
				$tableName  = $Row->$columnName;
				if (!$this->opt->isTableVoided($tableName))
				{
					unset($Row->$columnName);
					unset($dbName);
					$columnsRes = $this->db->query("SHOW FULL COLUMNS FROM`" . $tableName . '`');
					
					if (!isset($tablesData[$tableName]))
					{
						$Table                  = $Row;
						$Table->columns         = [];
						$tablesData[$tableName] = $Table;
					}
					
					while ($columnInfo = $columnsRes->fetch_array(MYSQLI_ASSOC))
					{
						$tablesData[$tableName]->columns[$columnInfo['Field']] = $columnInfo;
						if (in_array($columnInfo['Field'], $notAllowedColumns))
						{
							$this->error('Column <strong>' . $tableName . '.' . $columnInfo['Field'] . '</strong> is system reserverd');
						}
					}
				}
			}
			
			foreach ($tablesData as $tableName => $Table)
			{
				$model = $this->constructModelName($tableName);
				
				$templateVars              = [];
				$templateVars["tableName"] = $tableName;
				$templateVars["className"] = $model;
				
				$schemaTemplate                       = new SchemaTemplate($model);
				$schemaTemplate->createModelClassName = $model;
				$schemaTemplate->modelName            = $model;
				$schemaTemplate->tableName            = $tableName;
				
				$modelTemplate            = new ModelTemplate($model, $this->opt->getNamespace());
				$modelTemplate->tableName = $tableName;
				
				
				$schemaTemplate->isView = $Table->Table_type == "VIEW";
				if ($this->opt->isModelTIDEnabled($model) and isset($Table->columns[$this->opt->getModelTIDColumnName($model)]))
				{
					$schemaTemplate->TIDColumn = $this->opt->getModelTIDColumnName($model);
				}
				
				$templateVars["nodeProperties"] = '';
				$templateVars["columnMethods"]  = '';
				
				$templateVars["modelTraits"] = self::REMOVE_EMPTY_LINE;
				if ($modelTraits = $this->opt->getModelTraits($model))
				{
					foreach ($modelTraits as $key => $import)
					{
						$modelTraits[$key] = "use $import;";
					}
					$templateVars["modelTraits"] = join("\n", $modelTraits);
				}
				
				if ($result = $this->db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'"))
				{
					while ($Index = $result->fetch_object())
					{
						$schemaTemplate->addPrimaryColumn($Index->Column_name);
					}
				}
				$modelTemplate->setModelClass($this->opt->getModelExtender($model));
				
				$newModelName = '\\' . $model;
				if ($this->opt->getNamespace())
				{
					$mns = $this->opt->getNamespace();
					if (substr($mns, -1) != '\\')
					{
						$mns .= '\\';
					}
					$newModelName = '\\' . $mns . $model;
				}
				$this->dbTablesMethods .= '
	/**
	 * Method to return ' . $newModelName . ' class
	 * @param array $options = []
	 * @return ' . $newModelName . '|$this
	 */
	public static function ' . $model . '(array $options = [])
	{
		return new ' . $newModelName . '($options);
	}
				' . "\n";
				
				$isLast             = false;
				$count              = count($Table->columns);
				$key                = -1;
				$columnCommentParam = [];
				
				foreach ($Table->columns as $Column)
				{
					$columnName = $Column['Field'];
					$type       = Str::lower(preg_replace('/\(.*\)/m', '', $Column['Type']));
					$type       = strtolower(trim(str_replace("unsigned", "", $type)));
					$key++;
					if (($key + 1) == $count)
					{
						$isLast = true;
					}
					
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
						$commentTypes = 'mixed';
					}
					else
					{
						$commentTypes = $rep[$type];
					}
					$commentTypes    .= '|Field';
					$Column["types"] = explode('|', $commentTypes);
					$columnParamType = $Column["types"][0];
					$modelTemplate->setColumn($columnName, $Column);
					
					$templateVars["columnMethods"] .= '
	/**
	 * Set value for ' . $columnName . '
	 * @param ' . $commentTypes . ' $' . $columnParamType . ' - ' . $Column['Type'] . '
	 * @return ' . $model . '
	 */
	public function ' . $columnName . '($' . $columnParamType . '): ' . $model . '
	{
		return $this->add(\'' . $columnName . '\', $' . $columnParamType . ');
	}';
					
					$templateVars["nodeProperties"] .= '
    public $' . $columnName . ';';
					
					
					$columnCommentParam[$columnName] = '* @param ' . $columnParamType . ' $' . $columnName;
					
					
					$isInt    = (strpos($type, "int") !== false);
					$isNumber = (in_array($type, ["decimal", "float", "real", "double"]));
					$isAi     = $Column["Extra"] == "auto_increment";
					$isNull   = $Column["Null"] == "YES";
					
					if ($Column["Extra"] == "auto_increment")
					{
						$schemaTemplate->aiColumn = $columnName;
					}
					
					$signed        = (bool)strpos(strtolower($Column['Type']), "unsigned") !== false;
					$length        = null;
					$allowedValues = [];
					if (strpos($Column['Type'], "enum") !== false)
					{
						$allowedValues = [str_replace(["enum", "(", ")"], "", $Column['Type'])];
					}
					elseif (strpos($Column['Type'], "set") !== false)
					{
						$allowedValues = [str_replace(["set", "(", ")"], "", $Column['Type'])];
					}
					else
					{
						if (strpos($Column['Type'], "("))
						{
							$length = str_replace(['(', ',', ')'], ['', '.', ''], Regex::getMatch('/\((.*)\)/m', $Column['Type']));
							if ($isNumber)
							{
								$ex     = explode(".", $length);
								$length = '[\'d\'=>' . $ex[0] . ',\'p\'=>' . $ex[1] . ',\'fd\'=>' . ($ex[0] - $ex[1]) . ']';
							}
						}
					}
					if (in_array($type, ['timestamp', 'date', 'datetime']) or is_numeric($length))
					{
						$length = intval($length);
					}
					if ($isAi)
					{
						$default = '';
					}
					elseif ($isInt or $isNumber)
					{
						$default = ($Column['Default'] === null) ? 'Poesis::NONE' : addslashes($Column['Default']);
					}
					else
					{
						if ($Column['Default'] === null and $isNull)
						{
							$default = null;
						}
						elseif ($Column['Default'] === null)
						{
							$default = 'Poesis::NONE';
						}
						elseif ($Column['Default'] == "''")
						{
							$default = '';
						}
						else
						{
							$default = addslashes($Column['Default']);
						}
						
					}
					$schemaTemplate->setColumn($columnName, $type, $signed, $length, $default, $allowedValues, $isNull, $isAi);
				} //EOF each columns
				
				//make index methods
				$indexes = [];
				if ($result = $this->db->query("SHOW INDEX FROM `$tableName`"))
				{
					while ($Index = $result->fetch_object())
					{
						$indexes[$Index->Key_name][] = $Index;
					}
				}
				$indexMethods = array_filter($indexes, function ($var)
				{
					return count($var) > 1;
				});
				foreach ($indexMethods as $indexName => $columns)
				{
					$columnComment   = [];
					$columnArguments = [];
					$columnCalled    = [];
					foreach ($columns as $Col)
					{
						$columnComment[]   = $Col->Column_name;
						$columnArguments[] = '$' . $Col->Column_name;
						$columnCalled[]    = '
		$this->add(\'' . $Col->Column_name . '\', $' . $Col->Column_name . ');';
					}
					$templateVars["columnMethods"] .= '
	/**
	 * Set value for ' . join(', ', $columnComment) . " index";
					foreach ($columns as $Col)
					{
						$templateVars["columnMethods"] .= '
	 ' . $columnCommentParam[$Col->Column_name];
					}
					$templateVars["columnMethods"] .= '
	 * @return $model
	 */
	public function ' . $indexName . '_index(' . join(', ', $columnArguments) . ')
	{   ' . join('', $columnCalled) . '
	    return $this;
	}
';
				}
				
				
				$modelTemplate->addImports($this->opt->getModelImports($model));
				
				$modelTemplate->setDataMethodsClass($this->opt->getModelDataMethodsClass($model));
				$modelTemplate->setModelColumnClassName($this->opt->getModelColumnClass($model));
				$modelTemplate->loggerEnabled              = $this->opt->isModelLogEnabled($model);
				$modelTemplate->modelDefaultConnectionName = $this->opt->getModelConnectionName($model);
				$schemaTemplate->createModelClassName      = $this->constructFullName($model);
				$modelTemplate->setModelClassPath($this->constructFullName($model));
				$templateVars['node']        = $this->getModelNodeContent($templateVars, $model);
				$templateVars['dataMethods'] = $this->getModelDataMethodsClassContent($templateVars, $model);
				$templateVars['dbName']      = $this->dbName;
				
				$templateVars['model']  = $modelTemplate->getCode();
				$templateVars['schema'] = $schemaTemplate->getCode();
				
				$collectedFiles[$model . '.' . $this->opt->getModelFileNameExtension()] = $this->getTemplate("ModelTemplate.txt", $templateVars);
			}
		}
		//actually collect files
		Dir::flushExcept($installPath, [$this->getShortcutTraitFileName(), 'dummy.txt']);
		$madeFiles = [];
		foreach ($collectedFiles as $file => $content)
		{
			$madeFiles[] = $this->makeFile($installPath . $file, $content);
		}
		
		return $madeFiles;
	}
	
	/**
	 * @throws \Exception
	 */
	private function getModelNodeContent(array &$vars, string $model): string
	{
		if (!$this->opt->getModelMakeNode($model))
		{
			return self::REMOVE_EMPTY_LINE;
		}
		$vars['nodeClassName'] = $this->opt->getModelNodeClassName($model);
		$vars['nodeExtender']  = $this->opt->getModelNodeExtender($model);
		$vars["nodeTraits"]    = self::REMOVE_EMPTY_LINE;
		$nodeTraits            = $this->opt->getModelNodeTraits($model);
		if ($nodeTraits)
		{
			foreach ($nodeTraits as $key => $extender)
			{
				$nodeTraits[$key] = "use $extender;";
			}
			$vars["nodeTraits"] = join("\n", $nodeTraits);
		}
		
		return str_repeat("\n", 3) . $this->getTemplate("ModelNodeTemplate.txt", $vars);
	}
	
	/**
	 * @throws \Exception
	 */
	private function getModelDataMethodsClassContent(array &$vars, string $model): string
	{
		$vars['dataMethodsExtender'] = $this->opt->getModelDataMethodsClass($model);
		
		if (!$this->opt->getModelMakeNode($model))
		{
			return self::REMOVE_EMPTY_LINE;
		}
		$vars['createNodeClassArguments'] = '$constructorArguments';
		
		$vars['dataMethodsClassName'] = $this->opt->getModelDataMethodsClass($model);
		$vars['nodeClassFullPath']    = $this->constructFullName($this->opt->getModelNodeClassName($model));
		$vars['dataMethodsTraits']    = self::REMOVE_EMPTY_LINE;
		if ($trTraits = $this->opt->getModelDataMethodsTraits($model))
		{
			foreach ($trTraits as $key => $extender)
			{
				$trTraits[$key] = "use $extender;";
			}
			$vars["dataMethodsTraits"] = join("\n", $trTraits);
		}
		
		return str_repeat("\n", 1) . $this->getTemplate("ModelDataMethodsTemplate.txt", $vars);
	}
	
	private function getTemplate($file, $vars = null): string
	{
		$file = realpath(dirname(__FILE__)) . '/templates/' . $file;
		if (!file_exists($file))
		{
			$this->error("Installer $file not found");
		}
		$con = File::getContent($file);
		if ($vars)
		{
			return Variable::assign($vars, $con);
		}
		
		return $con;
	}
	
	private function getShortcutTraitFileName(): string
	{
		return $this->opt->getShortcutName() . '.' . $this->opt->getShortcutTraitFileNameExtension();
	}
}