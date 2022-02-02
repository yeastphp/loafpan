<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;


#[Attribute]
class Unit {
    /**
     * @param  string  $description  The description of this unit, used in the JSON Schema
     * @param  array  $generics  The generic variables available for this unit
     * @param  string|null  $expander  A custom expander for this unit
     * @param  string|null  $casing  The camel case variant that is expected from input objects
     */
    public function __construct(
      public string $description = "",
      public array $generics = [],
      public ?string $expander = null,
      #[ExpectedValues(['camelCase', 'PascalCase', 'snake_case', 'Ada_Case', 'MACRO_CASE', 'kebab-case', 'Train-Case', 'COBOL-CASE', 'lower case', 'UPPER CASE', 'Title Case', 'Sentence case', 'dot.notation', null])]
      public ?string $casing = null,
    ) {
    }
}