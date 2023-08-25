<?php

namespace Yeast\Loafpan;

/**
 * @template T
 */
interface UnitExpander extends BaseUnitExpander
{

    /**
     * Validate that given input can be used to expand to a unit
     *
     * @param  Visitor  $visitor  The user input, can be anything
     * @param  string[]  $generic  The generic type parameters used for this unit (positional)
     * @param  string[]  $path  The path Loafpan has taken so far to validate this input, this is used for JIT dead loop prevention. and should thus be passed to Loafpan again if you're passing the same input through Loafpan again
     *
     * @return bool
     */
    public function validate(Visitor $visitor, array $generic = [], array $path = []): bool;

    /**
     * Expand the input into a unit with given generic parameters, will throw on failure
     *
     * @param  Visitor  $visitor  The user input, can be anything
     * @param  string[]  $generic  The generic type parameters used for this unit (positional)
     * @param  string[]  $path  The path Loafpan has taken so far to expand this input, this is used for JIT dead loop prevention. and should thus be passed to Loafpan again if you're passing the same input through Loafpan again
     *
     * @return T
     */
    public function expand(Visitor $visitor, array $generic = [], array $path = []): mixed;
}