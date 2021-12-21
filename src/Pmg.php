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
use Infira\pmg\templates\ModelShortcutTemplate;

class Pmg extends Command
{
	const REMOVE_EMPTY_LINE = '[REMOVE_EMPTY_LINE]';
	private $dbName = '';
	
	/**
	 * @var \Infira\pmg\helper\Db
	 */
	private $db;
	/**
	 * @var \Infira\pmg\helper\Options
	 */
	private $opt;
	
	private $destination = '';
	private $madeFiles   = [];
	
	/**
	 * @var ModelShortcutTemplate
	 */
	private $shortcut;
	
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
		if ($destinationPath[0] != '/')
		{
			$rp              = dirname($yamlFile) . '/' . $destinationPath;
			$destinationPath = Dir::fixPath(realpath(dirname($yamlFile) . '/' . $destinationPath));
			if (!is_dir($destinationPath))
			{
				$this->error("create path $rp not found");
			}
		}
		if (!is_dir($destinationPath))
		{
			$this->error("create path $destinationPath not found");
		}
		if (!is_writable($destinationPath))
		{
			$this->error('create path not writable');
		}
		$this->destination = $destinationPath = Dir::fixPath($destinationPath);
		
		
		$connection   = (object)$this->opt->get('connection');
		$this->db     = new Db('pmg', $connection->host, $connection->user, $connection->pass, $connection->db, $connection->port);
		$this->dbName = $connection->db;
		$this->opt->scanExtensions();
		
		Dir::flushExcept($this->destination, [$this->getShortcutTraitFileName(), 'dummy.txt']);
		$this->shortcut = new ModelShortcutTemplate($this->opt->getShortcutName());
		$this->makeTableClassFiles();
		$this->shortcut->addImports($this->opt->getShortcutImports());
		$this->makeFile($this->getShortcutTraitFileName(), $this->shortcut->getCode());
		
		$this->output->region('Made models', function ()
		{
			$this->output->dumpArray($this->madeFiles);
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
	
	private function makeFile(string $fileName, $content)
	{
		$file = $this->destination . $fileName;
		File::delete($file);
		
		$file = realpath(dirname(__FILE__)) . '/templates/tmpl.txt';
		if (!file_exists($file))
		{
			$this->error("Installer $file not found");
		}
		$con = File::getContent($file);
		if ($vars)
		{
			return Variable::assign($vars, $con);
		}
		
		File::create($file, "<?php \n\n" . $content, "w+", 0777);
		
		$this->madeFiles[] = $file;
	}
	
	/**
	 * @throws \Exception
	 */
	private function makeTableClassFiles()
	{
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
				
				$templateVars                   = [];
				$templateVars["tableName"]      = $tableName;
				$templateVars["className"]      = $model;
				$templateVars["nodeProperties"] = '';
				
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
				
				$modelTemplate->addTraits($this->opt->getModelTraits($model));
				
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
				
				$shortcutMethod = $this->shortcut->createMethod($model);
				$shortcutMethod->setStatic(true);
				$shortcutMethod->addParameter('options')->setType('array');
				$shortcutMethod->setReturnType($model);
				$shortcutMethod->addBodyLine('return new ' . $newModelName . '($options);');
				$shortcutMethod->addComment('Method to return ' . $newModelName . ' class');
				$shortcutMethod->addComment('@param array $options = []');
				$shortcutMethod->addComment('@return ' . $newModelName);
				
				foreach ($Table->columns as $Column)
				{
					$columnName      = $Column['Field'];
					$type            = Str::lower(preg_replace('/\(.*\)/m', '', $Column['Type']));
					$type            = strtolower(trim(str_replace("unsigned", "", $type)));
					$Column['fType'] = $type;
					
					$modelTemplate->setColumn($columnName, $Column);
					
					$templateVars["nodeProperties"] .= '
    public $' . $columnName . ';';
					
					
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
					$columnComment = [];
					$method        = $modelTemplate->createMethod($indexName . '_index');
					foreach ($columns as $Col)
					{
						$columnComment[] = $Col->Column_name;
						$method->addParameter($Col->Column_name);
						$method->addBodyLine('$this->add(\'' . $Col->Column_name . '\', $' . $Col->Column_name . ')');
					}
					$method->addBodyLine('return $this;');
					$method->addComment('Set value for ' . join(', ', $columnComment) . ' index');
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
				
				$this->makeFile($model . '.' . $this->opt->getModelFileNameExtension(), $this->getTemplate("ModelTemplate.txt", $templateVars));
			}
		}
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