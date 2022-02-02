<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Unit;
use Yeast\Test\Loafpan\Expander\CustomExpanderExpander;


#[Unit(expander: CustomExpanderExpander::class)]
class CustomExpander {

}