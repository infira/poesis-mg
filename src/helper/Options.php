<?php

namespace Infira\pmg\helper;


use Exception;
use Infira\pmg\templates\Utils;
use InvalidArgumentException;
use Wolo\File\Path;
use Wolo\Regex;

class Options extends Config
{
    public static string $defaultDataModelsClass = '\Infira\Poesis\dr\DataMethods';

    public function __construct(string $yamlPath)
    {
        parent::__construct($yamlPath);
        $default = $this->get('model');

        if ($this->exists('models')) {
            foreach ($this->get('models') as $model => $conf) {
                $this->set("models.$model", array_merge($default, $conf));
                foreach ($conf as $name => $value) {
                    if (!$this->exists("model.$name")) {
                        throw new Exception("default model conf('$name') does not exists");
                    }
                    $defaultType = gettype($this->get("model.$name"));
                    $type = gettype($value);
                    if ($defaultType == 'array' and $type != 'array') {
                        throw new Exception("cant merge non arrays of conf('$name')");
                    }
                }
            }
        }
    }

    public function getNamespace(string $default = ''): ?string
    {
        return $this->get('namespace', $default) ?? $default;
    }

    public function getDestinationPath(string ...$path): string
    {
        if (!$this->config['destinationPath']) {
            throw new \RuntimeException('destinationPath not defined');
        }

        return Path::join($this->config['destinationPath'], ...$path);
    }

    public function setDestinationPath(string $path): void
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

    //region model
    public function canMakeModel(string $table): bool
    {
        if (in_array($table, $this->get('voidTables', []))) {
            return false;
        }
        $makeOnly = $this->get('makeOnlyTables', []);
        if (!is_array($makeOnly)) {
            return true;
        }
        if (count($makeOnly) <= 0) {
            return true;
        }

        return in_array($table, $makeOnly, true);
    }

    public function makeModelName(string $table): string
    {
        if ($tableNamePattern = $this->getModelTableNamePattern()) {
            if ($match = Regex::match($tableNamePattern, $table)) {
                $table = $match;
            }
        }
        if ($this->exists("tables.$table.modelName")) {
            return $this->get("tables.$table.modelName");
        }
        $prefix = $this->get('model.prefix', '');
        $prefix = $prefix ? $prefix.'_' : '';
        return Utils::className($prefix.$table);
    }

    private function setModelConfig(string $model, string $config, $configValue): void
    {
        $this->set("models.$model.$config", $configValue);
    }

    public function getModelConfig(string $model, string $config, mixed $default = null): mixed
    {
        if (!$this->exists("model.$config")) {
            throw new InvalidArgumentException("unknown model config('$config') does not exists");
        }
        $output = $this->get("models.$model.$config", $this->getOnEmpty("model.$config", $default));
        if (!$output) {
            return $default;
        }
        return $output;
    }

    private function addToModelConfig(string $model, string $config, array $configValues): void
    {
        foreach ($configValues as $value) {
            $this->add("models.$model.$config", $value);
        }
    }

    public function addModelTrait(string $model, string ...$trait): void
    {
        $this->addToModelConfig($model, 'traits', $trait);
    }

    public function getModelTraits(string $table): array
    {
        return $this->getModelConfig($this->makeModelName($table), 'traits', []);
    }

    public function addModelInterface(string $model, string ...$trait)
    {
        $this->addToModelConfig($model, 'interfaces', $trait);
    }

    public function addModelImport(string $model, string ...$trait)
    {
        $this->addToModelConfig($model, 'imports', $trait);
    }

    public function setModelExtender(string $model, string $extender): void
    {
        $this->setModelConfig($model, 'extender', $extender);
    }

    public function getModelColumnClass(string $table): string
    {
        return $this->getModelConfig($this->makeModelName($table), 'columnClass', '\Infira\Poesis\clause\ModelColumn');
    }

    public function getModelExtender(string $table, bool $isView): string
    {
        $model = $this->makeModelName($table);
        $extender = $this->getModelConfig($model, 'extender', '\Infira\Poesis\Model');
        if ($isView) {
            $extender = $this->getModelConfig($model, 'viewExtender', $extender);
        }
        return $extender;
    }

    public function getModelInterfaces(string $table): array
    {
        return $this->getModelConfig($this->makeModelName($table), 'interfaces', []);
    }

    public function getModelImports(string $table): array
    {
        return $this->getModelConfig($this->makeModelName($table), 'imports', []);
    }

    public function getModelTableNamePattern(): ?string
    {
        return $this->config['model']['modelTableNamePattern'] ?? null;
    }

    public function getModelFileNameExtension(): string
    {
        return $this->config['model']['fileExt'];
    }

    public function getModelConnectionName(string $table): string
    {
        return $this->getModelConfig($this->makeModelName($table), 'connectionName', 'defaultConnection');
    }

    public function getTIDColumnName(string $table): ?string
    {
        return $this->getModelConfig($this->makeModelName($table), 'TIDColumName');
    }

    public function isModelLogEnabled(string $table): bool
    {
        return $this->getModelConfig($this->makeModelName($table), 'log', false);
    }

    public function getModelDataMethodsClass(string $table): string
    {
        return $this->getModelConfig($this->makeModelName($table), 'dataMethods.class', self::$defaultDataModelsClass);
    }

    public function setModelDataMethodsClass(string $model, string $extender): void
    {
        $this->setModelConfig($model, 'dataMethods.class', $extender);
    }

    public function getModelDataMethodsTraits(string $table): array
    {
        return $this->getModelConfig($this->makeModelName($table), 'dataMethods.traits', []);
    }

    public function getModelNodeExtender(string $table): string
    {
        return $this->getModelConfig($this->makeModelName($table), 'dataMethods.node.extender', '\Infira\Poesis\dr\Node');
    }

    public function getMakeModelNode(string $table): bool
    {
        return (bool)$this->getModelConfig($this->makeModelName($table), 'dataMethods.node');
    }

    //endregion

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

}