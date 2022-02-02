<?php

namespace Yeast\Loafpan\Internal;

use JetBrains\PhpStorm\ExpectedValues;
use Yeast\Loafpan\Attribute\Field;


class Setter {
    const PROPERTY = 1;
    const METHOD   = 2;

    public function __construct(
      #[ExpectedValues([Setter::METHOD, Setter::PROPERTY])]
      public int $type,
      public string $inputName,
      public Field $field,
      public array $types,
    ) {
    }
}