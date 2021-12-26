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
use Nette\PhpGenerator\PhpFile;
use Infira\pmg\templates\Utils;
use Infira\pmg\templates\DataMethods;

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
		
		if (!file_exists($yamlFile)) {
			$this->error('Config files does not exist');
		}
		$this->opt       = new Options($yamlFile);
		$destinationPath = $this->opt->getDestinationPath();
		$extensionsPath  = $this->opt->getExtensionsPath();
		
		if ($extensionsPath) {
			if ($extensionsPath[0] != '/') {
				$rp             = dirname($yamlFile) . '/' . $extensionsPath;
				$extensionsPath = Dir::fixPath(realpath(dirname($yamlFile) . '/' . $extensionsPath));
				if (!is_dir($extensionsPath)) {
					$this->error("extensions path $rp not found");
				}
			}
			if (!is_dir($extensionsPath)) {
				$this->error("extensions path $extensionsPath not found");
			}
			if (!is_writable($extensionsPath)) {
				$this->error('extensions path not writable');
			}
			$this->opt->setExtensionsPath($extensionsPath);
		}
		
		
		if ($destinationPath[0] != '/') {
			$rp              = dirname($yamlFile) . '/' . $destinationPath;
			$destinationPath = Dir::fixPath(realpath(dirname($yamlFile) . '/' . $destinationPath));
			if (!is_dir($destinationPath)) {
				$this->error("create path $rp not found");
			}
		}
		if (!is_dir($destinationPath)) {
			$this->error("create path $destinationPath not found");
		}
		if (!is_writable($destinationPath)) {
			$this->error('create path not writable');
		}
		$this->destination = Dir::fixPath($destinationPath);
		$this->opt->setDestinationPath($this->destination);
		
		
		$connection   = (object)$this->opt->get('connection');
		$this->db     = new Db('pmg', $connection->host, $connection->user, $connection->pass, $connection->db, $connection->port);
		$this->dbName = $connection->db;
		$this->opt->scanExtensions();
		
		
		$shortcutFileName = $this->opt->getShortcutName() . '.' . $this->opt->getShortcutTraitFileNameExtension();
		$flushExcept      = [$shortcutFileName, 'dummy.txt'];
		if (strpos($this->opt->getExtensionsPath(), $this->opt->getDestinationPath()) !== false) {
			$bn = str_replace($this->opt->getDestinationPath(), '', $this->opt->getExtensionsPath());
			if (substr($bn, -1) == '/') {
				$bn = substr($bn, 0, -1);
			}
			$flushExcept[] = $bn;
		}
		Dir::flushExcept($this->destination, $flushExcept);
		
		
		$shortcutFile     = new PhpFile();
		$shortcutPhpCType = $shortcutFile->addTrait($this->constructFullName($this->opt->getShortcutName()));
		$this->shortcut   = new ModelShortcutTemplate($shortcutPhpCType, $shortcutFile);
		$this->makeTableClassFiles();
		$this->shortcut->addImports($this->opt->getShortcutImports());
		$this->shortcut->finalise();
		
		$this->makeFile($shortcutFileName, $shortcutFile->__toString());
		
		$this->output->region('Made models', function ()
		{
			$this->output->dumpArray($this->madeFiles);
		});
	}
	
	private function constructFullName(string $name): string
	{
		$name = Utils::fixClassName($name);
		
		return $this->opt->getNamespace() ? $this->opt->getNamespace() . '\\' . $name : $name;
	}
	
	/**
	 * @throws \Exception
	 */
	private function makeTableClassFiles()
	{
		//$model             = new Model(['isGenerator' => true]);
		$notAllowedColumns = [];//get_class_methods($model);
		
		$tables = $this->db->query("SHOW FULL TABLES");
		if ($tables) {
			$tablesData = [];
			while ($Row = $tables->fetch_object()) {
				$columnName = "Tables_in_" . $this->dbName;
				$tableName  = $Row->$columnName;
				if (!$this->opt->isTableVoided($tableName)) {
					unset($Row->$columnName);
					unset($dbName);
					$columnsRes = $this->db->query("SHOW FULL COLUMNS FROM`" . $tableName . '`');
					
					if (!isset($tablesData[$tableName])) {
						$Table                  = $Row;
						$Table->columns         = [];
						$tablesData[$tableName] = $Table;
					}
					
					while ($columnInfo = $columnsRes->fetch_array(MYSQLI_ASSOC)) {
						$tablesData[$tableName]->columns[$columnInfo['Field']] = $columnInfo;
						if (in_array($columnInfo['Field'], $notAllowedColumns)) {
							$this->error('Column <strong>' . $tableName . '.' . $columnInfo['Field'] . '</strong> is system reserverd');
						}
					}
				}
			}
			
			foreach ($tablesData as $tableName => $Table) {
				$model = Utils::fixClassName($this->opt->getModelClassNamePrefix() . $tableName);
				
				$templateVars                   = [];
				$templateVars["tableName"]      = $tableName;
				$templateVars["className"]      = $model;
				$templateVars["nodeProperties"] = '';
				
				$modelFullName = $this->constructFullName($model);
				$schemaFull    = $this->constructFullName($model . 'Schema');
				
				$modelFile       = new PhpFile();
				$modelClassType  = $modelFile->addClass($modelFullName);
				$schemaClassType = $modelFile->addClass($schemaFull);
				$modelFile->addUse('\Infira\Poesis\Poesis');
				$modelFile->addUse('\Infira\Poesis\orm\node\Field');
				
				$schemaTemplate                = new SchemaTemplate($schemaClassType, $modelFile);
				$schemaTemplate->modelName     = $model;
				$schemaTemplate->tableName     = $tableName;
				$schemaTemplate->modelFullPath = '\\' . $modelFullName;//TODO Misk seda vaja on?
				
				
				$modelTemplate            = new ModelTemplate($modelClassType, $modelFile);
				$modelTemplate->tableName = $tableName;
				$modelTemplate->name      = $model;
				
				$schemaTemplate->isView = $Table->Table_type == "VIEW";
				$TIDColumnName          = $this->opt->getTIDColumnName($model);
				if ($TIDColumnName !== null and isset($Table->columns[$TIDColumnName])) {
					$schemaTemplate->TIDColumn = $TIDColumnName;
				}
				
				$modelTemplate->setTraits($this->opt->getModelTraits($model));
				
				if ($result = $this->db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")) {
					while ($Index = $result->fetch_object()) {
						$schemaTemplate->addPrimaryColumn($Index->Column_name);
					}
				}
				if ($mc = $this->opt->getModelExtender($model)) {
					$modelTemplate->setModelClass($mc);
				}
				
				$this->shortcut->addModel($model);
				
				foreach ($Table->columns as $Column) {
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
					
					if ($Column["Extra"] == "auto_increment") {
						$schemaTemplate->aiColumn = $columnName;
					}
					
					$signed        = (bool)strpos(strtolower($Column['Type']), "unsigned") !== false;
					$length        = null;
					$allowedValues = [];
					if (strpos($Column['Type'], "enum") !== false) {
						$allowedValues = [str_replace(["enum", "(", ")"], "", $Column['Type'])];
					}
					elseif (strpos($Column['Type'], "set") !== false) {
						$allowedValues = [str_replace(["set", "(", ")"], "", $Column['Type'])];
					}
					else {
						if (strpos($Column['Type'], "(")) {
							$length = str_replace(['(', ',', ')'], ['', '.', ''], Regex::getMatch('/\((.*)\)/m', $Column['Type']));
							if ($isNumber) {
								$ex     = explode(".", $length);
								$length = 'CLEAN=[\'d\'=>' . $ex[0] . ',\'p\'=>' . $ex[1] . ',\'fd\'=>' . ($ex[0] - $ex[1]) . ']';
							}
						}
					}
					if (in_array($type, ['timestamp', 'date', 'datetime']) or is_numeric($length)) {
						$length = intval($length);
					}
					if ($isAi) {
						$default = '';
					}
					elseif ($isInt or $isNumber) {
						$default = ($Column['Default'] === null) ? 'Poesis::NONE' : addslashes($Column['Default']);
					}
					else {
						if ($Column['Default'] === null and $isNull) {
							$default = null;
						}
						elseif ($Column['Default'] === null) {
							$default = 'Poesis::NONE';
						}
						elseif ($Column['Default'] == "''") {
							$default = '';
						}
						else {
							$default = addslashes($Column['Default']);
						}
						
					}
					$schemaTemplate->setColumn($columnName, $type, $signed, $length, $default, $allowedValues, $isNull, $isAi);
				} //EOF each columns
				
				//make index methods
				$indexes = [];
				if ($result = $this->db->query("SHOW INDEX FROM `$tableName`")) {
					while ($Index = $result->fetch_object()) {
						$indexes[$Index->Key_name][] = $Index;
					}
				}
				$indexMethods = array_filter($indexes, function ($var)
				{
					return count($var) > 1;
				});
				foreach ($indexMethods as $indexName => $columns) {
					$columnComment = [];
					$method        = $modelTemplate->createMethod(Utils::fixClassVarName($indexName) . '_index');
					foreach ($columns as $Col) {
						$columnComment[] = $Col->Column_name;
						$method->addParameter($Col->Column_name);
						$method->addBodyLine('$this->add(\'' . $Col->Column_name . '\', $' . $Col->Column_name . ')');
					}
					$method->addBodyLine('return $this;');
					$method->addComment('Set value for ' . join(', ', $columnComment) . ' index');
				}
				
				
				$modelTemplate->addImports($this->opt->getModelImports($model));
				
				if ($cc = $this->opt->getColumnClass($model)) {
					$modelTemplate->setColumnClass($cc);
				}
				$modelTemplate->loggerEnabled              = $this->opt->isModelLogEnabled($model);
				$modelTemplate->modelDefaultConnectionName = $this->opt->getModelConnectionName($model);
				$this->makeExtras($modelFile, $modelTemplate, $model);
				
				$modelTemplate->finalise();
				$schemaTemplate->finalise();
				
				$this->makeFile($model . '.' . $this->opt->getModelFileNameExtension(), $modelFile->__toString());
			}
		}
	}
	
	private function makeFile(string $fileName, $content)
	{
		$file = $this->destination . $fileName;
		File::delete($file);
		
		$content = str_replace('<?php', File::getContent(realpath(dirname(__FILE__)) . '/templates/php.txt'), $content);
		File::create($file, $content, "w+", 0777);
		
		$this->madeFiles[] = $file;
	}
	
	private function makeExtras(PhpFile &$phpFile, ModelTemplate &$model, string $modelName)
	{
		$existingDataMethodsClass = $this->opt->getDataMethodsClass($modelName);
		$defaultDataMethodsClass  = '\Infira\Poesis\dr\DataMethods';
		
		if (!$this->opt->getMakeNode($modelName) and $existingDataMethodsClass) {
			$model->setDataMethodsClass($existingDataMethodsClass);
		}
		
		if (!$this->opt->getMakeNode($modelName) and !$existingDataMethodsClass) {
			return;
		}
		$nodeDataMethodsFullName = $this->constructFullName($modelName . "NodeDataMethods");
		$dmClassType             = $phpFile->addClass($nodeDataMethodsFullName);
		$dataMethods             = new DataMethods($dmClassType, $phpFile);
		if ($existingDataMethodsClass) {
			
			$model->setDataMethodsClass($nodeDataMethodsFullName, true);
			$dataMethods->setExtends($existingDataMethodsClass);
		}
		else {
			$model->setDataMethodsClass($nodeDataMethodsFullName, false);
			$dataMethods->setExtends($defaultDataMethodsClass);
		}
		
		
		$nodeExtender = $this->opt->getNodeExtender($modelName);
		if ($nodeExtender) {
			$nodeExtender = $this->constructFullName($nodeExtender);
		}
		else {
			$nodeExtender = '\Infira\Poesis\orm\Node';
		}
		$phpFile->addUse($nodeExtender, 'Node');
		
		$dataMethods->setTraits($this->opt->getDataMethodsTraits($modelName));
		
		$getNode = $dataMethods->createMethod('getNode');
		$getNode->setReturnType($nodeExtender)->isReturnNullable();
		$getNode->addParameter('constructorArguments')->setType('array')->setDefaultValue([]);
		$getNode->addBodyLine('return $this->getObject(Node::class, $constructorArguments)');
		
		$getNodes = $dataMethods->createMethod('getNodes');
		$getNodes->setReturnType('array');
		$getNodes->addParameter('constructorArguments')->setType('array')->setDefaultValue([]);
		$getNodes->addBodyLine('return $this->getObjects(Node::class, $constructorArguments)');
		
		$dataMethods->finalise();
		
	}
	
	private function getTemplate($file, $vars = null): string
	{
		$file = realpath(dirname(__FILE__)) . '/templates/' . $file;
		if (!file_exists($file)) {
			$this->error("Installer $file not found");
		}
		$con = File::getContent($file);
		if ($vars) {
			return Variable::assign($vars, $con);
		}
		
		return $con;
	}
}
