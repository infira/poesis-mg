<?php

namespace Infira\pmg\templates;

use Illuminate\Support\Str;

class Utils
{
	public static function parseValueFormat($value, ?string $valueFormat = "'%s'"): array
	{
		$valueFormat = $valueFormat ?: "'%s'";
		
		if (is_bool($value)) {
			$valueFormat = '%s';
			$value       = $value ? 'true' : 'false';
		}
		elseif ($value === null) {
			$valueFormat = '%s';
			$value       = 'null';
		}
		elseif (is_array($value)) {
			$valueFormat = "[%s]";
			$value       = join(',', $value);
		}
		elseif (is_string($value) and substr($value, 0, 6) == 'CLEAN=') {
			$valueFormat = '%s';
			$value       = substr($value, 6);
		}
		elseif (is_string($value) and strpos($value, 'Poesis::NONE') !== false or is_integer($value) or is_float($value)) {
			$valueFormat = '%s';
		}
		
		return [$valueFormat, $value];
	}
	
	public static function fixClassName(string $name): string
	{
		return self::fixNumericName(ucfirst(self::fixName($name)));
	}
	
	public static function fixVarName(string $name): string
	{
		return self::fixNumericName(self::fixName($name));
	}
	
	public static function fixMethodName(string $name): string
	{
		$name   = self::fixName($name);
		$studly = Str::studly($name);
		if ($name[0] !== $studly[0]) {
			$studly = lcfirst($studly);
		}
		
		return self::fixNumericName($studly);
	}
	
	private static function fixName(string $name): string
	{
		$name = Str::ascii($name, 'en');
		
		$name = preg_replace('![-]+!u', '_', $name);
		// Remove all characters that are not the separator, letters, numbers, or whitespace.
		$name = preg_replace('![^_\pL\pN\s]+!u', '', $name);
		
		// Replace all separator characters and whitespace by a single separator
		$name = preg_replace('![_\s]+!u', '_', $name);
		
		return trim($name, '_');
	}
	
	private static function fixNumericName(string $name): string
	{
		if (is_numeric($name[0])) {
			$name = "_$name";
		}
		
		return $name;
	}
	
}