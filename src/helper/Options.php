<?php

namespace Infira\pmg\helper;


use Infira\Utils\Dir;
use Infira\Utils\Regex;
use Infira\Utils\File;
use stdClass;
use Exception;
use Symfony\Component\Yaml\Yaml;
use Infira\Utils\Variable;

class Options
{
	private array $config;
	
	public function __construct(string $yamlPath)
	{
		$this->config = array_merge(Yaml::parseFile(PMG_PATH . "bin/defaults.yaml"), Yaml::parseFile($yamlPath));
	}
	
	private function getPathArr(string $configPath): array
	{
		return explode('.', $configPath);;
	}
	
	public function get(string $configPath)
	{
		if (!$this->exists($configPath))
		{
			throw new Exception("config path $configPath does not exist");
		}
		$to = &$this->config;
		foreach ($this->getPathArr($configPath) as $p)
		{
			$to = &$to[$p];
		}
		
		return $to;
	}
	
	public function exists(string $configPath): bool
	{
		$to = &$this->config;
		foreach ($this->getPathArr($configPath) as $p)
		{
			if (!array_key_exists($p, $to))
			{
				return false;
			}
		}
		
		return true;
	}
	
	private function set(string $configPath, $value)
	{
		$to    = &$this->config;
		$lastP = null;
		foreach ($this->getPathArr($configPath) as $p)
		{
			if (!array_key_exists($p, $to))
			{
				$to[$p] = new stdClass();
			}
			$to    = &$to[$p];
			$lastP = $p;
		}
		$to[$lastP] = $value;
	}
	
	private function add(string $configPath, $value)
	{
		$to    = &$this->config;
		$lastP = null;
		foreach ($this->getPathArr($configPath) as $p)
		{
			if (!property_exists($to, $p))
			{
				$to[$p] = new stdClass();
			}
			$to    = &$to[$p];
			$lastP = $p;
		}
		$to[$lastP][] = $value;
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
		
		return array_merge((array)$this->config['models'][$model], (array)$this->config['model']);
	}
	
	public function isTableVoided(string $table): bool
	{
		return in_array($table, $this->config['voidTables']);
	}
	
	
	//TODO
	public function scanExtensions(string $path)
	{
		if (!is_dir($path))
		{
			throw new Exception("scan model extendor folder must be correct path($path)");
		}
		foreach (Dir::getFileNames($path) as $fn)
		{
			$extension = str_replace('.php', '', $fn);
			if ($model = $this->findFileExtension($path, $fn, 'Model'))
			{
				$this->setModelExtender($model->model, $model->extension);
			}
			elseif (substr($extension, -9) == 'Extension')
			{
				$model = substr($extension, 0, -9);
				$this->addModelTrait($model, $extension);
			}
			elseif ($dm = $this->findFileExtension($path, $fn, 'DataMethods'))
			{
				$this->setModelDataMethodsClass($dm->model, $dm->extension);
			}
			elseif ($dm = $this->findFileExtension($path, $fn, 'Node'))
			{
				$this->setModelNodeExtendor($dm->model, $dm->extension);
			}
		}
	}
	
	private function findFileExtension($path, $file, $type): ?stdClass
	{
		$extension = str_replace('.php', '', $file);
		$len       = strlen($type) * -1;
		if (substr($extension, $len) == $type)
		{
			$model       = substr($extension, 0, $len);
			$fileContent = File::getContent($path . $file);
			//$con = Regex::getMatches('/<?php(.*)?class/ms', $fileContent);
			if (Regex::isMatch('/namespace (.+)?;/m', $fileContent))
			{
				$matches = [];
				preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
				$this->addModelImport($model, '\\' . $matches[1][0] . '\\' . $extension);
			}
			else
			{
				$extension = '\\' . $extension;
			}
			
			return (object)['model' => $model, 'extension' => $extension];
		}
		
		return null;
	}
	
	public function getNamespace(): string
	{
		return $this->config['namespace'];
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
	
	public function getShortcutNamespace(): string
	{
		return $this->config['modelShortcut']['namespace'];
	}
	
	public function getShortcutTraitFileNameExtension(): string
	{
		return $this->config['modelShortcut']['fileExt'];
	}
	//endregion
}