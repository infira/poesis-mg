<?php

namespace Infira\pmg\templates;

use Infira\pmg\helper\Options;

class ModelShortcut extends ClassTemplate
{
    public function __construct(Options $opt)
    {
        parent::__construct($opt, $opt->getNamespace());
        $this->setClass($this->namespace->addTrait($this->opt->getShortcutName()));
    }

    public function finalise(): void
    {
        $this->class->addComment('This class provides quick shortcuts to database table model classes');
        parent::finalise();
    }

    public function addModel(string $modelClass): void
    {
        $model = Utils::extractName($modelClass);
        $shortcutMethod = $this->createMethod(Utils::className($model));
        $shortcutMethod->setStatic(true);
        $shortcutMethod->addParameter('options')->setType('array')->setDefaultValue([]);
        $shortcutMethod->setReturnType($modelClass);
        $shortcutMethod->addBodyLine('return new '.$model.'($options)');
        $shortcutMethod->addComment('Method to return '.$model.' class');
        $shortcutMethod->addComment('@param array $options = []');
        $shortcutMethod->addComment('@return '.$model);
    }

    protected function constructFileName(): string
    {
        return $this->opt->getShortcutName().'.'.$this->opt->getShortcutTraitFileNameExtension();
    }

}