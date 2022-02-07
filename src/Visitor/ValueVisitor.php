<?php

namespace Yeast\Loafpan\Visitor;

use Yeast\Loafpan\Visitor;


class ValueVisitor implements Visitor {
    protected $cache = [];

    public function __construct(protected mixed $value) {
    }

    function isNull(): bool {
        return $this->value == null;
    }

    function isInteger(): bool {
        return is_int($this->value);
    }

    function isFloat(): bool {
        return is_float($this->value);
    }

    function isArray(): bool {
        return is_array($this->value);
    }

    function isObject(): bool {
        return is_object($this->value);
    }

    function hasKey(string $key): bool {
        return property_exists($this->value, $key);
    }

    function length(): int {
        return count($this->value);
    }

    protected function enter(mixed $value): Visitor {
        return new ValueVisitor($value);
    }

    function enterObject(string $key): Visitor {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->enter($this->value->{$key});
    }

    function enterArray(int $key): Visitor {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->enter($this->value[$key]);
    }

    function isBool(): bool {
        return is_bool($this->value);
    }

    function isString(): bool {
        return is_string($this->value);
    }

    function isList(): bool {
        return is_array($this->value) && array_is_list($this->value);
    }

    public function getValue(): mixed {
        return $this->value;
    }

    public function keys(): array {
        return array_keys((array)$this->value);
    }
}