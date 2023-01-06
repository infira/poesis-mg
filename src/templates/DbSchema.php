<?php

namespace Infira\pmg\templates;


use Infira\pmg\helper\ModelColumn;
use Infira\pmg\helper\Options;

class DbSchema extends ClassTemplate
{
    private array $columnStructure = [];


    public function __construct(Options $opt)
    {
        parent::__construct($opt, $opt->getNamespace());
        $this->setClass($this->namespace->addClass('DbSchema'));
        $this->class->setExtends('\Infira\Poesis\DbSchema');
        $this->class->addComment('@generated');
    }

    public function finalise(): void
    {
        $this->class->addProperty('structure', $this->columnStructure)->setProtected();
        parent::finalise();
    }

    public function setColumn(ModelColumn $column): void
    {
        $table = $column->getTable();
        $columnName = $column->getName();
        $this->columnStructure[$table][$columnName]['type'] = $column->getType();
        $this->columnStructure[$table][$columnName]['signed'] = $column->isSigned();
        $this->columnStructure[$table][$columnName]['length'] = $column->getLength();
        $this->columnStructure[$table][$columnName]['default'] = $column->getDefault();
        $this->columnStructure[$table][$columnName]['allowedValues'] = array_map(static fn($value) => str_replace("'", '', $value), $column->getAllowedValues());
        $this->columnStructure[$table][$columnName]['isNull'] = $column->isNullAllowed();
        $this->columnStructure[$table][$columnName]['isAI'] = $column->isAutoIncrement();
    }

    protected function constructFileName(): string
    {
        return $this->class->getName().'.'.$this->opt->getShortcutTraitFileNameExtension();
    }
}