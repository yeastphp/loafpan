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
    /**
     * @param  string  $cacheDirectory  The directory where Loafpan should write it's code generated php files
     * @param  bool  $autoGenerate  Generate expander code on demand
     * @param  bool  $autoUpdate  Check if a class was updated between now and when the last cache item was written
     * @param  bool  $ignoreCache  Ignore any cache available and generate all on demand
     * @param  bool  $useDefaultExpanders  Include default expanders (these can be found in Loafpan::getDefaultExpanders)
     */
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

    public function getCacheDirectory(): string {
        return $this->cacheDirectory;
    }

    /**
     * Register a new expand to this Loafpan instance, this can be helpful when the code gen doesn't offer enough flexibility
     *
     * @template T
     *
     * @param  class-string<T>  $className
     * @param  UnitExpander<T>  $expander
     *
     * @return void
     */
    public function registerExpander(string $className, UnitExpander $expander) {
        $this->registeredExpanders[$className] = $expander;
    }

    /**
     * @param  Loafpan  $loafpan
     *
     * @return array<string, UnitExpander>
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

    /** @var array<class-string, class-string> */
    private static array $knownCustomExpanders = [];

    /** @var array<class-string, UnitExpander> */
    private array $cachedExpanders = [];

    /** @var array<string, UnitExpander> */
    private array $registeredExpanders = [];

    /**
     * Expand given intput into given class, this function is the same as `expand` but generic parameters are passed as an array instead of part of the class name, this can be useful for static analysis tools or just code style,
     * considering
     * ```php
     * $loafpan->expandInto($input, MyClass::class, [MyTypeVar::class])
     * ```
     *
     * might look better than
     *
     * ```php
     * $loafpan->expand(MyClass::class . '<' . MyTypeVar::class . '>', $input)
     * ```
     *
     * @param  mixed  $input  The user input
     * @param  class-string<T>  $className  Class name to expand into (without type variables)
     * @param  array  $generics  The type variables for the class you want to expand into
     *
     * @return T
     * @see Loafpan::expand()
     *
     * @template T
     *
     */
    public function expandInto(mixed $input, string $className, array $generics = []): mixed {
        $fullName = $className;
        if (count($generics) > 0) {
            $fullName .= '<' . implode(",", $generics) . '>';
        }

        return $this->expandWith($fullName, $className, $generics, $input, []);
    }

    /**
     * Expand user input into a unit by class name, throws on failure
     *
     * @template T
     *
     * @param  class-string<T>  $className  The name of the class to expand into, with type variables e.g. `MyClass<int>`
     * @param  mixed  $input  The user input to expand on
     * @param  array  $typeVariables  The type vars from the calling class, e.g. one could ask for `MyClass<T>` and then pass the generic parameters of `["T" => "int"]` and this function will then return `MyClass<int>`
     * @param  array  $path  The path that has been taken to expand this user input, only useful inside expanders
     *
     * @return T
     */
    public function expand(string $className, mixed $input, array $typeVariables = [], array $path = []): mixed {
        [$className, $generics, $expanded] = $this->parseClassName($className, $typeVariables);

        return $this->expandWith($expanded, $className, $generics, $input, $path);
    }

    private function expandWith(string $fullName, string $className, array $generics, mixed $input, array $path): mixed {
        if ($className === 'int' || $className === 'string' || $className === 'bool' || $className === 'float' || $className === 'array' || $className === 'null' || $className === 'mixed') {
            if ( ! $this->validate($className, $input)) {
                throw new RuntimeException("Couldn't expand input of type " . gettype($input) . " into " . $className);
            }

            return $input;
        }

        if (in_array($fullName, $path)) {
            return false;
        }

        $expander = $this->getExpander($className);
        $path[]   = $fullName;

        return $expander->expand($input, $generics, $path);
    }

    /**
     * Check if given input can be expanded into given unit
     *
     * @param  string  $className  The name of the class to validate expansion into, with type variables e.g. `MyClass<int>`
     * @param  mixed  $input  The user input to validate
     * @param  array  $typeVariables  The type vars from the calling class, e.g. one could ask for `MyClass<T>` and then pass the generic parameters of `["T" => "int"]` and this function will then return `MyClass<int>`
     * @param  array  $path  The path that has been taken to validate this user input, only useful inside expanders
     *
     * @return bool
     * @throws ReflectionException
     */
    public function validate(string $className, mixed $input, array $typeVariables = [], array $path = []): bool {
        [$className, $generics, $expanded] = $this->parseClassName($className, $typeVariables);

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
     * Get the expander for a unit by class name
     *
     * @template T
     *
     * @param  class-string<T>  $className  The class name of the unit without type vars
     * @param  bool  $generateOnMissing  If a generator should be generated if there is none generated yet
     *
     * @return ?UnitExpander<T>
     * @throws ReflectionException
     */
    public function getExpander(string $className, bool $generateOnMissing = true): ?UnitExpander {
        return $this->registeredExpanders[$className] ?? ($this->cachedExpanders[$className] ?? ($generateOnMissing ? $this->createExpander($className) : null));
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
        $expanderClass = self::$knownCustomExpanders[$className] ?? null;
        if ($expanderClass === null) {
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

            $v = include_once $expanderFile;

            if (is_string($v)) {
                self::$knownCustomExpanders[$className] = $v;
                $expanderClass                            = $v;
            } else {
                $expanderClass = 'Yeast\\Loafpan\\Generated\\' . $name;
            }
        }

        return $this->cachedExpanders[$className] = ($expanderClass . '::create')($this);
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

    public function parseClassName(string $className, array $typeVariables = []): array {
        $item = strpos($className, '<');

        if ($item === false) {
            if (isset($typeVariables[$className])) {
                return $this->parseClassName($typeVariables[$className]);
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

                if (isset($typeVariables[$name])) {
                    $workingClassName = substr($workingClassName, 0, $lastArgument) . $typeVariables[$name] . substr($workingClassName, $i);
                    $i                += (strlen($typeVariables[$name]) - strlen($item));
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

    /**
     * Generate a json schema for given unit
     *
     * @param  string  $className  The unit to generate a json schema for, including type variables e.g. `MyClass<int>`
     *
     * @return array
     */
    public function jsonSchema(string $className): array {
        return (new JsonSchemaBuilder($this))->withRoot($className);
    }

    /**
     * Check if there's support to validate and expand to given class name via this Loafpan instance
     *
     * @param  string  $className  The name of the class you want to expand
     * @param  array  $typeVars  the type variables that exist within this class name, e.g. when checking for support of `MyClass<T>` one needs to pass `['T']` here to make sure it will not return false because T is not supported
     *
     * @return bool
     * @throws ReflectionException
     */
    public function hasSupport(string $className, array $typeVars = []) {
        $replacements = [];

        foreach ($typeVars as $var) {
            $replacements[$var] = 'null';
        }

        [$className, $generics] = $this->parseClassName($className, $replacements);

        if ($className === 'int' || $className === 'string' || $className === 'bool' || $className === 'float' || $className === 'array' || $className === 'null' || $className === 'mixed') {
            return true;
        }

        if ($this->getExpander($className, false) === null) {
            if ( ! class_exists($className)) {
                return false;
            }

            $c = new ReflectionClass($className);

            if (count($c->getAttributes(Unit::class)) !== 1) {
                return false;
            }
        }

        foreach ($generics as $typeClass) {
            if ( ! $this->hasSupport($typeClass)) {
                return false;
            }
        }

        return true;
    }
}