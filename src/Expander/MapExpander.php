<?php

namespace Yeast\Loafpan\Expander;

use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Loafpan\Visitor;


class MapExpander implements UnitExpander {
    private function __construct(
      private Loafpan $loafpan,
    ) {
    }

    /**
     * @param  Loafpan  $loafpan
     *
     * @return self
     */
    public static function create(Loafpan $loafpan): UnitExpander {
        return new self($loafpan);
    }

    public function validate(Visitor $visitor, array $generic = [], array $path = []): bool {
        if (!$visitor->isObject()) {
            return false;
        }

        $keys =  $visitor->keys();
        foreach ($keys as $key) {
            if ( ! $this->loafpan->validateVisitor($generic[0], $visitor->enterObject($key))) {
                return false;
            }
        }

        return true;
    }

    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed {
        $map = [];

        $keys =  $visitor->keys();
        foreach ($keys as $key) {
            $map[$key] = $this->loafpan->expandVisitor($generic[0], $visitor->enterObject($key));
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