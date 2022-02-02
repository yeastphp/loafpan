<?php

namespace Yeast\Loafpan\Attribute;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD)]
class Expander {
    public function __construct(public string $description = "", public ?string $type = null) {
    }
}