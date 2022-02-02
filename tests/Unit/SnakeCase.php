<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit(casing: "snake_case")]
class SnakeCase {

    public function __construct(
      #[Field]
      public string $niceGamer
    ) {
    }
}