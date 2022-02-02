<?php

namespace Yeast\Test\Loafpan;

use Error;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Yeast\Loafpan\Loafpan;
use Yeast\Test\Loafpan\Expander\CustomExpanderExpander;
use Yeast\Test\Loafpan\Unit\AcceptMultipleUnits;
use Yeast\Test\Loafpan\Unit\CustomExpander;
use Yeast\Test\Loafpan\Unit\CustomNames;
use Yeast\Test\Loafpan\Unit\InvalidUnit;
use Yeast\Test\Loafpan\Unit\PureOptional;
use Yeast\Test\Loafpan\Unit\Sandwich;
use Yeast\Test\Loafpan\Unit\SelfConsumingUnit;
use Yeast\Test\Loafpan\Unit\SetterAndConstructor;
use Yeast\Test\Loafpan\Unit\SetterOnly;
use Yeast\Test\Loafpan\Unit\SnakeCase;
use Yeast\Test\Loafpan\Unit\Topping;
use Yeast\Test\Loafpan\Unit\Wow;


class ExpanderTest extends TestCase {
    private Loafpan $loafpan;

    protected function setUp(): void {
        $this->loafpan = new Loafpan(__DIR__ . "/../var/cache", ignoreCache: true);
    }

    public function testSomething() {
        $sandwich = $this->loafpan->expandInto(['name' => 'gamers', 'topping' => ['gamer']], Sandwich::class, [Topping::class . '<string>']);
        $this->assertInstanceOf(Sandwich::class, $sandwich);
        $this->assertInstanceOf(Topping::class, $sandwich->topping[0]);
        $this->assertFalse($this->loafpan->validate(Sandwich::class . '<int>', []));
        $this->assertFalse(
          $this->loafpan->validate(Sandwich::class . '<' . Topping::class . '<int>>', [
            'name'    => 't',
            'topping' => [
              't',
            ],
          ])
        );

        $this->assertTrue(
          $this->loafpan->validate(Sandwich::class . '<' . Topping::class . '<int>>', [
            'name'    => 't',
            'topping' => [
              1,
            ],
          ])
        );
    }

    public function testUuid() {
        $this->assertTrue($this->loafpan->validate('list<' . Uuid::class . '>', ['0e3704e6-fece-4b07-b941-c11062402b48']));
        $this->assertFalse($this->loafpan->validate('list<' . Uuid::class . '>', ['oh no!']));
        $items = $this->loafpan->expand('list<' . Uuid::class . '>', ['0e3704e6-fece-4b07-b941-c11062402b48']);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(UuidInterface::class, $items[0]);
    }

    public function testDepth() {
        $data = $this->loafpan->expand('list<list<list<int>>>', [[[1, 2, 3]]]);
        $this->assertEquals([[[1, 2, 3]]], $data);
    }

    public function testRecursion() {
        // Since the only construction of InvalidUnit is having an InvalidUnit, it should always be false
        $this->assertFalse($this->loafpan->validate(InvalidUnit::class, []));

        // Because SelfConsumingUnit exposes a ::fromSelfInt, which then tries to create SelfConsumingUnit<int> which does match, this will work
        // This test makes sure it doesn't get hit in the cross fire of trying to prevent deadloops
        $this->assertTrue($this->loafpan->validate(SelfConsumingUnit::class . '<string>', ['value' => 1]));
        $v = $this->loafpan->expand(SelfConsumingUnit::class . '<string>', ['value' => 1]);
        $this->assertEquals('1', $v->getValue());
    }

    public function testJsonSchema() {
        // In a perfect world, I would test this, however this is not a perfect world, and the JsonSchema library dies on objects that are able to be itself (e.g. Object B is described as being Object B)
        if (defined('MYSTERIES_THING_SO_PHPSTAN_DOESNT_GET_ANGRY')) {
            $schema    = $this->loafpan->jsonSchema(SelfConsumingUnit::class . '<string>');
            $validator = new Validator();
            $v         = (object)['value' => 1];
            $validator->validate($v, $schema);
        }

        $validator = new Validator();
        $schema    = $this->loafpan->jsonSchema(Sandwich::class . '<' . Topping::class . '<string>>');

        $v = (object)['name' => 'gamers', 'topping' => ['gamer']];
        $validator->validate($v, $schema);
        $this->assertTrue($validator->isValid());
    }

