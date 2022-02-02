<?php

namespace Yeast\Test\Loafpan\Unit;


use Yeast\Loafpan\Attribute\Expander;
use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit("Defines a new sandwich", generics: ["T"])]
class Sandwich {
    public function __construct(
      #[Field("The name of the sandwich")]
      public string $name,
      #[Field(type: 'list<T>')]
      public array $topping = [],
      #[Field("oh no")]
      public int|string $amount = 4,
      #[Field("oh no", type: 'int|string|' . Sandwich::class . '<T>')]
      public int|string|Sandwich $with = 4,
    ) {
    }

    #[Expander]
    public static function fromString(string $name): static {
        return new Sandwich($name);
    }

    #[Expander("Create a sandwich purely from topping", type: 'int|' . Topping::class . '<T>')]
    public static function fromId(int|Topping $id): static {
        return new Sandwich("$id");
    }

    public function __toString(): string {
        return "Sandwich[$this->name]($this->topping)";
    }
}