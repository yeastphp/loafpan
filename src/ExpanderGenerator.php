<?php

namespace Yeast\Loafpan;

use Brick\VarExporter\VarExporter;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;
use Yeast\Loafpan\Internal\Setter;


class ExpanderGenerator {
    private ReflectionClass $reflection;
    private string $expanderClassName;
    private ?Unit $unit = null;
    /** @var ReflectionMethod[] */
    private array $expanders = [];
    private bool $canUseConstructor = false;
    private bool $constructorIsUniqueExpander = false;
    /** @var Setter[] */
    private array $setters = [];
    private array $errors = [];
    private array $constructorInfo = [];
    private array $requiredProperties = [];
    private array $expanderInfo = [];

    public function getErrors(): array {
        return $this->errors;
    }

    public static function getModificationTime(): int {
        return filemtime(__FILE__);
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @throws ReflectionException
     */
    public function __construct(private string $className, private Loafpan $loafpan) {
        $this->reflection        = new ReflectionClass($this->className);
        $this->expanderClassName = static::createGeneratedClassName($this->className, $this->loafpan->getCasing());
    }

    public function collectUnit(): void {
        $attributes = $this->reflection->getAttributes(Unit::class);

        if (count($attributes) !== 1) {
            throw new RuntimeException("To generate an expander for " . $this->className . " it needs the attribute `\\Yeast\\Loafpan\\Attribute\\Unit`");
        }

        $attribute = $attributes[0];
        /** @var Unit $unit */
        $unit       = $attribute->newInstance();
        $this->unit = $unit;
    }

    /**
     * @throws ReflectionException
     */
    public function collectExpanders(): void {
        foreach ($this->reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Expander::class);

            if (count($attributes) > 0) {
                $types   = [];
                $reasons = $this->checkIfAssignFunctionValid($method, $types);

                if ( ! $method->isPublic()) {
                    $reasons[] = 'it is not public';
                }

                if ( ! $method->isStatic()) {
                    $reasons[] = 'it is not static';
                }

                $returnType = $method->getReturnType();
                if ($returnType !== null && ( ! ($returnType instanceof ReflectionNamedType) || ($returnType->getName() !== 'mixed' && $returnType->getName() !== 'static' && $returnType->getName() !== $this->className))) {
                    $reasons[] = "it's return type is incorrect ($returnType) should be either `static` or `" . $this->className . '`';
                }

                if (count($reasons) > 0) {
                    $this->errors[] = 'The method ' . $method->getName() . " can't be used as expander because " . $this->joinEnglish($reasons);
                    continue;
                }

                $attrib                                 = $attributes[0]->newInstance();
                $this->expanderInfo[$method->getName()] = [$attrib, $types];

                $this->expanders[] = $method;
            }
        }

        $constructor = $this->reflection->getConstructor();

        // Use the default public constructor
        if ($constructor === null) {
            $this->canUseConstructor = true;
        }

        if ($constructor !== null && $constructor->isPublic()) {
            $constructorReasons      = [];
            $this->canUseConstructor = true;
            $parameters              = $constructor->getParameters();

            foreach ($parameters as $parameter) {
                $attrs = $parameter->getAttributes(Field::class);

                if (count($attrs) === 0 && $parameter->isOptional()) {
                    continue;
                }

                if (count($attrs) === 0) {
                    $this->canUseConstructor = false;
                    break;
                }

                /** @var Field $attr */
                $attr      = ($attrs[0])->newInstance();
                $fieldName = $attr->name ?? $this->loafpan->getFieldName($parameter->getName(), $this->unit);

                $this->constructorIsUniqueExpander = true;

                if ( ! $parameter->isOptional()) {
                    $this->requiredProperties[] = $fieldName;
                }

                $types = $this->getParameterTypes($parameter);

                $reasons = [];

                $anyOf = [];

                foreach ($types as $childType) {
                    if ( ! $this->loafpan->hasSupport($childType, $this->unit->generics) && ! in_array($childType, $this->unit->generics, true)) {
                        $reasons[] = 'there is no expander known for ' . $childType;
                    }

                    $anyOf[] = $childType;
                }

                $this->constructorInfo[$fieldName] = [$anyOf, $attr->description, $attr->name ?: $fieldName];

                if (count($reasons) > 0) {
                    $constructorReasons[] = "can't use parameter " . $parameter->getName() . ' because ' . $this->joinEnglish($reasons);
                }
            }

            if (count($constructorReasons) > 0) {
                $this->canUseConstructor = false;
                $this->errors[]          = "can't use constructor as expander because " . $this->joinEnglish($constructorReasons);
            }
        }
    }

