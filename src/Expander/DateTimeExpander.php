<?php

namespace Yeast\Loafpan\Expander;

use DateTime;
use Yeast\Loafpan\JsonSchemaBuilder;
use Yeast\Loafpan\Loafpan;
use Yeast\Loafpan\UnitExpander;
use Yeast\Loafpan\Visitor;


/**
 * An expander for the DateTime object, expects a ISO-8601 string as input
 *
 * @implements UnitExpander<DateTime>
 */
class DateTimeExpander implements UnitExpander {
    /**
     * @param  Loafpan  $loafpan
     *
     * @return self
     */
    public static function create(Loafpan $loafpan): UnitExpander {
        return new self();
    }

    public function getGenerics(): array {
        return [];
    }

    public function validate(Visitor $visitor, array $generic = [], array $path = []): bool {
        return $visitor->isString() && false !== DateTime::createFromFormat(DATE_ATOM, $visitor->getValue());
    }

    /**
     * @param  Visitor  $visitor
     * @param  array  $generic
     *
     * @return DateTime
     */
    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed {
        return DateTime::createFromFormat(DATE_ATOM, $visitor->getValue()) ?: throw new \RuntimeException("Invalid ISO-8601 string given");
    }

    public function buildSchema(JsonSchemaBuilder $builder, array $generic, string $definitionName): array {
        return ["type" => "string", "format" => "date-time"];
    }
}