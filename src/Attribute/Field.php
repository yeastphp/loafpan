<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class Field {
    public function __construct(
      public string $description = "",
      public ?string $type = null,
      public ?string $name = null,
    ) {
    }
}