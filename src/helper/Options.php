<?php

namespace Infira\pmg\helper;


use Infira\Utils\Dir;
use Infira\Utils\Regex;
use Infira\Utils\File;
use stdClass;
use Exception;
use Infira\Utils\Variable;
use Infira\console\helper\Config;
use Infira\console\Bin;

class Options extends Config
{
	public function __construct(string $yamlPath)
	{
		parent::__construct(Bin::getPath('defaults.yaml'));
		$this->mergeConfig($yamlPath);
	}
	
	private function setModelConfig(string $model, string $config, $configValue)
	{
		$this->set("models.$model.$config", $configValue);
	}
	
	private function addToModelConfig(string $model, string $config, $configValue)
	{
		$path = "models.$model.$config";
		if (!$this->exists($path))
		{
			$this->setModelConfig($model, $config, $this->config['model'][$config]);
		}
		$this->add($path, $configValue);
	}
	
	private function getModelConfig(string $model): array
	{
		if (!isset($this->config['models'][$model]))
		{
			return $this->config['model'];
		}
		
		return array_merge($this->config['model'], $this->config['models'][$model]);
	}
	
	public function isTableVoided(string $table): bool
	{
		return in_array($table, $this->config['voidTables']);
	}
	
	public function scanExtensions()
	{
		$path = $this->getExtensionsPath();
		if ($path === null)
		{
			return;
		}
		if (!is_dir($path))
		{
			throw new Exception("scan model extensions folder must be correct path($path)");
		}
		foreach (Dir::getFileNames($path) as $fn)
		{
			$file = Dir::fixPath($path) . $fn;
			if ($dm = $this->findFileExtension($file, 'Model'))
			{
				$this->setModelExtender($dm->model, $dm->extension);
			}
			elseif ($dm = $this->findFileExtension($file, 'Trait'))
			{
				$this->addModelTrait($dm->model, $dm->extension);
			}
			elseif ($dm = $this->findFileExtension($file, 'DataMethods'))
			{
				$this->setModelDataMethodsClass($dm->model, $dm->extension);
			}
			elseif ($dm = $this->findFileExtension($file, 'Node'))
			{
				$this->setModelNodeExtendor($dm->model, $dm->extension);
			}
			if (isset($dm))
			{
				foreach ($dm->imports as $import)
				{
					$this->addModelImport($dm->model, $import);
				}
			}
		}
	}
	
	private function findFileExtension($file, $type): ?stdClass
	{
		$pi       = (object)pathinfo($file);
		$fileName = $pi->filename;
		
		$len       = strlen($type) * -1;
		$extension = $fileName;
		if (substr($fileName, $len) == $type)
		{
			$model       = substr($fileName, 0, $len);
			$fileContent = File::getContent($file);
			$imports     = [];
			if (Regex::isMatch('/namespace (.+)?;/m', $fileContent))
			{
				$matches = [];
				preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
				$imports[] = '\\' . $matches[1][0] . '\\' . $extension;
			}
			else
			{
				$extension = '\\' . $extension;
			}
			
			return (object)['model' => $model, 'extension' => $extension, 'imports' => $imports];
		}
		
		return null;
	}
	
	public function getNamespace(): ?string
	{
		return $this->config['namespace'];
	}
	
	public function getDestinationPath(): string
	{
		return $this->config['destinationPath'];
	}
	
	public function getExtensionsPath(): ?string
	{
		return $this->config['extensionsPath'];
	}
	
	//region model options
	
	public function setModelExtender(string $model, string $extender)
	{
		$this->set("models.$model.extender", $extender);
	}
	
	public function getModelExtender(string $table): string
	{
		return $this->getModelConfig($table)['extender'];
	}
	
	public function addModelTrait(string $model, string $trait)
	{
		$this->addToModelConfig($model, 'traits', $trait);
	}
	
	public function getModelTraits(string $model): array
	{
		return $this->getModelConfig($model)['traits'];
	}
	
	public function addModelImport(string $model, string $import)
	{
		$this->addToModelConfig($model, 'imports', $import);
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
	
	public function isModelTIDEnabled(string $model): bool
	{
		return $this->getModelConfig($model)['TIDEnabled'];
	}
	
	public function getModelTIDColumnName(string $model): string
	{
		return $this->getModelConfig($model)['TIDColumName'];
	}
	
	public function isModelLogEnabled(string $model): bool
	{
		return $this->getModelConfig($model)['log'];
	}
	
	public function getModelDataMethodsClass(string $model): string
	{
		return $this->getModelConfig($model)['dataMethodsClass'];
	}
	
	public function setModelDataMethodsClass(string $model, string $extender)
	{
		$this->setModelConfig($model, 'dataMethodsClass', $extender);
	}
	
	public function addModelDataMethodsTrait(string $model, string $trait)
	{
		$this->addToModelConfig($model, 'dataMethodsClassTraits', $trait);
	}
	
	public function getModelDataMethodsTraits(string $model): array
	{
		return $this->getModelConfig($model)['dataMethodsClassTraits'];
	}
	
	public function getModelColumnClass(string $model): string
	{
		return $this->getModelConfig($model)['columnClass'];
	}
	//endregion
	
	//region node options
	private function getModelNodeConfig(string $model): array
	{
		return $this->getModelConfig($model)['node'];
	}
	
	public function getModelMakeNode(string $model): bool
	{
		return $this->getModelConfig($model)['makeNode'];
	}
	
	public function getModelNodeClassName(string $model)
	{
		return Variable::assign(['model' => $model], $this->getModelNodeConfig($model)['className']);
	}
	
	public function getModelNodeExtender(string $model): string
	{
		return $this->getModelNodeConfig($model)['extender'];
	}
	
	public function getModelNodeTraits(string $model): array
	{
		return $this->getModelNodeConfig($model)['traits'];
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