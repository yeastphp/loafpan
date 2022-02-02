<?php

namespace Yeast\Loafpan\Expander;

use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;


class MapExpander implements UnitExpander {
    private function __construct(
      private Loafpan $loafpan,
    ) {
    }

    public static function create(Loafpan $loafpan): static {
        return new MapExpander($loafpan);
    }

    public function validate(mixed $input, array $generic = [], array $path = []): bool {
        if ( ! is_array($input)) {
            return false;
        }

        foreach ($input as $item) {
            if ( ! $this->loafpan->validate($generic[0], $item)) {
                return false;
            }
        }

        return true;
    }

    public function expand(mixed $input, array $generic = [], array $path = []): mixed {
        $map = [];

        foreach ($input as $key => $value) {
            $map[$key] = $this->loafpan->expand($generic[0], $value);
        }

        return $map;
    }

    public function getGenerics(): array {
        return [];
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return [
          "type"                 => "object",
          "additionalProperties" => $builder->getReference($generic[0]),
        ];
    }
}