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

class Pmg extends Command
{
	const REMOVE_EMPTY_LINE = '[REMOVE_EMPTY_LINE]';
	private string $dbTablesMethods = '';
	private string $dbName          = '';
	
	private helper\Db      $db;
	private helper\Options $opt;
	
	
	public function __construct()
	{
		parent::__construct('create');
	}
	
	public function configure(): void
	{
		$this->addArgument('yaml', InputArgument::REQUIRED);
		$this->addArgument('path', InputArgument::REQUIRED);
	}
	
	/**
	 * @throws \Exception
	 */
	public function runCommand()
	{
		$yamlFile   = $this->input->getArgument('yaml');
		$createPath = $this->input->getArgument('path');
		
		if (!file_exists($yamlFile))
		{
			$this->error('Config files does not exist');
		}
		
		if (!is_dir($createPath))
		{
			$this->error("create path $createPath not found");
		}
		if (!is_writable($createPath))
		{
			$this->error('create path not writable');
		}
		$createPath = Dir::fixPath($createPath);
		
		$this->opt    = new Options($yamlFile);
		$connection   = (object)$this->opt->get('connection');
		$this->db     = new Db('pmg', $connection->host, $connection->user, $connection->pass, $connection->db, $connection->port);
		$this->dbName = $connection->db;
		
		$madeFiles    = $this->makeTableClassFiles($createPath);
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
		if ($this->opt->getShortcutNamespace())
		{
			$vars['shortcutNamespace'] = 'namespace ' . $this->opt->getShortcutNamespace() . ';';
		}
		$template    = Variable::assign($vars, $this->getTemplate("ModelShortcut_Template.txt"));
		$madeFiles[] = $this->makeFile($createPath . $this->getShortcutTraitFileName(), $template);
		
		return $madeFiles;
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
				
				$templateVars["isView"]    = ($Table->Table_type == "VIEW") ? "true" : "false";
				$templateVars["aiColumn"]  = 'null';
				$templateVars["TIDColumn"] = 'null';
				if ($this->opt->isModelTIDEnabled($model) and isset($Table->columns[$this->opt->getModelTIDColumnName($model)]))
				{
					$templateVars["TIDColumn"] = "'" . $this->opt->getModelTIDColumnName($model) . "'";
				}
				
				$templateVars["autoAssistProperty"] = self::REMOVE_EMPTY_LINE;
				$templateVars["nodeProperties"]     = '';
				$templateVars["columnMethods"]      = '';
				$templateVars["primaryColumns"]     = '[]';
				
				$templateVars["modelTraits"] = self::REMOVE_EMPTY_LINE;
				if ($modelTraits = $this->opt->getModelTraits($model))
				{
					foreach ($modelTraits as $key => $import)
					{
						$modelTraits[$key] = "use $import;";
					}
					$templateVars["modelTraits"] = join("\n", $modelTraits);
				}
				
				$primaryColumns = [];
				if ($result = $this->db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'"))
				{
					while ($Index = $result->fetch_object())
					{
						$primaryColumns[] = "'" . $Index->Column_name . "'";
					}
				}
				if ($primaryColumns)
				{
					$templateVars["primaryColumns"] = "[" . join(",", $primaryColumns) . "]";
				}
				$templateVars["columnTypes"]   = '';
				$templateVars["columnNames"]   = '';
				$templateVars['modelExtender'] = $this->opt->getModelExtender($tableName);
				
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
					$commentTypes .= '|Field';
					
					$columnParamType   = explode('|', $commentTypes)[0];
					$Column["Comment"] = $Column['Type'];
					$Desc              = (isset($Column["Comment"]) && $Column["Comment"]) ? ' - ' . $Column["Comment"] : '';
					
					$templateVars["autoAssistProperty"] .= '
 * @property %modelColumnClassLastName% $' . $columnName . ' ' . $columnParamType . $Desc;
					
					
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
					$templateVars["columnNames"]     .= "'" . $columnName . "'" . ((!$isLast) ? ',' : '');
					
					
					$isInt    = (strpos($type, "int") !== false);
					$isNumber = (in_array($type, ["decimal", "float", "real", "double"]));
					
					$allowedValues = '';
					$length        = "null";
					if (strpos($Column['Type'], "enum") !== false)
					{
						$allowedValues = str_replace(["enum", "(", ")"], "", $Column['Type']);
					}
					elseif (strpos($Column['Type'], "set") !== false)
					{
						$allowedValues = str_replace(["set", "(", ")"], "", $Column['Type']);
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
					
					$isAi   = $Column["Extra"] == "auto_increment";
					$isNull = $Column["Null"] == "YES";
					
					if ($isAi)
					{
						$default = "''";
					}
					elseif ($isInt or $isNumber)
					{
						$default = ($Column['Default'] === null) ? 'Poesis::NONE' : addslashes($Column['Default']);
					}
					else
					{
						if ($Column['Default'] === null and $isNull)
						{
							$default = 'NULL';
						}
						elseif ($Column['Default'] === null)
						{
							$default = 'Poesis::NONE';
						}
						elseif ($Column['Default'] == "''")
						{
							$default = "''";
						}
						else
						{
							$default = "'" . addslashes($Column['Default']) . "'";
						}
						
					}
					if (in_array($type, ['timestamp', 'date', 'datetime']))
					{
						$length = intval($length);
					}
					
					$vars                        = [];
					$vars["fn"]                  = $columnName;
					$vars["t"]                   = $type;
					$vars["sig"]                 = (strpos(strtolower($Column['Type']), "unsigned") !== false) ? "FALSE" : "TRUE";
					$vars["len"]                 = $length;
					$vars["def"]                 = $default;
					$vars["aw"]                  = $allowedValues;
					$vars["in"]                  = ($isNull) ? "TRUE" : "FALSE";
					$vars["isAi"]                = ($isAi) ? "TRUE" : "FALSE";//isAuto Increment
					$templateVars["columnTypes"] .= '
		' . Variable::assign($vars, 'self::$columnStructure[' . "'%fn%'] = ['type'=>'%t%','signed'=>%sig%,'length'=>%len%,'default'=>%def%,'allowedValues'=>[%aw%],'isNull'=>%in%,'isAI'=>%isAi%];");
					
					if ($Column["Extra"] == "auto_increment")
					{
						$templateVars["aiColumn"] = "'" . $columnName . "'";
					}
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
				
				$max = 0;
				foreach (explode("\n", $templateVars['columnTypes']) as $line)
				{
					$line = trim($line);
					$max  = max($max, strlen(substr($line, 0, strpos($line, '=') + 1)));
				}
				foreach (explode("\n", $templateVars['columnTypes']) as $line)
				{
					$line = trim($line);
					if ($line)
					{
						$b                           = substr($line, 0, strpos($line, '=') + 1);
						$len                         = strlen($b);
						$f                           = str_replace('=', str_pad(" ", ($max - $len) + 1) . '=', $b);
						$templateVars['columnTypes'] = str_replace($b, $f, $templateVars['columnTypes']);
					}
				}
				$templateVars['columnTypes'] = ltrim($templateVars['columnTypes']);
				
				$modelImports = [];
				foreach ($this->opt->getModelImports($model) as $ik => $name)
				{
					$modelImports[$ik] = "use $name;";
				}
				$templateVars['modelImports']   = $modelImports ? join("\n", $modelImports) : self::REMOVE_EMPTY_LINE;
				$templateVars['modelNamespace'] = self::REMOVE_EMPTY_LINE;
				if ($this->opt->getNamespace())
				{
					$templateVars['modelNamespace'] = 'namespace ' . $this->opt->getNamespace() . ';';
				}
				
				$templateVars['node']                       = $this->getModelNodeContent($templateVars, $model);
				$templateVars['dataMethodsClass']           = $this->opt->getModelDataMethodsClass($model);
				$templateVars['dataMethods']                = $this->getModelDataMethodsClassContent($templateVars, $model);
				$templateVars['modelDefaultConnectionName'] = $this->opt->getModelConnectionName($model);
				$templateVars['modelNewClass']              = $this->constructFullName($model);
				$templateVars['dbName']                     = $this->dbName;
				$templateVars['modelColumnClassName']       = $this->opt->getModelColumnClass($model);
				$templateVars['loggerEnabled']              = $this->opt->isModelLogEnabled($model) ? 'true' : 'false';
				$templateVars['useModelColumnClass']        = $templateVars['modelColumnClassName'][0] == '\\' ? substr($templateVars['modelColumnClassName'], 1) : $templateVars['modelColumnClassName'];
				$ex                                         = explode('\\', $templateVars['modelColumnClassName']);
				$templateVars['modelColumnClassLastName']   = end($ex);
				
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
		
		$vars['dataMethodsClassName'] = $vars['dataMethodsClass'] = $model . 'DataMethods';
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