<?php

namespace Infira\pmg\templates;

class ModelShortcutTemplate extends ClassTemplate
{
	public function __construct(string $name, ?string $namespace = '')
	{
		parent::__construct('trait', $name, $namespace);
		$this->class->addComment('This class provides quick shortcuts to database table model classes');
	}
	
	public function beforeGetCode() { }
}