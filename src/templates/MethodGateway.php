<?php

namespace Infira\pmg\templates;

use Illuminate\Support\Str;
use Infira\pmg\templates\Traits\CommentsManager;
use Nette\PhpGenerator\Method;

/**
 * @mixin Method
 */
class MethodGateway
{
    private Method $method;
    use CommentsManager;

    public function __construct(Method|string $method)
    {
        if (is_string($method)) {
            $method = new Method($method);
        }
        $this->method = $method;
        $this->setComment($this->method->getComment());
    }

    public function __call($name, $arguments)
    {
        return $this->method->$name(...$arguments);
    }

    public function addBodyLine(string $line, string ...$sprintfValues): void
    {
        $line = $sprintfValues ? vsprintf($line, $sprintfValues) : $line;
        $this->method->addBody(Str::finish($line, ';'));
    }

    public function getMethod(): Method
    {
        $method = clone $this->method;
        $method->setComment($this->getComment());

        return $method;
    }

    public function getSource(): string
    {
        return (string)$this->method;
    }
}