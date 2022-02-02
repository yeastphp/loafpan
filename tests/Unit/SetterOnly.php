<?php

namespace Yeast\Test\Loafpan\Unit;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Yeast\Loafpan\Attribute\Field;
use Yeast\Loafpan\Attribute\Unit;


#[Unit]
class SetterOnly {
    #[Field]
    public string $gamer;

    #[Field(type: Topping::class . '<string>|null')]
    public ?Topping $topping = null;

    #[Field("It's a neat UUID!", type: Uuid::class)]
    public ?UuidInterface $uuid = null;

    public bool $hasCornbread = false;

    #[Field]
    public function cornbread(bool $gamer) {
        $this->hasCornbread = $gamer;
    }
}