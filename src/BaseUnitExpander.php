<?php

namespace Yeast\Loafpan;

interface BaseUnitExpander
{
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
     * Create the JSON Schema for this unit
     *
     * @param  JsonSchemaBuilder  $builder
     * @param  array  $generic
     * @param  string  $definitionName
     *
     * @return array
     */
    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array;
}