<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Unit;


#[Unit("whoa!")]
class AcceptMultipleUnits {
    private function __construct() {
    }

    #[Expander(type: Topping::class . '<string>|' . SetterOnly::class)]
    public static function fromGamers($input): static {
        return new static();
    }
}