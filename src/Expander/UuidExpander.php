<?php

namespace Yeast\Loafpan\Expander;

use Ramsey\Uuid\Uuid;
use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Loafpan\Visitor;


class UuidExpander implements UnitExpander {
    public static function create(Loafpan $loafpan): UnitExpander {
        return new self();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(Visitor $visitor, array $generic = [], array $path = []): bool {
        return $visitor->isString() && Uuid::isValid($visitor->getValue());
    }

    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed {
        return Uuid::fromString($visitor->getValue());
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return ['type' => "string", "format" => 'uuid'];
    }
}