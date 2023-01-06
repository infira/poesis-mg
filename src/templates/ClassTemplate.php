<?php

namespace Infira\pmg\templates;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Infira\console\Console;
use Infira\pmg\helper\Options;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use Wolo\File\File;
use Wolo\File\Path;

/**
 * @mixin ClassType
 */
class ClassTemplate
{
    protected ClassType $class;
    /**
     * @var MethodGateway[]
     */
    protected array $methods = [];
    /**
     * @PhpNamespace
     */
    protected PhpNamespace $namespace;
    private bool $generated = false;

    public function __construct(protected Options $opt, PhpNamespace|string $namespace = '')
    {
        if (is_string($namespace)) {
            $this->namespace = new PhpNamespace($namespace);
        }
        else {
            $this->namespace = $namespace;
        }
    }

    public function setAsGenerated(): static
    {
        $this->generated = true;

        return $this;
    }

    protected function setClass(ClassType $class): void
    {
        $this->class = $class;
    }

    public function __call($name, $arguments)
    {
        return $this->class->$name(...$arguments);
    }

    public function finalise(): void
    {
        $this->class->setMethods(
            array_map(
                static function (MethodGateway $methodGateway) {
                    return $methodGateway->getMethod();
                },
                $this->methods
            )
        );
        if ($this->generated) {
            $this->class->addComment('')->addComment('@generated');
        }
    }

    public function &createMethod(string $name, bool $replace = false): MethodGateway
    {
        if (!$replace && $this->hasMethod($name)) {
            throw new \RuntimeException("method('$name') already exists");
        }
        $this->methods[$name] = new MethodGateway($name);
        $this->methods[$name]->setPublic();
        $this->methods[$name]->setAsGenerated();

        return $this->methods[$name];
    }

    public function &getMethod(string $name): MethodGateway
    {
        return $this->methods[$name];
    }

    public function addMethod(string $name): MethodGateway
    {
        return new MethodGateway($this->class->addMethod($name));
    }

    public function hasMethod(string $name): bool
    {
        return isset($this->methods[$name]);
    }

    public function isMethodGenerated(string $name): bool
    {
        return $this->getMethod($name)->isGenerated();
    }

    /**
     * @return MethodGateway[]
     */
    public function getMethods(): array
    {
        return array_map(static fn(Method $m) => new MethodGateway($m), $this->class->getMethods());
    }

    public function addImports(array $imports): void
    {
        array_walk($imports, [$this, 'import']);
    }

    public function import(string $name, ?string $alias = null): void
    {
        $this->namespace->addUse($name, $alias);
    }

    public function getSource(): string
    {
        return (string)$this->class;
    }

    protected function constructFileName(): string
    {
        return Utils::className($this->class->getName()).'.php';
    }

    protected function getFile(): string
    {
        return $this->opt->getDestinationPath($this->constructFileName());
    }

    public function save(string $printer = Printer::class): string
    {
        $this->finalise();
        $file = $this->getFile();


        $printer = new $printer();
        //File::remove($file);
        $phpContent = Str::of('<?php')
            ->append(PHP_EOL)
            ->append(PHP_EOL)
            ->append($printer->printNamespace($this->namespace));

        File::put($file, $phpContent);

        return basename($file);
    }

    public function getName(): string
    {
        return $this->class->getName();
    }

//    public function addComment(string $comment): static
//    {
//        $this->comments->add($comment);
//
//        return $this;
//    }
}