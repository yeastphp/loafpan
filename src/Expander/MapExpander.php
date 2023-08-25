<?php

namespace Yeast\Loafpan\Expander;

use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Loafpan\UnitExpanderV2;
use Yeast\Loafpan\Visitor;


class MapExpander implements UnitExpander, UnitExpanderV2
{
    private function __construct(
      private readonly Loafpan $loafpan,
    ) {
    }

    /**
     * @param  Loafpan  $loafpan
     *
     * @return self
     */
    public static function create(Loafpan $loafpan): UnitExpander
    {
        return new self($loafpan);
    }

    public function validate(Visitor $visitor, array $generic = [], array $path = []): bool
    {
        if ( ! $visitor->isObject()) {
            return false;
        }

        $keyType   = isset($generic[1]) ? $generic[0] : 'mixed';
        $valueType = $generic[1] ?? $generic[0] ?? 'mixed';


        $keys = $visitor->keys();
        foreach ($keys as $key) {
            if ( ! $this->loafpan->validate($keyType, $key)) {
                return false;
            }

            if ( ! $this->loafpan->validateVisitor($valueType, $visitor->enterObject($key))) {
                return false;
            }
        }

        return true;
    }

    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed
    {
        $map = [];

        $keyType   = isset($generic[1]) ? $generic[0] : 'mixed';
        $valueType = $generic[1] ?? $generic[0] ?? 'mixed';


        $keys = $visitor->keys();
        foreach ($keys as $key) {
            $key = $this->loafpan->expand($keyType, $key);

            $map[$key] = $this->loafpan->expandVisitor($valueType, $visitor->enterObject($key));
        }

        return $map;
    }

    public function getGenerics(): array
    {
        return [];
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array
    {
        return [
          "type"                 => "object",
          "additionalProperties" => $builder->getReference($generic[0]),
        ];
    }

    public function expandAndValidate(Visitor $visitor, array $generic = [], array $path = []): array
    {
        if ( ! $visitor->isObject()) {
            return false;
        }

        $keyType   = isset($generic[1]) ? $generic[0] : 'mixed';
        $valueType = $generic[1] ?? $generic[0] ?? 'mixed';

        $keys = $visitor->keys();
        foreach ($keys as $key) {
            $key = $this->loafpan->expand($keyType, $key);

            $map[$key] = $this->loafpan->expandVisitor($valueType, $visitor->enterObject($key));
        }

        return $map;
    }
}