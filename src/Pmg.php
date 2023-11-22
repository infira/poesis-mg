<?php

namespace Infira\pmg;


use Illuminate\Support\Str;
use Infira\console\Command;
use Infira\console\Console;
use Infira\pmg\helper\Db;
use Infira\pmg\helper\ModelColumn;
use Infira\pmg\helper\Options;
use Infira\pmg\templates\ClassPrinter;
use Infira\pmg\templates\DbSchema;
use Infira\pmg\templates\Model;
use Infira\pmg\templates\ModelShortcut;
use Infira\pmg\templates\Utils;
use Symfony\Component\Console\Input\InputArgument;
use Wolo\File\Path;
use Wolo\Regex;

class Pmg extends Command
{
    private string $dbName = '';
    private Db $db;
    private Options $opt;
    private array $madeFiles = [];
    private ModelShortcut $shortcut;
    private DbSchema $schema;

    public function __construct()
    {
        parent::__construct('create');
    }

    public function error($msg): void
    {
        Console::$output->error($msg);
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
        $yamlFile = realpath($this->input->getArgument('yaml'));
        if (!file_exists($yamlFile)) {
            $this->error('Config files does not exist');
        }
        $this->opt = new Options($yamlFile);
        $yamlPath = dirname($yamlFile);
        $destinationPath = $this->opt->getDestinationPath();
        if ($destinationPath[0] !== '/') {
            $destinationPath = Path::join($yamlPath, $destinationPath);
        }
        if ($this->validateWriteablePath($destinationPath)) {
            $this->opt->setDestinationPath($destinationPath);
        }
        else {
            $this->error("destination path('$destinationPath') is not a folder or not writable");
        }
        $extensionsPath = $this->opt->getExtensionsPath();
        if ($extensionsPath[0] !== '/') {
            $extensionsPath = Path::join($yamlPath, $extensionsPath);
        }
        if ($this->validateWriteablePath($extensionsPath)) {
            $this->opt->setExtensionsPath($extensionsPath);
        }
        else {
            $this->error("extensions path('$extensionsPath') is not a folder or not writable");
        }
        $connection = (object)$this->opt->get('connection');
        $this->db = new Db('pmg', $connection->host, $connection->user, $connection->pass, $connection->db, $connection->port);
        $this->dbName = $connection->db;
        $this->opt->scanExtensions();


        $this->shortcut = new ModelShortcut($this->opt);
        $this->schema = new DbSchema($this->opt);
        $this->makeTableClassFiles();
        $this->shortcut->addImports($this->opt->getShortcutImports());
        $this->madeFiles[] = $this->shortcut->save();
        $this->madeFiles[] = $this->schema->save();

        $this->output->region('Made models', function () {
            if ($this->output->isVerbose()) {
                foreach ($this->madeFiles as $file) {
                    //$this->output->msg('<fg=#00aaff>Installed file</>: ' . str_replace($this->opt->getDestinationPath(), '', $file));
                    $this->output->msg('<fg=#00aaff>Installed file</>: '.$file);
                }
            }
            else {
                $this->output->info('Made '.count($this->madeFiles).' models into '.$this->opt->getDestinationPath());
            }
        });
    }

    private function validateWriteablePath(string $path): bool
    {
        $path = realpath($path);
        if (!$path) {
            return false;
        }
        if (!is_dir($path)) {
            return false;
        }
        if (!is_writable($path)) {
            return false;
        }
        return true;
    }

    private function makeModelName(string $table): string
    {
        if ($tableNamePattern = $this->opt->getModelTableNamePattern()) {
            if ($match = Regex::match($tableNamePattern, $table)) {
                $table = $match;
            }
        }
        $prefix = $this->opt->getModelClassNamePrefix() ? $this->opt->getModelClassNamePrefix().'_' : '';
        return Utils::className($prefix.$table);
    }

    private function constructFullName(string $name): string
    {
        $name = Utils::className($name);

        return $this->opt->getNamespace() ? $this->opt->getNamespace().'\\'.$name : $name;
    }

