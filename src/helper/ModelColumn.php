<?php

namespace Infira\pmg\helper;


use Illuminate\Support\Str;
use Infira\pmg\templates\Utils;
use Nette\PhpGenerator\Literal;
use Wolo\Regex;

class ModelColumn
{
    public function __construct(private array $dbColumn, private string $table) {}

    public function isEnumOrSet(): bool
    {
        return preg_match('/(enum|set)\((.+?)\)/m', $this->dbColumn['Type']);
    }

    public function getType(): string
    {
        $type = Str::lower(preg_replace('/\(.*\)/m', '', $this->dbColumn['Type']));

        return strtolower(trim(str_replace("unsigned", "", $type)));
    }

    public function isDecimals(): bool
    {
        return in_array($this->getType(), ["decimal", "float", "real", "double"], true);
    }

    public function isInteger(): bool
    {
        return str_contains($this->getType(), "int");
    }

    public function isNullAllowed(): bool
    {
        return $this->dbColumn['Null'] === 'YES';
    }

    public function isAutoIncrement(): bool
    {
        return $this->dbColumn['Extra'] === 'auto_increment';
    }

    public function isSigned(): bool
    {
        return str_contains(strtolower($this->dbColumn['Type']), 'unsigned');
    }

    public function isDateTimeLike(): bool
    {
        return in_array($this->getType(), ['timestamp', 'date', 'datetime'], true);
    }

    public function getLength(): array|int|Literal|null
    {
        $length = null;
        if (!$this->isEnumOrSet() && str_contains($this->dbColumn['Type'], "(")) {
            $length = str_replace(['(', ',', ')'], ['', '.', ''], Regex::match('/\((.*)\)/m', $this->dbColumn['Type']));
            if ($this->isDecimals()) {
                $ex = explode(".", $length);
                $length = Utils::literal('[\'d\'=>'.$ex[0].',\'p\'=>'.$ex[1].',\'fd\'=>'.($ex[0] - $ex[1]).']');
            }
        }
        if (is_numeric($length) || $this->isDateTimeLike()) {
            return (int)$length;
        }

        return $length;
    }

    public function getDefault(): ?string
    {
        if ($this->isAutoIncrement()) {
            return '';
        }

//        if ($this->isInteger() || $this->isDecimals()) {
//            return ($this->dbColumn['Default'] === null) ? '__poesis_none__' : addslashes($this->dbColumn['Default']);
//        }

        if ($this->dbColumn['Default'] === null && $this->isNullAllowed()) {
            return null;
        }

        if ($this->dbColumn['Default'] === null) {
            return '__poesis_none__';
        }

        if ($this->dbColumn['Default'] === "''") {
            return '';
        }

        return addslashes($this->dbColumn['Default']);
    }

    public function getAllowedValues(): array
    {
        if (preg_match('/(enum|set)\((.+?)\)/m', $this->dbColumn['Type'])) {
            preg_match_all('/[\'"](.+?)[\'"]/m', $this->dbColumn['Type'], $numMatches);

            return $numMatches[1];
        }

        return [];
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getName(): string
    {
        return $this->dbColumn['Field'];
    }

    /**
     * @return array|string[]
     */
    public function getDocsTypes(): array
    {
        $type = $this->getType();
        $types = [];
        if (in_array($type, ['varchar', 'char', 'tinytext', 'mediumtext', 'text', 'longtext', 'enum', 'serial', 'datetime', 'date', '', '', '', '', '', ''], true)) {
            $types[] = 'string';
        }
        elseif (in_array($type, ['smallint', 'tinyint', 'mediumint', 'int', 'bigint', 'year'])) {
            $types[] = 'integer';
        }
        elseif (in_array($type, ['float', 'decimal', 'double', 'real'])) {
            $types[] = 'float';
        }
        elseif ($type === 'set') {
            $types[] = 'array';
            $types[] = 'string';
        }
        elseif ($type === 'timestamp') {
            $types[] = 'integer';
            $types[] = 'string';
        }
        else {
            $types = ['mixed'];
        }
        if ($this->isNullAllowed()) {
            $types[] = 'null';
        }
        $types[] = 'Field';

        return $types;
    }

    public function getDocsDescription(): string
    {
        $comment = $this->dbColumn['Type'];
        if ($this->isNullAllowed()) {
            $comment .= '|null';
        }
        if (isset($this->dbColumn["Comment"]) && $this->dbColumn["Comment"] !== '') {
            $comment .= ' - '.$this->dbColumn["Comment"];
        }

        return $comment;
    }
}