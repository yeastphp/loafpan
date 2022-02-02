<?php

namespace Yeast\Loafpan;

class JsonSchemaBuilder {
    public function __construct(
      private Loafpan $loafpan,
    ) {
    }

    private array $definitions = [];

    public function addDefinition(string $definitionName, array $def) {
        $this->definitions[$definitionName] = $def;
    }

    public static function getPrimitive(string $name): mixed {
        if ($name === 'int') {
            return 'integer';
        }

        if ($name === 'float') {
            return 'float';
        }

        if ($name === 'string') {
            return 'string';
        }

        if ($name === 'null') {
            return 'null';
        }

        if ($name === 'array') {
            return ['object', 'array'];
        }

        if ($name === 'bool') {
            return 'bool';
        }

        return null;
    }

    public function getReference(string $className, array $replacements = []): array {
        [$baseName, $generics, $expanded] = $this->loafpan->parseClassName($className, $replacements);
        $data = static::getPrimitive($baseName);

        if ($data !== null) {
            return ['type' => $data];
        }

        $expanded = str_replace('\\', '.', $expanded);

        $ref = ['$ref' => "#/definitions/" . $expanded];

        if (isset($this->definitions[$expanded])) {
            return $ref;
        }

        $this->definitions[$expanded] = false;
        $expander                     = $this->loafpan->getExpander($baseName);
        $this->definitions[$expanded] = $expander->buildSchema($this, $generics, $expanded);

        return $ref;
    }

    public function withRoot(string $className): array {
        $root                = [];
        $root['$schema']     = "http://json-schema.org/draft/2020-12/schema";
        $ref = $this->getReference($className);
        $root['definitions'] = $this->definitions;

        $root += $ref;

        return $root;
    }
}