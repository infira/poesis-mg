<?php

namespace Infira\pmg\templates;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Literal;

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

    public static function className(string $name): string
    {
        return self::fixNumericName(ucfirst(Str::camel(self::fixName($name))));
    }

    public static function varName(string $name): string
    {
        return self::fixNumericName(self::fixName($name));
    }

    public static function methodName(string $name): string
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

        $name = preg_replace('![_]+!u', '_', $name);
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

    public static function extractName(string $namespace): string
    {
        $ex = explode('\\', str_replace('/', '\\', $namespace));

        return end($ex);
    }

    public static function toPhpType(string $type): string
    {
        $convertTypes = ['integer' => 'int', 'number' => 'float', 'boolean' => 'bool'];

        return $convertTypes[$type] ?? $type;
    }

    public static function makePhpTypes(string $typeStr, bool $extractClassName, bool $callable = false): array
    {
        $typeStr = trim($typeStr);
        $types   = [];
        if ($typeStr[0] === "?") {
            $types[] = 'null';
            $typeStr = substr($typeStr, 1);
        }

        if ($typeStr[0] === '\\') {
            $types[] = 'array';
            $types[] = '\stdClass';
            if ($callable) {
                $types[] = 'callable';
            }
            $types[] = $extractClassName ? self::extractName($typeStr) : $typeStr;
        }
        else {
            if ($callable) {
                $types[] = 'callable';
            }
            $types[] = self::toPhpType($typeStr);
        }

        return $types;
    }

    public static function isClassLike(string $str): bool
    {
        return (bool)preg_match('/\w+\\\\/m', $str);
    }

    public static function extractClass(string $str): string
    {
        if ($str[0] === "?") {
            $str = substr($str, 1);
        }

        return sprintf('%s::class', self::extractName($str));
    }
}