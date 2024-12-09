<?php

namespace Infira\pmg\helper;


use Exception;
use Nette\PhpGenerator\ClassType;
use stdClass;
use Wolo\File\File;
use Wolo\File\Folder;
use Wolo\File\Path;
use Wolo\Regex;

class Options extends Config
{
    public static string $defaultDataModelsClass = '\Infira\Poesis\dr\DataMethods';

    public function __construct(string $yamlPath)
    {
        parent::__construct($yamlPath);
        $default = $this->get('model');

        foreach (['modelExtends' => 'setModelExtender', 'modelTraits' => 'addModelTrait', 'modelInterfaces' => 'addModelInterface', 'modelImports' => 'addModelImport'] as $globalConf => $method) {
            if (!$this->exists($globalConf)) {
                continue;
            }
            foreach ($this->get($globalConf) as $class => $models) {
                foreach ($models as $model) {
                    if (!$this->exists("models.$model")) {
                        $this->set("models.$model", $default);
                    }
                    $this->$method($model, $class);
                }
            }
        }

        if ($this->exists('models')) {
            foreach ($this->get('models') as $model => $conf) {
                $this->set("models.$model", $default);
                foreach ($conf as $name => $value) {
                    if (!$this->exists("model.$name")) {
                        throw new Exception("default model conf('$name') does not exists");
                    }
                    $defaultType = gettype($this->get("model.$name"));
                    $type = gettype($value);
                    if ($defaultType == 'array' and $type != 'array') {
                        throw new Exception("cant merge non arrays of conf('$name')");
                    }
                    else {
                        $this->setModelConfig($model, $name, $value);
                    }
                }
            }
        }

        $this->set('customModels', []);
    }

    private function checkModel(string $model)
    {
        if (!isset($this->config['models'][$model])) {
            $this->set("models.$model", $this->config['model']);
        }
    }

    private function setModelConfig(string $model, string $config, $configValue)
    {
        $this->checkModel($model);
        $this->set("models.$model.$config", $configValue);
    }

    private function addToModelConfig(string $model, string $config, array $configValues)
    {
        $this->checkModel($model);
        foreach ($configValues as $value) {
            $this->add("models.$model.$config", $value);
        }
    }

    private function getModelConfig(string $model): array
    {
        $this->checkModel($model);

        return $this->config['models'][$model];
    }

    private function getConfig(string $name, $default = null)
    {
        if (array_key_exists($name, $this->config)) {
            return $this->config[$name];
        }

        return $default;
    }

    public function isTableVoided(string $table): bool
    {
        $tables = $this->getConfig('voidTables', []);
        if (!is_array($tables)) {
            return false;
        }

        return in_array($table, $tables, true);
    }

    public function canMake(string $table): bool
    {
        if ($this->isTableVoided($table)) {
            return false;
        }
        $makeOnly = $this->getConfig('makeOnlyTables', []);
        if (!is_array($makeOnly)) {
            return true;
        }
        if (count($makeOnly) <= 0) {
            return true;
        }

        return in_array($table, $makeOnly, true);
    }

    public function scanExtensions()
    {
        $path = $this->getExtensionsPath();
        if ($path === null) {
            return;
        }
        if (!is_dir($path)) {
            throw new Exception("scan model extensions folder must be correct path($path)");
        }
        foreach (Folder::fileNames($path) as $fn) {
            $file = Path::join($path, $fn);
            if ($dm = $this->findFileExtension($file, 'Model')) {
                $this->setModelExtender($dm->model, $dm->name);
            }
            else if ($dm = $this->findFileExtension($file, 'Trait')) {
                $this->addModelTrait($dm->model, $dm->name);
            }
            else if ($dm = $this->findFileExtension($file, 'DataMethods')) {
                $this->setDataMethodsClass($dm->model, $dm->name);
            }
            else if ($dm = $this->findFileExtension($file, 'Node')) {
                $this->setModelExtender($dm->model, $dm->name);
            }
        }
    }

    private function findFileExtension($file, $type): ?stdClass
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

    public function getNamespace(string $default = ''): ?string
    {
        return $this->getConfig('namespace', $default) ?? $default;
    }

