<?php

namespace Yeast\Loafpan\Expander;

use Ramsey\Uuid\Uuid;
use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;


class UuidExpander implements UnitExpander {
    public static function create(Loafpan $loafpan): UnitExpander {
        return new self();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(mixed $input, array $generic = [], array $path = []): bool {
        return is_string($input) && Uuid::isValid($input);
    }

    public function expand(mixed $input, array $generic = [], array $path = []): mixed {
        return Uuid::fromString($input);
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return ['type' => "string", "format" => 'uuid'];
    }
}