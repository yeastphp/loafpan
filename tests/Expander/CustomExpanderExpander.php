<?php

namespace Yeast\Test\Loafpan\Expander;

use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Test\Loafpan\Unit\CustomExpander;


/**
 * @implements UnitExpander<CustomExpander>
 */
class CustomExpanderExpander implements UnitExpander {
    public static function create(Loafpan $loafpan): UnitExpander {
        return new self();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(mixed $input, array $generic = [], array $path = []): bool {
        return true;
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        // Anything goes here
        return [];
    }

    public function expand(mixed $input, array $generic = [], array $path = []): mixed {
        return new CustomExpander();
    }
}