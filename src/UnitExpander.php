<?php

namespace Yeast\Loafpan;

/**
 * @template T
 */
interface UnitExpander {
    /**
     * @param  Loafpan  $loafpan
     *
     * @return self
     */
    public static function create(Loafpan $loafpan): static;

    public function getGenerics(): array;

    public function validate(mixed $input, array $generic = [], array $path = []): bool;

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array;

    /**
     * @return T
     */
    public function expand(mixed $input, array $generic = [], array $path = []): mixed;
}