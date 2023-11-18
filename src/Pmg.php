<?php

namespace Infira\pmg;


use Illuminate\Support\Str;
use Infira\console\Command;
use Infira\console\Console;
use Infira\pmg\helper\Db;
use Infira\pmg\helper\ModelColumn;
use Infira\pmg\helper\Options;
use Infira\pmg\templates\ClassPrinter;
use Infira\pmg\templates\ClassTemplate;
use Infira\pmg\templates\DataMethods;
use Infira\pmg\templates\DbSchema;
use Infira\pmg\templates\ModelShortcut;
use Infira\pmg\templates\Model;
use Infira\pmg\templates\Utils;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Printer;
use Wolo\Regex;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Console\Input\InputArgument;
use Wolo\File\File;
use Wolo\File\Path;

class Pmg extends Command
{
    const REMOVE_EMPTY_LINE = '[REMOVE_EMPTY_LINE]';
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
        $yamlFile = $this->input->getArgument('yaml');

        if (!file_exists($yamlFile)) {
            $this->error('Config files does not exist');
        }
        $this->opt = new Options($yamlFile);
        $destinationPath = $this->opt->getDestinationPath();
        $extensionsPath = $this->opt->getExtensionsPath();

        $yamlDirName = dirname($yamlFile);
        if ($extensionsPath) {
            if ($extensionsPath[0] !== '/') {
                $extensionsPath = realpath(Path::join($yamlDirName, $extensionsPath));
                if (!is_dir($extensionsPath)) {
                    $this->error("extensions path('$extensionsPath') not found");
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


        if ($destinationPath[0] !== '/') {
            $destinationPath = realpath(Path::join($yamlDirName, $destinationPath));
        }
        if (!is_dir($destinationPath)) {
            $this->error("destination path('$destinationPath') not found");
        }
        if (!is_writable($destinationPath)) {
            $this->error("destination path('$destinationPath') not writable");
        }
        $this->opt->setDestinationPath($destinationPath);


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

    private function constructFullName(string $name): string //TODO miks seda kas see ei võiks kusagil make juures olla?
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
                $prefix = $this->opt->getModelClassNamePrefix() ? $this->opt->getModelClassNamePrefix().'_' : '';


                $modelName = Utils::className($prefix.$tableName);
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
