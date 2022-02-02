<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute]
class Unit {
    public function __construct(
      public string $description = "",
      public array $generics = [],
      public ?string $expander = null
    ) {
    }
}