<?php

namespace Yeast\Loafpan;

/**
 * The visitor interface is used to enter and type check user data
 *
 * User data is wrapped around a visitor and then passed through the expanders, this allows the user input to be in different formats are representations
 */
interface Visitor {
    /**
     * If current value is null
     *
     * @return bool
     */
    function isNull(): bool;

    /**
     * If current value is an integer
     *
     * @return bool
     */
    function isInteger(): bool;

    /**
     * If current value is a float
     *
     * @return bool
     */
    function isFloat(): bool;

    /**
     * If current value is an array (php array, can be either list or object)
     *
     * This is only used when a user asks for the php type array
     *
     * @return bool
     */
    function isArray(): bool;

    /**
     * If the current value is a boolean
     *
     * @return bool
     */
    function isBool(): bool;

    /**
     * If the current value is a string
     *
     * @return bool
     */
    function isString(): bool;

    /**
     * If the current value is an object (JSON like object, or map)
     *
     * @return bool
     */
    function isObject(): bool;

    /**
     * If the current value is a list (JSON like array, Java like List)
     *
     * @return bool
     */
    function isList(): bool;

    /**
     * The length of this array or list
     *
     * @return int
     */
    function length(): int;

    /**
     * The keys of this object
     *
     * @return array
     */
    function keys(): array;

    /**
     * Get the visitor for the object entry with the name $key
     *
     * @param  string  $key
     *
     * @return Visitor
     */
    function enterObject(string $key): Visitor;

    /**
     * If there's a value (*including null*) under the name $key
     *
     * @param  string  $key
     *
     * @return bool
     */
    function hasKey(string $key): bool;

    /**
     * Get the visitor for the list entry at the index $key
     *
     * @param  int  $key
     *
     * @return Visitor
     */
    function enterArray(int $key): Visitor;

    /**
     * Get the raw value
     *
     * @return mixed
     */
    function getValue(): mixed;
}