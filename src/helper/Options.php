<?php

namespace Infira\pmg\helper;


use Infira\Utils\Dir;
use Infira\Utils\Regex;
use Infira\Utils\File;
use stdClass;
use Exception;
use Infira\console\helper\Config;
use Infira\console\Bin;

class Options extends Config
{
	public function __construct(string $yamlPath)
	{
		parent::__construct(Bin::getPath('defaults.yaml'));
		$this->mergeConfig($yamlPath);
		
		$default = $this->get('model');
		if ($this->exists('models')) {
			foreach ($this->get('models') as $model => $conf) {
				$this->set("models.$model", $default);
				foreach ($conf as $name => $value) {
					if (!$this->exists("model.$name")) {
						throw new Exception("default model conf('$name') does not exists");
					}
					$defaultType = gettype($this->get("model.$name"));
					$type        = gettype($value);
					if ($defaultType == 'array' and $type != 'array') {
						throw new Exception("cant merge non arrays of conf('$name')");
					}
					else {
						$this->setModelConfig($model, $name, $value);
					}
				}
			}
		}
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
	
	private function addToModelConfig(string $model, string $config, $configValue)
	{
		$this->checkModel($model);
		$this->add("models.$model.$config", $configValue);
	}
	
	private function getModelConfig(string $model): array
	{
		$this->checkModel($model);
		
		return $this->config['models'][$model];
	}
	
	public function isTableVoided(string $table): bool
	{
		return in_array($table, $this->config['voidTables']);
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
		foreach (Dir::getFileNames($path) as $fn) {
			$file = Dir::fixPath($path) . $fn;
			if ($dm = $this->findFileExtension($file, 'Model')) {
				$this->setModelExtender($dm->model, $dm->name);
			}
			elseif ($dm = $this->findFileExtension($file, 'Trait')) {
				$this->addModelTrait($dm->model, $dm->name);
			}
			elseif ($dm = $this->findFileExtension($file, 'DataMethods')) {
				$this->setDataMethodsClass($dm->model, $dm->name);
			}
			elseif ($dm = $this->findFileExtension($file, 'Node')) {
				$this->setModelNodeExtendor($dm->model, $dm->name);
			}
		}
	}
	
	private function findFileExtension($file, $type): ?stdClass
	{
		$pi       = (object)pathinfo($file);
		$fileName = $pi->filename;
		
		if (!preg_match('/(.+)(' . $type . '.*)/m', $fileName, $matches)) {
			return null;
		}
		$model = $matches[1];
		$name  = '\\' . $matches[0];
		
		$fileContent = File::getContent($file);
		if (Regex::isMatch('/namespace (.+)?;/m', $fileContent)) {
			$matches = [];
			preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
			$name = '\\' . $matches[1][0] . $name;
		}
		
		return (object)['model' => $model, 'name' => $name];
	}
	
	public function getNamespace(): ?string
	{
		return $this->config['namespace'];
	}
	
	public function getDestinationPath(): string
	{
		return $this->config['destinationPath'];
	}
	
	public function setDestinationPath(string $path)
	{
		$this->config['destinationPath'] = $path;
	}
	
	public function getExtensionsPath(): ?string
	{
		return $this->config['extensionsPath'];
	}
	
	public function setExtensionsPath(string $path)
	{
		$this->config['extensionsPath'] = $path;
	}
	
	public function getColumnClass(string $model): ?string
	{
		return $this->getModelConfig($model)['columnClass'];
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
		
		return $this->getModelConfig($table)['extender'] ?? '\Infira\Poesis\orm\Model';
	}
	
	public function addModelTrait(string $model, string $trait)
	{
		$this->addToModelConfig($model, 'traits', $trait);
	}
	
	public function getModelTraits(string $model): array
	{
		return $this->getModelConfig($model)['traits'];
	}
	
	public function getModelInterfaces(string $model): array
	{
		$interfaces   = $this->getModelConfig($model)['implements'];
		$interfaces[] = '\Infira\Poesis\orm\ModelContract';
		
		return $interfaces;
	}
	
	public function getModelImports(string $model): array
	{
		return $this->getModelConfig($model)['imports'];
	}
	
	public function getModelClassNamePrefix(): string
	{
		return $this->config['model']['prefix'];
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
		return $this->getDataMethodsConfig($model)['class'] ?? '\Infira\Poesis\dr\DataMethods';
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
	
	public function getNodeExtender(string $model): ?string
	{
		return $this->getModelNodeConfig($model)['extender'] ?? '\Infira\Poesis\orm\Node';
	}
	
	public function getMakeNode(string $model): bool
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