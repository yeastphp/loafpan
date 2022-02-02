<?php

namespace Yeast\Loafpan;

use DateTime;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Yeast\Loafpan\Attribute\Unit;
use Yeast\Loafpan\Expander\DateTimeExpander;
use Yeast\Loafpan\Expander\DateTimeImmutableExpander;
use Yeast\Loafpan\Expander\ListExpander;
use Yeast\Loafpan\Expander\MapExpander;
use Yeast\Loafpan\Expander\UuidExpander;


class Loafpan {
    public function __construct(
      private string $cacheDirectory,
      private bool $autoGenerate = true,
      private bool $autoUpdate = true,
      private bool $ignoreCache = false,
      bool $useDefaultExpanders = true,
    ) {
        if ($useDefaultExpanders) {
            $this->registeredExpanders = static::getDefaultExpanders($this);
        }
    }

    /**
     * @param  Loafpan  $loafpan
     *
     * @return UnitExpander[]
     */
    public static function getDefaultExpanders(Loafpan $loafpan): array {
        return [
          'list'                   => ListExpander::create($loafpan),
          'map'                    => MapExpander::create($loafpan),
          DateTime::class          => DateTimeExpander::create($loafpan),
          DateTimeImmutable::class => DateTimeImmutableExpander::create($loafpan),
          Uuid::class              => UuidExpander::create($loafpan),
          UuidInterface::class     => UuidExpander::create($loafpan),
        ];
    }

    /** @var array<class-string, UnitExpander> */
    private array $cachedExpanders = [];

    /** @var array<string, class-string<UnitExpander>> */
    private array $registeredExpanders = [];

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @return T
     * @throws ReflectionException
     */
    public function expand(string $className, mixed $input, array $genericParameters = [], array $path = []): mixed {
        [$className, $generics, $expanded] = $this->parseClassName($className, $genericParameters);

        if ($className === 'int' || $className === 'string' || $className === 'bool' || $className === 'float' || $className === 'array' || $className === 'null') {
            if ( ! $this->validate($className, $input)) {
                throw new RuntimeException("aa");
            }

            return $input;
        }

        if (in_array($expanded, $path)) {
            return false;
        }

        $expander = $this->getExpander($className);
        $path[]   = $expanded;

        return $expander->expand($input, $generics, $path);
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @throws ReflectionException
     */
    public function validate(string $className, mixed $input, array $replacements = [], array $path = []): bool {
        [$className, $generics, $expanded] = $this->parseClassName($className, $replacements);

        if ($className === 'mixed') {
            return true;
        }

        switch ($className) {
            case 'null':
                return $input === null;
            case 'int':
                return is_int($input);
            case 'string':
                return is_string($input);
            case 'float':
                return is_float($input);
            case 'bool':
                return is_bool($input);
            case 'array':
                // TODO: extra validation?
                return is_array($input);
        }

        if (in_array($expanded, $path)) {
            return false;
        }

        $expander = $this->getExpander($className);
        $path[]   = $expanded;

        return $expander->validate($input, $generics, $path);
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @return UnitExpander<T>
     * @throws ReflectionException
     */
    public function getExpander(string $className): UnitExpander {
        return $this->registeredExpanders[$className] ?? ($this->cachedExpanders[$className] ?? $this->createExpander($className));
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @return UnitExpander<T>
     * @throws ReflectionException
     */
    private function createExpander(string $className): UnitExpander {
        $name = ExpanderGenerator::createGeneratedClassName($className);

        $sourceChangeTime = false;
        if ($this->autoGenerate && $this->autoUpdate) {
            $reflection       = new ReflectionClass($className);
            $classFileName    = $reflection->getFileName();
            $sourceChangeTime = filemtime($classFileName);
        }

        $expanderFile = $this->cacheDirectory . '/' . $name . '.php';
        if ($this->ignoreCache || ! file_exists($expanderFile) || ($this->autoUpdate && filemtime($expanderFile) < $sourceChangeTime)) {
            if ( ! $this->autoGenerate) {
                throw new RuntimeException();
            }

            $source = $this->generateExpander($className);
            file_put_contents($expanderFile, $source);
        }

        include_once $expanderFile;

        return $this->cachedExpanders[$className] = ('Yeast\\Loafpan\\Generated\\' . $name . '::create')($this);
    }

    /**
     * @throws ReflectionException
     */
    private function generateExpander(string $className): string {
        $generator = new ExpanderGenerator($className, $this);
        $generator->collectInfo();

        if (count($generator->getErrors()) > 0) {
            throw new RuntimeException("Failed to create expander class because [" . implode(", ", $generator->getErrors()) . "]");
        }

        return $generator->generateUnitExpander();
    }

    public function parseClassName(string $className, array $replacements = []): array {
        $item = strpos($className, '<');

        if ($item === false) {
            if (isset($replacements[$className])) {
                return $this->parseClassName($replacements[$className]);
            }

            return [$className, [], $className];
        }

        if ( ! str_ends_with($className, '>')) {
            throw new \RuntimeException("$className invalid");
        }

        $baseName = substr($className, 0, $item);
        $generics = [];

        $depth            = 0;
        $lastStart        = $item + 1;
        $lastArgument     = $lastStart;
        $workingClassName = $className;
        for ($i = $item + 1; $i < strlen($workingClassName); $i++) {
            if ($workingClassName[$i] === ',' || $workingClassName[$i] === '>') {
                $item = substr($workingClassName, $lastStart, $i - $lastStart);
                $name = trim($item);

                if (isset($replacements[$name])) {
                    $workingClassName = substr($workingClassName, 0, $lastArgument) . $replacements[$name] . substr($workingClassName, $i);
                    $i                += (strlen($replacements[$name]) - strlen($item));
                }

                $lastArgument = $i + 1;
            }

            if ($workingClassName[$i] === ',' && $depth === 0) {
                $generics[] = substr($workingClassName, $lastStart, $i - $lastStart);
                continue;
            }

            if ($workingClassName[$i] === '<') {
                $depth++;
                $lastArgument = $i + 1;
            }

            if ($workingClassName[$i] === '>') {
                $depth--;
            }
        }

        $generics[] = substr($workingClassName, $lastStart, ($i - $lastStart) - 1);

        return [$baseName, $generics, $workingClassName];
    }

    public function jsonSchema(string $className): array {
        $builder = new JsonSchemaBuilder($this);

        return $builder->withRoot($className);
    }

    public function hasSupport(string $className) {
        [$className] = $this->parseClassName($className);

        if ($className === 'int' || $className === 'string' || $className === 'bool' || $className === 'float' || $className === 'array' || $className === 'null') {
            return true;
        }

        if (isset($this->registeredExpanders[$className]) || isset($this->cachedExpanders[$className])) {
            return true;
        }

        if ( ! class_exists($className)) {
            return false;
        }

        $c = new ReflectionClass($className);

        return count($c->getAttributes(Unit::class)) === 1;
    }
}