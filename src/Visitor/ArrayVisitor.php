<?php

namespace Yeast\Loafpan\Visitor;

use Yeast\Loafpan\Visitor;


class ArrayVisitor extends ValueVisitor {
    function isObject(): bool {
        return is_array($this->value) && ( ! array_is_list($this->value) || count($this->value) === 0);
    }

    function hasKey(string $key): bool {
        return array_key_exists($key, $this->value);
    }

    protected function enter(mixed $value): Visitor {
        return new ArrayVisitor($value);
    }

    function enterObject(string $key): Visitor {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->enter($this->value[$key]);
    }

    public function keys(): array {
        return array_keys($this->value);
    }
}