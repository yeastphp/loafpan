<?php

namespace Yeast\Loafpan\Expander;

use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Loafpan\Visitor;


/**
 * An expander for array lists
 *
 * @implements UnitExpander<array>
 */
class ListExpander implements UnitExpander {
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
        if ( ! $visitor->isList()) {
            return false;
        }

        $c = $visitor->length();
        for ($i = 0; $i < $c; $i++) {
            if ( ! $this->loafpan->validateVisitor($generic[0], $visitor->enterArray($i))) {
                return false;
            }
        }

        return true;
    }

    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed {
        $v = [];
        $c = $visitor->length();
        for ($i = 0; $i < $c; $i++) {
            $v[] = $this->loafpan->expandVisitor($generic[0], $visitor->enterArray($i));
        }

        return $v;
    }

    public function getGenerics(): array {
        return [];
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return [
          "type"  => "array",
          "items" => $builder->getReference($generic[0]),
        ];
    }
}