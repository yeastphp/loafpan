<?php

namespace Yeast\Loafpan\Expander;

use DateTime;
use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;


class DateTimeExpander implements UnitExpander {
    public static function create(Loafpan $loafpan): static {
        return new DateTimeExpander();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(mixed $input, array $generic = [], array $path = []): bool {
        return is_string($input) && false !== DateTime::createFromFormat(DATE_ISO8601, $input);
    }

    /**
     * @param  mixed  $input
     * @param  array  $generic
     *
     * @return DateTime
     */
    public function expand(mixed $input, array $generic = [], array $path = []): mixed {
        return DateTime::createFromFormat(DATE_ISO8601, $input);
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return ["type" => "string", "format" => "date-time"];
    }
}