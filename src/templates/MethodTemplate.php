<?php

namespace Infira\pmg\templates;

use Nette\PhpGenerator\Method;

/**
 * @mixin Method
 */
class MethodTemplate extends Magics
{
	/**
	 * @var \Nette\PhpGenerator\Method
	 */
	protected $method;
	private   $lines           = [];
	private   $eqLineSetMaxLen = 0;
	
	public function __construct(Method $method)
	{
		$this->method = &$method;
		$this->setMagicVar('method');
	}
	
	public function addEqBodyLine(string $set, $value, $valueFormat = null)
	{
		$this->eqLineSetMaxLen = max($this->eqLineSetMaxLen, strlen($set));
		if (!$valueFormat) {
			$parsed      = Utils::parseValueFormat($value, $valueFormat);
			$value       = $parsed[1];
			$valueFormat = $parsed[0];
		}
		$this->lines[] = ['line' => [$set, sprintf($valueFormat, $value)], 'type' => 'eq'];
	}
	
	public function addBodyLine(string $line, string ...$sprintfValues)
	{
		$line          = $sprintfValues ? vsprintf($line, $sprintfValues) : $line;
		$this->lines[] = ['line' => $line, 'type' => 'normal'];
	}
	
	public function construct(): Method
	{
		foreach ($this->lines as $line) {
			$lineStr = $line['line'];
			if ($line['type'] == 'eq') {
				$set     = $lineStr[0];
				$value   = $lineStr[1];
				$eq      = str_repeat(' ', $this->eqLineSetMaxLen - strlen($set)) . ' = ';
				$lineStr = $set . $eq . $value;
			}
			$this->addBody($lineStr . ';');
		}
		
		return $this->method;
	}
}