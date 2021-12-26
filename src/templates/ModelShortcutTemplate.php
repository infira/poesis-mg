<?php

namespace Infira\pmg\templates;

class ModelShortcutTemplate extends ClassTemplate
{
	public function beforeFinalize()
	{
		$this->class->addComment('This class provides quick shortcuts to database table model classes');
	}
	
	public function addModel(string $model)
	{
		$shortcutMethod = $this->createMethod(Utils::className($model));
		$shortcutMethod->setStatic(true);
		$shortcutMethod->addParameter('options')->setType('array')->setDefaultValue([]);
		$shortcutMethod->setReturnType($model);
		$shortcutMethod->addBodyLine('return new ' . $model . '($options)');
		$shortcutMethod->addComment('Method to return ' . $model . ' class');
		$shortcutMethod->addComment('@param array $options = []');
		$shortcutMethod->addComment('@return ' . $model);
	}
}