    /**
     * @throws \Exception
     */
    private function makeTableClassFiles(): void
    {
        //$model             = new Model(['isGenerator' => true]);
        $notAllowedColumns = [];//get_class_methods($model);

        $tables = $this->db->query("SHOW FULL TABLES");
        if ($tables) {
            $tablesData = [];
            while ($Row = $tables->fetch_object()) {
                $columnName = "Tables_in_".$this->dbName;
                $tableName = $Row->$columnName;
                if ($this->opt->canMake($tableName)) {
                    unset($Row->$columnName);
                    unset($dbName);
                    $columnsRes = $this->db->query("SHOW FULL COLUMNS FROM`".$tableName.'`');

                    if (!isset($tablesData[$tableName])) {
                        $Table = $Row;
                        $Table->columns = [];
                        $tablesData[$tableName] = $Table;
                    }

                    while ($columnInfo = $columnsRes->fetch_array(MYSQLI_ASSOC)) {
                        $tablesData[$tableName]->columns[$columnInfo['Field']] = $columnInfo;
                        if (in_array($columnInfo['Field'], $notAllowedColumns)) {
                            $this->error('Column <strong>'.$tableName.'.'.$columnInfo['Field'].'</strong> is system reserverd');
                        }
                    }
                }
            }

            foreach ($tablesData as $tableName => $Table) {
                $modelName = $this->makeModelName($tableName);
                $modelTemplate = new Model(
                    $modelName,
                    $tableName,
                    $this->opt
                );
                $modelTemplate->setModelExtender($this->opt->getModelExtender($modelName, $Table->Table_type === 'VIEW'));
                if ($this->opt->isModelLogEnabled($modelName)) {
                    $modelTemplate->addSchemaProperty('log', true);
                }
                if (($connectionName = $this->opt->getModelConnectionName($modelName)) !== 'defaultConnection') {
                    $modelTemplate->addSchemaProperty('connection', $connectionName);
                }


                if ($Table->Table_type === 'VIEW') {
                    $modelTemplate->addSchemaProperty('isView', true);
                }
                $TIDColumnName = $this->opt->getTIDColumnName($modelName);
                if ($TIDColumnName !== null && isset($Table->columns[$TIDColumnName])) {
                    $modelTemplate->addSchemaProperty('TIDColumn', $TIDColumnName);
                }

                foreach ($this->opt->getModelTraits($modelName) as $trait) {
                    $modelTemplate->addTrait($trait);
                }
                foreach ($this->opt->getModelInterfaces($modelName) as $interface) {
                    $modelTemplate->addImplement($interface);
                }

                if ($result = $this->db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")) {
                    while ($Index = $result->fetch_object()) {
                        $modelTemplate->addPrimaryColumn($Index->Column_name);
                    }
                }

                $this->shortcut->addModel($this->constructFullName($modelName));

                foreach ($Table->columns as $Column) {
                    $columnName = $Column['Field'];
                    if ($columnName == 'ttest') {
                        debug($Column);
                        continue;
                    }
                    $type = Str::lower(preg_replace('/\(.*\)/m', '', $Column['Type']));
                    $type = strtolower(trim(str_replace("unsigned", "", $type)));
                    $Column['fType'] = $type;
                    $modelColumn = new ModelColumn($Column, $tableName);

                    $modelTemplate->setColumn($modelColumn);

                    if ($modelColumn->isAutoIncrement()) {
                        $modelTemplate->addSchemaProperty('aiColumn', $columnName);
                    }

                    $this->schema->setColumn($modelColumn);
                } //EOF each columns

                //make index methods
                $indexes = [];
                if ($result = $this->db->query("SHOW INDEX FROM `$tableName`")) {
                    while ($Index = $result->fetch_object()) {
                        $indexes[$Index->Key_name][] = $Index;
                    }
                }
                $indexMethods = array_filter($indexes, static function ($var) {
                    return count($var) > 1;
                });
                $modelTemplate->addIndexMethods($indexMethods);
                $modelTemplate->addImports($this->opt->getModelImports($modelName));
                $this->madeFiles[] = $modelTemplate->save(ClassPrinter::class);
            }
        }
    }
}
