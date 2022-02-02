<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Unit;
use Yeast\Loafpan\Loafpan;


#[Unit(generics: ['T'])]
class Topping {
    public function __construct(public string $name) {
    }

    #[Expander(type: 'T')]
    public static function fromString(mixed $input, Loafpan $gamer) {
        return new Topping((string)$input);
    }

    public function __toString(): string {
        return 'Topping(' . $this->name . ')';
    }
}