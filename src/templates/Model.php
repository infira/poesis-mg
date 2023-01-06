<?php

namespace Infira\pmg\templates;


use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Infira\pmg\helper\ModelColumn;
use Infira\pmg\helper\Options;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Wolo\File\File;

class Model extends ClassTemplate
{
    private const MODEL_COLUMN_ALIAS = 'ModelColumn';
    protected string $dataMethodsClass = '';
    protected string $columnClass = '';
    /**
     * @var ModelColumn[]
     */
    private array $columns = [];
    private array $modelProperties = [];
    private bool $modelFileExists;
    private string $columnMethodReferenceType;
    protected Collection $existingComments;

    public function __construct(
        private string $modelName,
        private string $tableName,
        Options $opt
    ) {
        parent::__construct($opt, $opt->getNamespace());
        $this->columnClass = $this->opt->getColumnClass($modelName) ?: '\Infira\Poesis\clause\ModelColumn';
        $this->existingComments = new Collection();
        $savedFile = $this->getFile();
        if ($this->modelFileExists = file_exists($savedFile)) {
            $this->setClassFromExistingFile($savedFile);
        }
        else {
            $this->columnMethodReferenceType = $this->modelName;
            $this->setClass($this->namespace->addClass($this->modelName));
        }
        $this->setAsGenerated();
        $this->namespace->addUse('\Infira\Poesis\clause\Field');
        $this->addSchemaProperty('table', $this->tableName);
        $this->setDataMethodsClass($this->opt->getDataMethodsClass($this->modelName));
    }

    private function setClassFromExistingFile(string $savedFile): void
    {
        $class = clone ClassType::fromCode(File::content($savedFile));
        $this->columnMethodReferenceType = $class->getName();

        $columnReferences = collect();
        $comments = Str::of($class->getComment())->explode(PHP_EOL)->map(static fn($c) => Str::of($c));
        foreach ($comments as $comment) {
            $comment = Str::of($comment);
            if ($comment->is('*'.self::MODEL_COLUMN_ALIAS.'*')) {
                $key = $comment->after('@property '.self::MODEL_COLUMN_ALIAS.' $')->before(' ')->trim();
                $columnReferences->put((string)$key, $comment->after('$'.$key.' ')->trim());
            }
        }
        $rejectPatterns = $columnReferences->keys()
            ->map(fn($key) => \Wolo\Str::wrap($key, '*@method '.$this->modelName.' ', '*'))
            ->merge(
                $columnReferences->keys()->map(fn($key) => \Wolo\Str::wrap($key, '*@property ModelColumn $', '*'))
            );
        $comments = $comments
            ->reject(function (Stringable $comment) use ($rejectPatterns) {
                $comment = $comment->trim();

                return $comment->isEmpty()
                    || $comment->is('*@property '.$this->columnMethodReferenceType.' $Where*')
                    || $comment->is('*@method '.$this->columnMethodReferenceType.' model*')
                    || $comment->is($rejectPatterns->toArray())
                    || $comment->is('*ORM model for*')
                    || $comment->is('@generated*');
            });
        if ($comments->count()) {
            $this->existingComments = $this->existingComments->merge($comments->join(PHP_EOL));
        }
        $class->setComment('');

        $this->methods = array_map(function (Method $m) use (&$class) {
            $class->removeMethod($m->getName());

            return new MethodGateway($m);
        }, $class->getMethods());

//        $mm = $this->opt->getCustomModel($this->className);
//        foreach ($mm->getNamespace()->getUses() as $use) {
//            $this->namespace->addUse($use);
//        }
//        foreach ($mm->getImplements() as $implement) {
//            $this->class->addImplement($implement);
//        }
//        foreach ($mm->getTraits() as $trait) {
//            $this->class->addTrait($trait);
//        }

        $this->setClass($class);
        $this->namespace->add($this->class);
        foreach ($this->class->getNamespace()->getUses() as $use) {
            $this->namespace->addUse($use);
        }
    }

    public function finalise(): void
    {
        $this->makeExtras();
        $this->addSelectMethod();

        $this->import($this->columnClass, self::MODEL_COLUMN_ALIAS);

        if ($this->columnClass !== '\Infira\Poesis\clause\ModelColumn') {
            $this->addSchemaProperty('columnClass', Utils::literal("ModelColumn::class"));
        }

        foreach ($this->modelProperties as $name => $value) {
            $this->addProperty($name, $value)->setProtected();
        }
        $this->addComment('ORM model for '.$this->tableName)->addComment(' ');
        $this->addComment('@property '.$this->modelName.' $Where class where values');
        $this->addComment('@method '.$this->modelName.' model(array $options = [])');
        foreach ($this->columns as $column) {
            $columnName = $column->getName();
            $desc = $column->getDocsDescription();
            $docsTypes = $column->getDocsTypes();
            $docMethodTypes = implode('|', $docsTypes);
            $paramName = Utils::varName($docsTypes[0]);
            $desc = $column->getDocsDescription();

            $this->addComment('@property ModelColumn $'.$columnName.' '.$desc);
            $this->addComment('@method '.$this->columnMethodReferenceType.' '.$columnName.'('.$docMethodTypes.' $'.$paramName.') - '.$desc);
        }
        if ($this->modelFileExists) {
            $this->existingComments->each(fn($str) => $this->addComment((string)$str));
        }
        parent::finalise();
    }

