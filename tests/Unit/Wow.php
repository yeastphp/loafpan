<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit]
class Wow {
    public function __construct(
      #[Field]
      public int $longWord
    ) {
    }
}