    public function testSetterOnly() {
        $setterOnly = $this->loafpan->expand(SetterOnly::class, []);
        $this->assertInstanceOf(SetterOnly::class, $setterOnly);
        $this->assertNull($setterOnly->topping);
        // Should be uninitialized (phpstan doesnt think it'll be thrown? lol)
        try {
            $_ = $setterOnly->gamer;
            /** @phpstan-ignore-next-line */
        } catch (Error $error) {
            $this->assertStringContainsString('must not be accessed before initialization', $error->getMessage());
        }

        $setterOnly = $this->loafpan->expand(SetterOnly::class, ["gamer" => "gamer", "topping" => null]);
        $this->assertInstanceOf(SetterOnly::class, $setterOnly);
        $this->assertEquals("gamer", $setterOnly->gamer);
        $this->assertNull($setterOnly->topping);

        $setterOnly = $this->loafpan->expand(SetterOnly::class, ["gamer" => "gamer", "topping" => "nice"]);
        $this->assertInstanceOf(SetterOnly::class, $setterOnly);
        $this->assertEquals("gamer", $setterOnly->gamer);
        $this->assertInstanceOf(Topping::class, $setterOnly->topping);

        $setterOnly = $this->loafpan->expand(SetterOnly::class, ["gamer" => "gamer", "topping" => "nice", "cornbread" => true]);
        $this->assertInstanceOf(SetterOnly::class, $setterOnly);
        $this->assertEquals("gamer", $setterOnly->gamer);
        $this->assertTrue($setterOnly->hasCornbread);
        $this->assertInstanceOf(Topping::class, $setterOnly->topping);

        $uuid       = "ce1056be-ebd6-497a-980d-6d725ec8d741";
        $setterOnly = $this->loafpan->expand(SetterOnly::class, ["gamer" => "gamer", "topping" => "nice", "uuid" => $uuid]);
        $this->assertInstanceOf(SetterOnly::class, $setterOnly);
        $this->assertEquals("gamer", $setterOnly->gamer);
        $this->assertTrue($setterOnly->uuid?->equals(Uuid::fromString($uuid)));
        $this->assertInstanceOf(Topping::class, $setterOnly->topping);
    }

    public function testSetterAndConstructor() {
        $this->assertFalse($this->loafpan->validate(SetterAndConstructor::class, []));
        $this->assertTrue($this->loafpan->validate(SetterAndConstructor::class, ['number' => 4]));
        $this->assertTrue($this->loafpan->validate(SetterAndConstructor::class, ['number' => 4, 'text' => 'gamers']));

        /** @var SetterAndConstructor $v */
        $v = $this->loafpan->expand(SetterAndConstructor::class, ['number' => 4, 'text' => 'gamers']);
        $this->assertEquals(4, $v->number);
        $this->assertEquals("gamers", $v->text);
    }

    public function testAcceptMultipleUnits() {
        $this->assertTrue($this->loafpan->validate(AcceptMultipleUnits::class, []));
        $this->loafpan->expand(AcceptMultipleUnits::class, []);
        $this->addToAssertionCount(1);
    }

    public function testCustomNames() {
        $this->assertTrue($this->loafpan->validate(CustomNames::class, ['professionals' => 0]));
        $v = $this->loafpan->expandInto(['professionals' => 0, 'book' => "Hello!"], CustomNames::class);
        $this->assertEquals('Hello!', $v->text);
        $this->assertEquals(0, $v->gamers);
    }

    public function testMap() {
        $this->assertTrue($this->loafpan->validate('map<string>', []));
        $this->assertTrue($this->loafpan->validate('map<string>', ["gamer" => "gamer"]));
        $this->assertFalse($this->loafpan->validate('map<int>', ["gamer" => "gamer"]));
    }

    public function testCustomExpander() {
        $expander = $this->loafpan->getExpander(CustomExpander::class);
        $this->assertInstanceOf(CustomExpanderExpander::class, $expander);

        // Make sure the cache is used
        $newLoafpan = new Loafpan($this->loafpan->getCacheDirectory());
        $expander   = $newLoafpan->getExpander(CustomExpander::class);
        $this->assertInstanceOf(CustomExpanderExpander::class, $expander);
    }

    public function testPureOptional() {
        $v = $this->loafpan->expandInto(['gamer' => 4], PureOptional::class);
        $this->assertEquals(4, $v->getGamer());
    }

    public function testCasing() {
        $this->assertFalse($this->loafpan->validate(SnakeCase::class, ['niceGamer' => "hello!"]));
        $this->assertTrue($this->loafpan->validate(SnakeCase::class, ['nice_gamer' => "hello!"]));

        /** @var SnakeCase $v */
        $v = $this->loafpan->expand(SnakeCase::class, ['nice_gamer' => 'Hello!']);
        $this->assertEquals('Hello!', $v->niceGamer);

        $this->assertTrue($this->loafpan->validate(Wow::class, ['longWord' => 4]));
        $lf = new Loafpan($this->loafpan->getCacheDirectory(), casing: 'kebab-case');
        $this->assertFalse($lf->validate(Wow::class, ['longWord' => 4]));
        $this->assertTrue($lf->validate(Wow::class, ['long-word' => 4]));

        // Shouldn't override unit specific casing's
        $this->assertFalse($lf->validate(SnakeCase::class, ['nice-gamer' => "hello!"]));
        $this->assertTrue($lf->validate(SnakeCase::class, ['nice_gamer' => "hello!"]));
    }
}
