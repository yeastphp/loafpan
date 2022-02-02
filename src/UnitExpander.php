<?php

namespace Yeast\Loafpan;

/**
 * @template T
 */
interface UnitExpander {
    /**
     * Create a new instance of this expander with given Loafpan instance
     *
     * @param  Loafpan  $loafpan
     *
     * @return self
     */
    public static function create(Loafpan $loafpan): UnitExpander;

    /**
     * Get a list of all available generic type vars, e.g in MyClass<T, A> this would return ['T', 'A']
     *
     * @return array
     */
    public function getGenerics(): array;

    /**
     * Validate that given input can be used to expand to a unit
     *
     * @param  mixed  $input  The user input, can be anything
     * @param  string[]  $generic  The generic type parameters used for this unit (positional)
     * @param  string[]  $path  The path Loafpan has taken so far to validate this input, this is used for JIT dead loop prevention. and should thus be passed to Loafpan again if you're passing the same input through Loafpan again
     *
     * @return bool
     */
    public function validate(mixed $input, array $generic = [], array $path = []): bool;

    /**
     * Create the JSON Schema for this unit
     *
     * @param  JsonSchemaBuilder  $builder
     * @param  array  $generic
     * @param  string  $definitionName
     *
     * @return array
     */
    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array;

    /**
     * Expand the input into a unit with given generic parameters, will throw on failure
     *
     * @param  mixed  $input  The user input, can be anything
     * @param  string[]  $generic  The generic type parameters used for this unit (positional)
     * @param  string[]  $path  The path Loafpan has taken so far to expand this input, this is used for JIT dead loop prevention. and should thus be passed to Loafpan again if you're passing the same input through Loafpan again
     *
     * @return T
     */
    public function expand(mixed $input, array $generic = [], array $path = []): mixed;
}