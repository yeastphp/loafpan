<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit]
class PureOptional {
    public function __construct(
      #[Field]
      private int $gamer = 1,
    ) {
    }

    public function getGamer(): int {
        return $this->gamer;
    }
}