    public function addIndexMethods(array $indexMethods): void
    {
        foreach ($indexMethods as $indexName => $columns) {
            $columnComment = [];
            $methodName = Utils::methodName($indexName).'_index';
            if ($this->hasMethod($methodName) && !$this->methods[$methodName]->isGenerated()) { //cant overwrite user generated method
                continue;
            }
            $method = $this->createMethod($methodName, true);
            $method->addComment('Set value for '.implode(', ', $columnComment).' index');
            $method->addComment('');
            foreach ($columns as $Col) {
                $columnComment[] = $Col->Column_name;
                $method->addParameter($Col->Column_name);

                $column = $this->columns[$Col->Column_name];
                $desc = $column->getDocsDescription();

                $commentTypes = implode('|', $column->getDocsTypes());
                $method->addComment('@param '.$commentTypes.' $'.$Col->Column_name.' - '.$desc);
                $method->addBodyLine('$this->add2Clause($this->value2ModelColumn(\'%s\', $%s));', $Col->Column_name, $Col->Column_name);
            }
            $method->addBodyLine('return $this');
        }
    }

    public function setDataMethodsClass(string $dataMethodsClass): void
    {
        if ($dataMethodsClass !== Options::$defaultDataModelsClass) {
            $this->import($dataMethodsClass);
        }
        $this->dataMethodsClass = $dataMethodsClass;
    }

    public function setModelExtender(string $class): void
    {
        $this->import($class);
        $this->class->setExtends($class);
    }

    public function setColumn(ModelColumn $column): void
    {
        $this->columns[$column->getName()] = $column;
    }

    public function addPrimaryColumn(string $name): void
    {
        $this->modelProperties['primaryColumns'][] = $name;
    }

    public function addSchemaProperty(string $name, $value): void
    {
        $this->modelProperties[$name] = $value;
    }

    private function makeExtras(): void
    {
        if (!$this->opt->getMakeNode($this->modelName)) {
            return;
        }
        $dataMethods = new ClassTemplate($this->opt, $this->namespace);
        $dataMethods->setClass($this->namespace->addClass(Utils::className($this->modelName.'NodeDataMethods')));
        $dataMethods->setExtends($dataMethods->getName());


        $nodeExtender = $this->opt->getNodeExtender($this->modelName);
        $this->namespace->addUse($nodeExtender, 'Node');

        $dataMethods->setTraits($this->opt->getDataMethodsTraits($this->modelName));

        $getNode = $dataMethods->createMethod('getNode');
        $getNode->setReturnType($nodeExtender)->isReturnNullable();
        $getNode->addParameter('constructorArguments')->setType('array')->setDefaultValue([]);
        $getNode->addBodyLine('return $this->getObject(Node::class, $constructorArguments)');

        $getNodes = $dataMethods->createMethod('getNodes');
        $getNodes->setReturnType('array');
        $getNodes->addParameter('constructorArguments')->setType('array')->setDefaultValue([]);
        $getNodes->addBodyLine('return $this->getObjects(Node::class, $constructorArguments)');

        $dataMethods->finalise();
        $this->setDataMethodsClass($dataMethods->getName());
    }

    protected function constructFileName(): string
    {
        return $this->modelName.'.'.$this->opt->getModelFileNameExtension();
    }

    //region helpers
    private function addSelectMethod(): void
    {
        if ($this->dataMethodsClass === Options::$defaultDataModelsClass) {
            return;
        }
        if ($this->hasMethod('select') && !$this->methods['select']->isGenerated()) { //cant overwrite user generated method
            return;
        }
        $select = $this->createMethod('select', true);
        $select->addParameter('columns')
            //->setType('array|string')
            ->setDefaultValue(null);
        $select->addBodyLine('return parent::doSelect($columns, %s)', Utils::extractClass($this->dataMethodsClass));
        $select->addComment('Select data from database');
        $select->addComment('@param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma');
        $select->addComment('@return '.$this->dataMethodsClass);
    }

    //endregion
}