    public function getDestinationPath(string ...$path): string
    {
        if (!$this->config['destinationPath']) {
            throw new \RuntimeException('destinationPath not defined');
        }

        return Path::join($this->config['destinationPath'], ...$path);
    }

    public function setDestinationPath(string $path)
    {
        $this->config['destinationPath'] = $path;
    }

    public function getExtensionsPath(string ...$path): string
    {
        if (!$this->config['extensionsPath']) {
            throw new \RuntimeException('extensionsPath not defined');
        }

        return Path::join($this->config['extensionsPath'], ...$path);
    }

    public function setExtensionsPath(string $path): void
    {
        $this->config['extensionsPath'] = $path;
    }

    public function getColumnClass(string $model): ?string
    {
        return $this->getModelConfig($model)['columnClass'];
    }

    public function hasCustomModel(string $name): bool
    {
        return $this->exists("customModels.$name");
    }

    public function getCustomModel(string $name): ClassType //TODO not need it anymore
    {
        return $this->get("customModels.$name");
    }

    public function setModelExtender(string $model, string $extender)
    {
        $this->setModelConfig($model, 'extender', $extender);
    }

    public function getModelExtender(string $table, bool $isView): string
    {
        if ($isView and $this->getModelConfig($table)['viewExtender']) {
            return $this->getModelConfig($table)['viewExtender'];
        }

        return $this->getModelConfig($table)['extender'] ?? '\Infira\Poesis\Model';
    }

    public function addModelTrait(string $model, string ...$trait)
    {
        $this->addToModelConfig($model, 'traits', $trait);
    }

    public function getModelTraits(string $model): array
    {
        return $this->getModelConfig($model)['traits'];
    }

    public function getModelInterfaces(string $model): array
    {
        $interfaces = $this->getModelConfig($model)['interfaces'] ?? [];

        return $interfaces;
    }

    public function addModelInterface(string $model, string ...$trait)
    {
        $this->addToModelConfig($model, 'interfaces', $trait);
    }

    public function getModelImports(string $model): array
    {
        return $this->getModelConfig($model)['imports'];
    }

    public function addModelImport(string $model, string ...$trait)
    {
        $this->addToModelConfig($model, 'imports', $trait);
    }

    public function getModelClassNamePrefix(): string
    {
        return $this->config['model']['prefix'];
    }

    public function getModelTableNamePattern(): ?string
    {
        return $this->config['model']['modelTableNamePattern'] ?? null;
    }

    public function getModelFileNameExtension(): string
    {
        return $this->config['model']['fileExt'];
    }

    public function getModelConnectionName(string $model): string
    {
        return $this->getModelConfig($model)['connectionName'];
    }

    public function getTIDColumnName(string $model): ?string
    {
        return $this->getModelConfig($model)['TIDColumName'];
    }

    public function isModelLogEnabled(string $model): bool
    {
        return $this->getModelConfig($model)['log'];
    }

    //region data methods
    private function getDataMethodsConfig(string $model): ?array
    {
        return $this->getModelConfig($model)['dataMethods'];
    }

    public function getDataMethodsClass(string $model): ?string
    {
        return $this->getDataMethodsConfig($model)['class'] ?? self::$defaultDataModelsClass;
    }

    public function setDataMethodsClass(string $model, string $extender)
    {
        $this->setModelConfig("$model", 'dataMethods.class', $extender);
    }

    public function getDataMethodsTraits(string $model): array
    {
        return $this->getDataMethodsConfig($model)['traits'];
    }

    private function getModelNodeConfig(string $model): ?array
    {
        return $this->getDataMethodsConfig($model)['node'];
    }

    public function getModelNodeExtender(string $model): ?string
    {
        return $this->getModelNodeConfig($model)['extender'] ?? '\Infira\Poesis\dr\Node';
    }

    public function getMakeModelNode(string $model): bool
    {
        return (bool)$this->getModelNodeConfig($model);
    }

    //endregion


    //region shortcut options
    public function getShortcutImports(): array
    {
        return $this->config['modelShortcut']['imports'];
    }

    public function getShortcutName(): string
    {
        return $this->config['modelShortcut']['name'];
    }

    public function getShortcutTraitFileNameExtension(): string
    {
        return $this->config['modelShortcut']['fileExt'];
    }
    //endregion
}