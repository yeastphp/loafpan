<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Unit;


#[Unit]
class InvalidUnit {
    #[Expander]
    public static function fromSelf(InvalidUnit $unit) {

    }
}