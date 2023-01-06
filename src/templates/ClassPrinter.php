<?php

namespace Infira\pmg\templates;

class ClassPrinter extends \Nette\PhpGenerator\Printer
{
    protected $linesBetweenMethods = 1;
    public $wrapLength = 2000;
}