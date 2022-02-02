<?php

namespace Yeast\Test\Loafpan\Unit;

use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit(generics: ['T'])]
class SelfConsumingUnit {
    public function __construct(
      #[Field(type: 'T')]
      private mixed $value,
    ) {
    }

    public function getValue(): mixed {
        return $this->value;
    }

    #[Expander(type: SelfConsumingUnit::class . '<int>')]
    public static function fromSelfInt(self $self): SelfConsumingUnit {
        return new SelfConsumingUnit((string)$self->value);
    }
}