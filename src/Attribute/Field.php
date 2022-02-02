<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class Field {
    /**
     * @param  string  $description  The description of this field, used in JSON Schema
     * @param  string|null  $type  The type of this field, used to override the PHP type, useful for e.g. generics or to specify difference between a list and map, can be a union type e.g. `"null|int"`
     * @param  string|null  $name  The actual name of this field in the input array
     */
    public function __construct(
      public string $description = "",
      public ?string $type = null,
      public ?string $name = null,
    ) {
    }
}