    public function collectSetters(): void {
        $this->collectPropertySetters();
        $this->collectMethodSetters();
    }

    public function collectPropertySetters(): void {
        $properties = $this->reflection->getProperties();

        foreach ($properties as $property) {
            $attrs = $property->getAttributes(Field::class);

            if (empty($attrs)) {
                continue;
            }

            $attr = $attrs[0];
            /** @var Field $field */
            $field = $attr->newInstance();

            if ($property->isPromoted()) {
                if ( ! $this->canUseConstructor && ! $property->hasDefaultValue()) {
                    $this->errors[] = "Can't set promoted field " . $property->getName() . ' because the constructor is not usable for expansion and has no default value';
                    continue;
                }

                if ($this->canUseConstructor) {
                    continue;
                }
            }

            $reasons = [];
            if ( ! $property->isPublic()) {
                $reasons[] = 'is not public';
            }

            if (PHP_VERSION_ID >= 80100 && $property->isReadOnly()) {
                $reasons[] = 'is read-only';
            }

            if (count($reasons) > 0) {
                $this->errors[] = "Can't set field " . $property->getName() . 'because ' . implode(" and ", $reasons);
                continue;
            }

            $types           = $this->getFieldTypes($property->getType(), $field->type);
            $this->setters[] = new Setter(Setter::PROPERTY, $property->getName(), $field, $types);
        }
    }

    private function checkIfAssignFunctionValid(ReflectionMethod $method, array &$types = []): array {
        $reasons    = [];
        $parameters = $method->getParameters();

        if (count($parameters) === 0 || count($parameters) > 2) {
            return ['function should have a signature of ($visitor) or ($visitor, Loafpan $loafpan)'];
        }

        $types = $this->getExpanderTypes($parameters[0]);

        foreach ($types as $type) {
            if ( ! $this->loafpan->hasSupport($type, $this->unit->generics) && ! in_array($type, $this->unit->generics, true)) {
                $reasons[] = 'there is no expander known for ' . $type;
            }
        }

        if (count($parameters) === 2) {
            $type = $parameters[1]->getType();

            if ( ! $type instanceof ReflectionNamedType || ($type->getName() !== 'mixed' && $type->getName() !== Loafpan::class)) {
                $reasons[] = 'the second argument should be of type ' . Loafpan::class . ' but ' . ($type) . ' found';
            }
        }

        return $reasons;
    }

    private function collectMethodSetters(): void {
        $methods = $this->reflection->getMethods();

        foreach ($methods as $method) {
            $attrs = $method->getAttributes(Field::class);

            if (empty($attrs)) {
                continue;
            }

            $attr = $attrs[0];
            /** @var Field $field */
            $field = $attr->newInstance();

            $reasons = [];

            if ( ! $method->isPublic()) {
                $reasons[] = 'is not public';
            }

            $types   = [];
            $reasons = array_merge($reasons, $this->checkIfAssignFunctionValid($method, $types));

            if (count($reasons) > 0) {
                $this->errors[] = 'The method ' . $method->getName() . " can't be used as setter because " . $this->joinEnglish($reasons);
                continue;
            }

            $this->setters[] = new Setter(Setter::METHOD, $method->getName(), $field, $types);
        }
    }

