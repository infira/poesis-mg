<?php

namespace Infira\pmg\templates;

use Nette\PhpGenerator\Method;

/**
 * @mixin Method
 */
class MethodTemplate extends Template
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	protected $method;
	private   $lines           = [];
	private   $eqLineSetMaxLen = 0;
	
	public function __construct(string $name)
	{
		parent::__construct(new Method($name), 'method');
	}
	
	public function addEqBodyLine(string $set, $value, $valueFormat = null)
	{
		$this->eqLineSetMaxLen = max($this->eqLineSetMaxLen, strlen($set));
		if (!$valueFormat)
		{
			$parsed      = $this->parseValueFormat($value, $valueFormat);
			$value       = $parsed[1];
			$valueFormat = $parsed[0];
		}
		$this->doAddBodyLine([$set => sprintf($valueFormat, $value)], 'eq');
	}
	
	public function addBodyLine(string $line)
	{
		$this->doAddBodyLine($line, 'normal');
	}
	
	private function doAddBodyLine($line, string $type)
	{
		$this->lines[] = ['line' => $line, 'type' => $type];
	}
	
	public function construct(): Method
	{
		foreach ($this->lines as $line)
		{
			$lineStr = $line['line'];
			if ($line['type'] == 'eq')
			{
				$set     = array_key_first($lineStr);
				$value   = $lineStr[$set];
				$eq      = str_repeat(' ', $this->eqLineSetMaxLen - strlen($set)) . ' = ';
				$lineStr = $set . $eq . $value;
			}
			$this->addBody($lineStr . ';');
		}
		
		return $this->method;
	}
}