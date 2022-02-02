<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit]
class CustomNames {
    public function __construct(
      #[Field(name: "professionals")]
      public int $gamers,
    ) {
    }

    #[Field(name: "book")]
    public string $text;
}