    public function joinEnglish(array $items): string {
        if (count($items) === 0) {
            return "";
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last  = array_pop($items);
        $parts = [];
        if (count($items) > 0) {
            $parts[] = implode(", ", $items);
        }

        $parts[] = $last;

        return implode(" and ", $parts);
    }

    /**
     * @throws ReflectionException
     */
    public function collectInfo(): void {
        $this->collectUnit();
        $this->collectExpanders();
        $this->collectSetters();
    }

    public static function createGeneratedClassName(string $className, ?string $casing = null): string {
        $crc = substr(dechex(crc32($className . '.' . ($casing ?? 'default'))) . '0000', 0, 4);

        return 'UnitExpander_' . $crc . '_' . str_replace('\\', '_', $className);
    }

    public function generateUnitExpander(): string {
        if ($this->unit->expander !== null) {
            return "<?php\n// $this->className uses a custom expander\nreturn \\{$this->unit->expander}::class;\n";
        }

        $file = new PhpFile();

        $namespace = $file->addNamespace("Yeast\\Loafpan\\Generated");
        $namespace->addUse(UnitExpander::class);
        $namespace->addUse(Loafpan::class);
        $namespace->addUse(JsonSchemaBuilder::class);
        $namespace->addUse(Visitor::class);


        $classType = $namespace->addClass($this->expanderClassName);
        $classType->setFinal();
        $classType->addComment('@implements UnitExpander<\\' . $this->className . '>');
        $classType->addImplement(UnitExpander::class);

        $classType->addMethod('__construct')
                  ->addPromotedParameter('loafpan')
                  ->setType(Loafpan::class);

        $create = $classType->addMethod("create");
        $create->addParameter("loafpan")->setType(Loafpan::class);
        $create->setReturnType(UnitExpander::class);
        $create->setStatic();
        $create->addBody('return new static($loafpan);');

        $methodSelection = $classType->addMethod('selectExpansionMethod');
        $methodSelection->addParameter('visitor')->setType(Visitor::class);
        $methodSelection->addParameter("generics")->setType('array');
        $methodSelection->addParameter('path')->setType('array');
        $methodSelection->setReturnType('?array');
        $methodSelection->setBody($this->generateMethodSelectionCode($classType));

        $validate = $classType->addMethod('validate');
        $validate->addParameter('visitor')->setType(Visitor::class);
        $validate->addParameter("generics")->setType('array')->setDefaultValue([]);
        $validate->addParameter('path')->setType('array')->setDefaultValue([]);
        $validate->setReturnType('bool');

        $validate->setBody('return $this->selectExpansionMethod($visitor, $generics, $path) !== null;');

        $expand = $classType->addMethod('expand');
        $expand->addParameter('visitor')->setType(Visitor::class);
        $expand->addParameter("generics")->setType('array')->setDefaultValue([]);
        $expand->addParameter('path')->setType('array')->setDefaultValue([]);
        $expand->setReturnType('mixed');
        $expand->addComment('@return \\' . $this->className);

        $expand->setBody($this->generateExpansionCode());

        $getGenerics = $classType->addMethod('getGenerics');
        $getGenerics->setReturnType('array');
        $getGenerics->setBody('return ' . $this->prettyExport($this->unit->generics) . ';');

        $buildSchema = $classType->addMethod('buildSchema');
        $buildSchema->addParameter('builder')->setType(JsonSchemaBuilder::class);
        $buildSchema->addParameter('generics')->setType('array');
        $buildSchema->addParameter('definitionName')->setType('string');
        $buildSchema->setReturnType('array');
        $buildSchema->setBody($this->generateBuildSchema());

        $printer = new PsrPrinter();

        return $printer->printFile($file);
    }

    private function generateExpansionCode(): string {
        $code = [
          '$match = $this->selectExpansionMethod($visitor, $generics, $path);' . "\n\$expanded = null;",
          "if (\$match === null) {\n     throw new \\RuntimeException(\"Can't expand {$this->className} based on given input\");\n}",
        ];

        if ($this->canUseConstructor) {
            $code[] = "if (\$match[0] === '__construct') {\n" . $this->indentBlock($this->generateConstructorExpansionCode()) . "\n}";
        }

        foreach ($this->expanders as $expander) {
            $phpName = var_export($expander->name, true);
            $code[]  = "if (\$match[0] === $phpName) {\n" . $this->indentBlock($this->generateExpanderMethodExpansionCode($expander)) . "\n}";
        }

        $code[] = "return \$expanded;";

        return implode("\n\n", $code);
    }

    /**
     * @throws ReflectionException
     */
    private function generateConstructorExpansionCode(): string {
        $constructor = $this->reflection->getConstructor();

        $parameterList = [];

        $code = [];

        if ($this->constructorIsUniqueExpander) {
            foreach ($constructor->getParameters() as $parameter) {
                $attrs = $parameter->getAttributes(Field::class);

                if (count($attrs) === 0) {
                    continue;
                }

                /** @var Field $field */
                $field = $attrs[0]->newInstance();

                $defaultValue = null;

                if ($parameter->isOptional()) {
                    if ($parameter->isDefaultValueConstant()) {
                        $defaultValue = $parameter->getDefaultValueConstantName();
                    } else {
                        $defaultValue = $this->prettyExport($parameter->getDefaultValue());
                    }
                }

                $parameterVariable = $this->generateFieldExpansionCode($this->getParameterTypes($parameter), $parameter->name, $field, null, $parameter->isOptional(), $defaultValue, $preBlock);

                if ($preBlock !== null) {
                    $code[] = $preBlock;
                }

                $parameterList[] = $parameter->name . ': ' . $parameterVariable;
            }
        }

        $code[] = '$expanded = new \\' . $this->className . '(' . implode(", ", $parameterList) . ');';

        foreach ($this->setters as $setter) {
            if ($setter->type === Setter::PROPERTY) {
                $assignTo = fn(string $valGen) => '$expanded->' . $setter->inputName . ' = ' . ($valGen);
            } else {
                $assignTo = fn(string $valGen) => '$expanded->' . $setter->inputName . '(' . $valGen . ')';
            }

            $this->generateFieldExpansionCode($setter->types, $setter->inputName, $setter->field, $assignTo, true, null, $preBlock);
            $code[] = $preBlock;
        }

        return implode("\n\n", $code);
    }

    private function prettyExport(mixed $value, int $level = 1): string {
        return VarExporter::export($value, VarExporter::INLINE_NUMERIC_SCALAR_ARRAY | VarExporter::NO_SET_STATE | VarExporter::NO_CLOSURES | VarExporter::NOT_ANY_OBJECT | VarExporter::TRAILING_COMMA_IN_ARRAY, $level);
    }

    private function getParameterTypes(ReflectionParameter $parameter): array {
        $fields = $parameter->getAttributes(Field::class);

        $typeOverride = null;
        if (count($fields) === 1) {
            $typeOverride = $fields[0]->newInstance()->type;
        }

        return $this->getFieldTypes($parameter->getType(), $typeOverride);
    }

    private function getExpanderTypes(ReflectionParameter $parameter): array {
        $func      = $parameter->getDeclaringFunction();
        $expanders = $func->getAttributes(Expander::class);

        $typeOverride = null;

        if (count($expanders) === 1) {
            $typeOverride = $expanders[0]->newInstance()->type;
        }

        return $this->getFieldTypes($parameter->getType(), $typeOverride);
    }

    private function hasComplex(array $types): bool {
        foreach ($types as $type) {
            if ( ! $this->isBuiltin($type)) {
                return true;
            }
        }

        return false;
    }

    private function hasSimple(array $types): bool {
        foreach ($types as $type) {
            if ($this->isBuiltin($type)) {
                return true;
            }
        }

        return false;
    }

    private function generateExpanderMethodExpansionCode(ReflectionMethod $expander): string {
        $parameter = $expander->getParameters()[0];

        $valueName = '$visitor';
        $code      = "";

        $types = $this->getExpanderTypes($parameter);
        if ($this->hasComplex($types)) {
            $valueName = '$visitorValue';

            if ($this->hasSimple($types)) {
                $code = "if (\$match[1] !== false) {\n    \$visitorValue = \$this->loafpan->expandVisitor(\$match[1], \$visitor, " . $this->getGenericArray() . ", \$path);\n} else {\n    \$visitorValue = \$visitor;\n}\n\n";
            } else {
                $code = '$visitorValue = $this->loafpan->expandVisitor($match[1], $visitor, ' . $this->getGenericArray() . ', $path);' . "\n";
            }
        } else {
            $valueName .= '->getValue()';
        }

        $simple = '$expanded = \\' . $this->className . '::' . $expander->getName() . '(' . $valueName . ($expander->getNumberOfParameters() > 1 ? ', $this->loafpan' : '') . ');';

        $code .= $simple;

        return $code;
    }

    private function indentBlock(string $data, int $level = 1): string {
        $lines = explode("\n", $data);

        foreach ($lines as &$line) {
            if (trim($line) === "") {
                $line = "";
            }

            $line = str_repeat('    ', $level) . $line;
        }

        return implode("\n", $lines);
    }

    private function generateMethodSelectionCode(ClassType $classType): string {
        $code = [];

        foreach ($this->expanders as $expander) {
            $code[] = $this->generateExpanderMethodMatcherCode($expander);
        }

        if ($this->canUseConstructor && $this->constructorIsUniqueExpander) {
            $code[] = $this->generateConstructorMethodMatcherCode($classType);
        }

        if (count($this->setters) > 0 && ($this->canUseConstructor && ! $this->constructorIsUniqueExpander)) {
            $block = "if (\$visitor->isObject() && (\$match = " . $this->generateSetterMethodMatcherCode($this->setters, $classType) . ") !== null) {\n";
            $block .= "    return ['__construct', \$match];\n";
            $block .= "}";

            $code[] = $block;
        }

        $code[] = 'return null;';

        return implode("\n\n", $code);
    }

    private function isBuiltin(string $type): bool {
        return $type === 'int' || $type === 'null' || $type === 'bool' || $type === 'float' || $type === 'string' || $type === 'array' || $type === 'mixed';
    }

    private function getFieldTypes(?ReflectionType $type, ?string $typeOverride = null): array {
        if ($typeOverride !== null) {
            return explode('|', $typeOverride);
        }

        if ($type instanceof ReflectionNamedType) {
            $types = [$type->getName()];

            if ($type->allowsNull()) {
                $types[] = 'null';
            }

            return $types;
        }

        if ($type instanceof ReflectionUnionType) {
            $types = [];

            foreach ($type->getTypes() as $childType) {
                $types[] = $childType->getName();
            }

            return $types;
        }

        if ($type === null) {
            return ['mixed'];
        }

        throw new RuntimeException("can't resolve type, most likely a conjunction type is used which isn't supported");
    }

    private function generateConstructorMethodMatcherCode(ClassType $classType): string {
        $method = $classType->addMethod('matchConstructorExpander')
                            ->setPrivate();

        $method->addParameter('visitor')
               ->setType(Visitor::class);

        $method->addParameter("generics")->setType('array');

        $checks = [];

        if (count($this->setters) > 0) {
            $block    = "\$result = " . $this->generateSetterMethodMatcherCode($this->setters, $classType) . ";\n";
            $block    .= "if (\$result === null) {\n";
            $block    .= "    return null;\n";
            $block    .= "}";
            $checks[] = $block;
        } else {
            $checks[] = '$result = [];';
        }

        $parameters = $this->reflection->getConstructor()->getParameters();

        foreach ($parameters as $parameter) {
            $attrs = $parameter->getAttributes(Field::class);

            if (count($attrs) === 0) {
                continue;
            }

            /** @var Field $field */
            $field = $attrs[0]->newInstance();
            $types = $this->getFieldTypes($parameter->getType(), $field->type);

            $checks[] = $this->getFieldMatcher($types, $parameter->getName(), $parameter->isOptional(), $field);
        }

        $checks[] = 'return $result;';

        $method->setBody(implode("\n\n", $checks));
        $method->setReturnType("?array");

        return "/**\n * Matcher for the constructor expander\n */\nif (\$visitor->isObject() && (\$match = \$this->matchConstructorExpander(\$visitor, \$generics)) !== null) {\n    return ['__construct', \$match];\n}";
    }

    private function getCheckForType(string $type, string $varName, ?string &$expanded = null): string {
        if ($this->isBuiltin($type)) {
            $expanded = null;

            return match ($type) {
                'mixed' => 'true',
                'string' => "{$varName}->isString()",
                'null' => "{$varName}->isNull()",
                'int' => "{$varName}->isInteger()",
                'float' => "{$varName}->isFloat()",
                'array' => "{$varName}->isArray()",
                'bool' => "{$varName}->isBool()",
                default => throw new RuntimeException("$type is recognized as builtin type, yet there is no support"),
            };
        } else {
            $expanded = $type;

            return '$this->loafpan->validateVisitor(' . var_export($type, true) . ", $varName, " . $this->getGenericArray() . ", \$path)";
        }
    }

    private function generateExpanderMethodMatcherCode(ReflectionMethod $expander): string {
        $first = $expander->getParameters()[0];

        /** @var Expander $expanderAttr */
        $expanderAttr = $expander->getAttributes(Expander::class)[0]->newInstance();

        $parameterType = $first->getType();
        $types         = $this->getFieldTypes($parameterType, $expanderAttr->type);

        $primitiveChecks = [];
        $expandedChecks  = [];

        foreach ($types as $type) {
            $expanded = null;
            $check    = $this->getCheckForType($type, '$visitor', $expanded);

            if ($expanded !== null) {
                $expandedChecks[$expanded] = $check;
            } else {
                $primitiveChecks[] = $check;
            }
        }

        $code = [];

        if (count($primitiveChecks) > 0) {
            $code[] = 'if (' . implode(" || ", $primitiveChecks) . ") {\n    return [" . var_export($expander->name, true) . ", false];\n}";
        }

        foreach ($expandedChecks as $class => $check) {
            $code[] = "if ($check) {\n    return [" . var_export($expander->name, true) . ", " . var_export($class, true) . "];\n}";
        }

        return "/**\n * Matchers for the function $this->className::$expander->name\n */\n" . implode("\n\n", $code);
    }

    private function getGenericArray(): string {
        $entries = [];

        $i = 0;
        foreach ($this->unit->generics as $typeVar) {
            $entries[] = var_export($typeVar, true) . ' => $generics[' . $i . ']';
            $i++;
        }

        return '[' . implode(", ", $entries) . ']';
    }

    private function generateBuildSchema(): string {
        $options = [];
        if (($this->constructorIsUniqueExpander || count($this->setters) > 0) && $this->canUseConstructor) {
            $option = "[\n";
            $option .= "    'type' => 'object',\n";
            $option .= "    'properties' => [\n";

            $props = [];

            foreach ($this->constructorInfo as [$types, $description, $name]) {
                $props[$name] = [$types, $description];
            }

            foreach ($this->setters as $setter) {
                $props[$setter->field->name ?: $this->loafpan->getFieldName($setter->inputName, $this->unit)] = [$setter->types, $setter->field->description];
            }

            foreach ($props as $key => [$types, $description]) {
                $primitives = [];
                $refs       = [];

                [$refs, $primitives, $primitivesDef] = $this->extractPrimitives($types, $refs, $primitives);

                $option .= "        " . var_export($key, true) . ' => ';
                if (count($refs) === 0) {
                    if ($description !== "") {
                        $primitivesDef = array_merge(["description" => $description], $primitivesDef);
                    }

                    $option .= $this->prettyExport($primitivesDef, 2) . ",\n";
                } elseif (count($primitives) === 0 && count($refs) === 1) {
                    $ref = '$builder->getReference(' . var_export($refs[0], true) . ", " . $this->getGenericArray() . ")";

                    if ($description !== "") {
                        $ref = "array_merge(\n            ['description' => " . var_export($description, true) . "],\n            " . $ref . "\n        )";
                    }

                    $option .= $ref . ",\n";
                } else {
                    $option .= "[\n";

                    if ($description !== "") {
                        $option .= "            'description' => " . var_export($description, true) . ",\n";
                    }

                    $option .= "            'anyOf' => [\n";

                    if (count($primitives) > 0) {
                        $option .= '                ' . $this->prettyExport($primitivesDef, 4) . ",\n";
                    }

                    foreach ($refs as $ref) {
                        $option .= '                $builder->getReference(' . var_export($ref, true) . ", " . $this->getGenericArray() . "),\n";
                    }

                    $option .= "            ],\n";
                    $option .= "        ],\n";
                }
            }

            $option .= "    ],\n";

            if (count($this->requiredProperties) > 0) {
                $option .= "    'required' => " . $this->prettyExport($this->requiredProperties) . ",\n";
            }

            $option .= ']';

            $options[] = $option;
        }

        if (count($this->expanderInfo) === 0) {
            return 'return ' . (count($options) ? $options[0] : '[]') . ";";
        }

        foreach ($this->expanderInfo as [$attrib, $types]) {
            $options[] = $this->generateExpanderJsonSchemaCode($types, $attrib);
        }

        if (count($options) === 1) {
            if ($this->unit->description !== "") {
                return 'return array_merge(' . $this->prettyExport(['description' => $this->unit->description], 0) . ', ' . $options[0] . ');';
            }


            return 'return ' . $options[0] . ";";
        }

        $code = "return [\n";

        if ($this->unit->description !== "") {
            $code .= "    'description' => " . var_export($this->unit->description, true) . ",\n";
        }

        $code .= "    'oneOf' => [\n";
        foreach ($options as $option) {
            $code .= $this->indentBlock($option, 2) . ",\n";
        }
        $code .= "    ]\n";
        $code .= "];";

        return $code;
    }

    public function generateExpanderJsonSchemaCode(array $types, Expander $expander): string {
        $options    = [];
        $primitives = [];
        $refs       = [];

        [$refs, $primitives, $primitivesDef] = $this->extractPrimitives($types, $refs, $primitives);

        if (count($primitives) > 0) {
            if (count($refs) === 0) {
                if ($expander->description !== "") {
                    $primitivesDef = array_merge(["description" => $expander->description], $primitivesDef);
                }

                return $this->prettyExport($primitivesDef, 0);
            }

            $options[] = $this->prettyExport($primitivesDef, 0);
        }

        foreach ($refs as $ref) {
            $options[] = '$builder->getReference(' . var_export($ref, true) . ", " . $this->getGenericArray() . ")";
        }

        if (count($options) === 1) {
            if ($expander->description !== "") {
                return 'array_merge(' . $this->prettyExport(['description' => $expander->description], 0) . ', ' . $options[0] . ')';
            }

            return $options[0];
        }

        $code = "[\n";

        if ($expander->description !== "") {
            $code .= "    'description' => " . var_export($expander->description, true) . ",\n";
        }

        $code .= "    'oneOf' => [\n";
        foreach ($options as $option) {
            $code .= $this->indentBlock($option, 2) . ",\n";
        }
        $code .= "    ]\n";
        $code .= "]";

        return $code;
    }

    /**
     * @param  Setter[]  $setters
     * @param  ClassType  $class
     *
     * @return string
     */
    private function generateSetterMethodMatcherCode(array $setters, ClassType $class): string {
        $matchSetters = $class->addMethod('matchSetters')
                              ->setPrivate()
                              ->setReturnType('?array');

        $matchSetters->addParameter('visitor')->setType(Visitor::class);
        $matchSetters->addParameter('generics')->setType('array');


        $code   = [];
        $code[] = '$result = [];';

        foreach ($setters as $setter) {
            $code[] = $this->getFieldMatcher($setter->types, $setter->inputName, true, $setter->field);
        }

        $code[] = 'return $result;';

        $matchSetters->setBody(implode("\n\n", $code));

        return '$this->matchSetters($visitor, $generics)';
    }

    private function extractPrimitives(array $types, array $refs, array $primitives): array {
        foreach ($types as $type) {
            $prim = JsonSchemaBuilder::getPrimitive($type);
            if ($prim === null) {
                $refs[] = $type;
                continue;
            }

            if (is_string($prim)) {
                $prim = [$prim];
            }

            $primitives = array_merge($primitives, $prim);
        }

        $primitivesDef = ['type' => count($primitives) === 1 ? $primitives[0] : $primitives];

        return [$refs, $primitives, $primitivesDef];
    }

    private function getFieldMatcher(array $types, string $fieldName, bool $optional, Field $field): string {
        $phpFieldName = var_export($field->name ?: $this->loafpan->getFieldName($fieldName, $this->unit), true);

        $simpleChecks = [];
        $unitChecks   = [];

        foreach ($types as $type) {
            $expanded = null;
            $check    = $this->getCheckForType($type, "\$visitor->enterObject($phpFieldName)", $expanded);

            if ($expanded === null) {
                $simpleChecks[] = $check;
            } else {
                $unitChecks[] = $expanded;
            }
        }

        $parameterCode = [];

        if (count($simpleChecks) > 0) {
            if (count($simpleChecks) === 1) {
                $simpleCheck = $simpleChecks[0];
            } else {
                $simpleCheck = '(' . implode(" || ", $simpleChecks) . ')';
            }

            $if    = 'if ($visitor->hasKey(' . $phpFieldName . ') && ';
            $ifEnd = ')';
            if ($optional) {
                if (count($simpleChecks) > 1) {
                    $if    = 'if';
                    $ifEnd = '';
                } else {
                    $if = 'if (';
                }
            }

            $parameterCode[] = $if . $simpleCheck . "$ifEnd {\n    \$result[$phpFieldName] = false;\n}";
        }

        if (count($unitChecks) > 1) {
            $items = [];
            foreach ($unitChecks as $check) {
                $items[] = var_export($check, true);
            }

            $unitCheck = 'foreach ([' . implode(", ", $items) . "] as \$unitName) {\n";
            $unitCheck .= '    if ($this->loafpan->validateVisitor($unitName, $visitor->enterObject(' . $phpFieldName . "), " . $this->getGenericArray() . ")) {\n";
            $unitCheck .= '        $result[' . $phpFieldName . "] = \$unitName;\n";
            $unitCheck .= "        break;\n";
            $unitCheck .= '    }' . "\n";
            $unitCheck .= "}";

            if ( ! $optional) {
                $unitCheck = "if (\$visitor->hasKey($phpFieldName)) {\n" . $this->indentBlock($unitCheck) . "\n}";
            }

            $parameterCode[] = $unitCheck;
        }

        if (count($unitChecks) === 1) {
            $checkPrefix = $optional ? '' : '$visitor->hasKey(' . $phpFieldName . ') && ';
            $unitCheck   = 'if (' . $checkPrefix . '$this->loafpan->validateVisitor(' . var_export($unitChecks[0], true) . ', $visitor->enterObject(' . $phpFieldName . "), " . $this->getGenericArray() . ")) {\n";
            $unitCheck   .= '    $result[' . $phpFieldName . "] = " . var_export($unitChecks[0], true) . ";\n";
            $unitCheck   .= '}';

            $parameterCode[] = $unitCheck;
        }

        $parameterCode[] = "if (!isset(\$result[" . $phpFieldName . "])) {\n    return null;\n}";

        $check = implode("\n\n", $parameterCode);;

        if ($optional) {
            $check = "if (\$visitor->hasKey($phpFieldName)) {\n" . $this->indentBlock($check) . "\n}";
        }

        return $check;
    }

    private function generateFieldExpansionCode(array $types, string $name, Field $field, ?callable $assign = null, bool $optional = false, ?string $defaultValue = null, ?string &$preBlock = null): string {
        $phpFieldName      = var_export($field->name ?: $this->loafpan->getFieldName($name, $this->unit), true);
        $parameterVariable = '$visitor->enterObject(' . $phpFieldName . ')->getValue()';

        $complex = $this->hasComplex($types);
        $simple  = $this->hasSimple($types);
        if (($optional && $assign === null) || $complex) {
            $parameterVariable = '$field' . ucfirst($name);
        }

        $preBlock = null;

        $parameterCode = "";

        if ($complex) {
            $valueGen = '$this->loafpan->expandVisitor($match[1][' . $phpFieldName . '], $visitor->enterObject(' . $phpFieldName . '), ' . $this->getGenericArray() . ')';

            if ($assign === null) {
                $complexCode = $parameterVariable . ' = ' . $valueGen;
            } else {
                $complexCode = ($assign)($valueGen);
            }

            $complexCode .= ';';

            if ($simple) {
                $valueGen      = '$visitor->enterObject(' . $phpFieldName . ')->getValue()';
                $assignment    = $assign ? (($assign)($valueGen)) : ($parameterVariable . ' = ' . $valueGen);
                $parameterCode = 'if ($match[1][' . $phpFieldName . "] !== false) {\n    " . $complexCode . "\n} else {\n    " . $assignment . " ;\n}";
            } else {
                $parameterCode .= $complexCode;
            }
        }

        if ($optional) {
            if ( ! $complex) {
                if ($assign === null) {
                    $parameterCode = $parameterVariable . ' = $visitor->enterObject(' . $phpFieldName . ')->getValue();' . "\n";
                } else {
                    $parameterCode .= ($assign)($parameterVariable) . ';';
                }
            }

            $parameterCode = 'if (isset($match[1][' . $phpFieldName . '])) {' . "\n" . $this->indentBlock(trim($parameterCode)) . "\n}";

            if ($defaultValue !== null) {
                $assignment    = $assign ? (($assign)($defaultValue)) : ($parameterVariable . ' = ' . $defaultValue);
                $parameterCode .= " else {\n    " . $assignment . ";\n}";
            }
        }

        if ($parameterCode !== "") {
            $preBlock = $parameterCode;
        }

        return $parameterVariable;
    }
}