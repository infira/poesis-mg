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
	
	public static function fixClassVarName(string $name): string
	{
		$name = lcfirst(Str::camel(self::slug($name, '_')));
		if (is_numeric($name[0])) {
			$name = "_$name";
		}
		
		return $name;
	}
	
	public static function fixClassName(string $name): string
	{
		return ucfirst(self::fixClassVarName($name));
	}
	
	/**
	 * Same as Str::slug but without converting to lowecase
	 *
	 * @param $title
	 * @param $separator
	 * @param $language
	 * @return string
	 */
	public static function slug($title, $separator = '-', $language = 'en'): string
	{
		$title = $language ? Str::ascii($title, $language) : $title;
		
		// Convert all dashes/underscores into separator
		$flip = $separator === '-' ? '_' : '-';
		
		$title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);
		
		// Replace @ with the word 'at'
		$title = str_replace('@', $separator . 'at' . $separator, $title);
		
		// Remove all characters that are not the separator, letters, numbers, or whitespace.
		$title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', $title);
		
		// Replace all separator characters and whitespace by a single separator
		$title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);
		
		return trim($title, $separator);
	}
	
}