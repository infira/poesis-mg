<?php

namespace Infira\pmg\templates;

abstract class Template
{
	private $mixerVar;
	
	public function __construct(object $mixer, string $varName)
	{
		$this->mixerVar = $varName;
		$this->$varName = $mixer;
	}
	
	public function __call($name, $arguments)
	{
		$var = $this->mixerVar;
		
		return $this->$var->$name(...$arguments);
	}
	
	
	protected function parseValueFormat($value, ?string $valueFormat = "'%s'"): array
	{
		$valueFormat = $valueFormat ?: "'%s'";
		
		if (is_bool($value))
		{
			$valueFormat = '%s';
			$value       = $value ? 'true' : 'false';
		}
		elseif ($value === null)
		{
			$valueFormat = '%s';
			$value       = 'null';
		}
		elseif (is_array($value))
		{
			$valueFormat = "[%s]";
			$value       = join(',', $value);
		}
		elseif (is_string($value) and substr($value, 0, 6) == 'CLEAN=')
		{
			$valueFormat = '%s';
			$value       = substr($value, 6);
		}
		elseif (is_string($value) and strpos($value, 'Poesis::NONE') !== false or is_integer($value) or is_float($value))
		{
			$valueFormat = '%s';
		}
		
		return [$valueFormat, $value];
	}
}