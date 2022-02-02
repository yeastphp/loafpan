<?php

namespace Yeast\Loafpan\Expander;

use DateTimeImmutable;
use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;


class DateTimeImmutableExpander implements UnitExpander {
    public static function create(Loafpan $loafpan): static {
        return new DateTimeImmutableExpander();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(mixed $input, array $generic = [], array $path = []): bool {
        return is_string($input) && false !== DateTimeImmutable::createFromFormat(DATE_ISO8601, $input);
    }

    /**
     * @param  mixed  $input
     * @param  array  $generic
     *
     * @return DateTimeImmutable
     */
    public function expand(mixed $input, array $generic = [], array $path = []): mixed {
        return DateTimeImmutable::createFromFormat(DATE_ISO8601, $input);
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return ["type" => "string", "format" => "date-time"];
    }
}