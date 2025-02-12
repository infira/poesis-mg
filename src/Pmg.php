<?php

namespace Infira\pmg;


use Illuminate\Support\Str;
use Infira\Console\Command;
use Infira\pmg\helper\Db;
use Infira\pmg\helper\ModelColumn;
use Infira\pmg\helper\Options;
use Infira\pmg\templates\ClassPrinter;
use Infira\pmg\templates\DbSchema;
use Infira\pmg\templates\Model;
use Infira\pmg\templates\ModelShortcut;
use Infira\pmg\templates\Utils;
use stdClass;
use Symfony\Component\Console\Input\InputArgument;
use Wolo\File\File;
use Wolo\File\Folder;
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
        $this->console->error($msg);
    }

    public function configure(): void
    {
        $this->addArgument('yaml', InputArgument::REQUIRED);
    }

    public function runCommand(): void
    {
        $yamlArgument = $this->input->getArgument('yaml');
        $yamlFile = realpath($yamlArgument);
        if (!file_exists($yamlFile)) {
            $this->error("Config file('$yamlArgument') does not exist");
            return;
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
        $this->scanExtensions();


        $this->shortcut = new ModelShortcut($this->opt);
        $this->schema = new DbSchema($this->opt);
        $this->makeTableClassFiles();
        $this->shortcut->addImports($this->opt->getShortcutImports());
        $this->madeFiles[] = $this->shortcut->save();
        $this->madeFiles[] = $this->schema->save();

        $this->console->region('Made models', function () {
            if ($this->console->isVerbose()) {
                foreach ($this->madeFiles as $file) {
                    $this->console->write('<fg=#00aaff>Installed file</>: '.$file);
                }
            }
            else {
                $this->console->writeln('<info>Made '.count($this->madeFiles).' models into '.$this->opt->getDestinationPath().'</info>');
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

    private function constructFullName(string $name): string
    {
        $name = Utils::className($name);

        return $this->opt->getNamespace() ? $this->opt->getNamespace().'\\'.$name : $name;
    }

    private function scanExtensions(): void
    {
        $path = $this->opt->getExtensionsPath();
        if (!is_dir($path)) {
            $this->error("scan model extensions folder must be correct path($path)");
        }
        foreach (Folder::fileNames($path) as $fn) {
            $file = Path::join($path, $fn);
            if ($dm = $this->getExtensionAttributes($file, 'Model')) {
                $this->opt->setModelExtender($dm->model, $dm->name);
            }
            else if ($dm = $this->getExtensionAttributes($file, 'Trait')) {
                $this->opt->addModelTrait($dm->model, $dm->name);
            }
            else if ($dm = $this->getExtensionAttributes($file, 'DataMethods')) {
                $this->opt->setModelDataMethodsClass($dm->model, $dm->name);
            }
            else if ($dm = $this->getExtensionAttributes($file, 'Node')) {
                $this->opt->setModelExtender($dm->model, $dm->name);
            }
        }
    }

    private function getExtensionAttributes($file, $type): ?stdClass
    {
        $pi = (object)pathinfo($file);
        $fileName = $pi->filename;

        if (!preg_match('/(.+)('.$type.'.*)/m', $fileName, $matches)) {
            return null;
        }
        $model = $matches[1];
        $name = '\\'.$matches[0];

        $fileContent = File::content($file);
        if (Regex::match('/namespace (.+)?;/m', $fileContent)) {
            $matches = [];
            preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
            $name = '\\'.$matches[1][0].$name;
        }

        return (object)['model' => $model, 'name' => $name];
    }

    private function makeTableClassFiles(): void
    {
        //$model             = new Model(['isGenerator' => true]);
        $notAllowedColumns = [];//get_class_methods($model);

        $tables = $this->db->query("SHOW FULL TABLES");
        if (!$tables) {
            return;
        }
        $tablesData = [];
        while ($Table = $tables->fetch_object()) {
            $columnName = "Tables_in_".$this->dbName;
            $tableName = $Table->$columnName;
            if (!$this->opt->canMakeModel($tableName)) {
                continue;
            }
            $modelName = $this->opt->makeModelName($tableName);
            $modelTemplate = new Model(
                $modelName,
                $tableName,
                $Table,
                $this->opt
            );
            $this->shortcut->addModel($this->constructFullName($modelName));
            if ($result = $this->db->query("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")) {
                while ($Index = $result->fetch_object()) {
                    $modelTemplate->addPrimaryColumn($Index->Column_name);
                }
            }

            $columnsRes = $this->db->query("SHOW FULL COLUMNS FROM`".$tableName.'`');
            while ($column = $columnsRes->fetch_array(MYSQLI_ASSOC)) {
                if (in_array($column['Field'], $notAllowedColumns)) {
                    $this->error('column <strong>'.$tableName.'.'.$column['Field'].'</strong> is system reserverd');
                    break;
                }

                $columnName = $column['Field'];
                if ($columnName == 'ttest') {
                    debug($column);
                    break;
                }
                $type = Str::lower(preg_replace('/\(.*\)/m', '', $column['Type']));
                $type = strtolower(trim(str_replace("unsigned", "", $type)));
                $column['fType'] = $type;
                $modelColumn = new ModelColumn($column, $tableName);

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
            $modelTemplate->addImports($this->opt->getModelImports($tableName));
            $this->madeFiles[] = $modelTemplate->save(ClassPrinter::class);
        }
    }
}
