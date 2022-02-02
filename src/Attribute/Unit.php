<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute]
class Unit {
    /**
     * @param  string  $description  The description of this unit, used in the JSON Schema
     * @param  array  $generics  The generic variables available for this unit
     * @param  string|null  $expander  A custom expander for this unit
     */
    public function __construct(
      public string $description = "",
      public array $generics = [],
      public ?string $expander = null
    ) {
    }
}