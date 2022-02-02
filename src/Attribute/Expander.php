<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD)]
class Expander {
    /**
     * @param  string  $description  The description of this expander, this is used in the JSON Schema
     * @param  string|null  $type The type this expander accepts, useful for e.g. specifying generic parameters, can be a union type e.g. `"null|int"`
     */
    public function __construct(public string $description = "", public ?string $type = null) {
    }
}