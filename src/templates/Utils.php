<?php

namespace Infira\pmg\templates;

use Nette\PhpGenerator\Literal;

class Utils extends \Infira\console\helper\Utils
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
		elseif (is_string($value) and strpos($value, 'Poesis::NONE') !== false or is_integer($value) or is_float($value)) {
			$valueFormat = '%s';
		}
		elseif (is_object($value) and $value instanceof Literal) {
			$valueFormat = '%s';
			$value       = $value->__toString();
		}
		
		return [$valueFormat, $value];
	}
	
	public static function literal(string $format, ...$values): Literal
	{
		return new Literal(sprintf($format, $values));
	}
}