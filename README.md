# Loafpan

A simple PHP 8 native object expansion (or hydration as some call it) framework

### Features

- Only focused on deserialization of data, thus perfect for configs
- JSON Schema generation (also works for yaml!)
- PHP 8 Attribute guided
- Simple templating/generics support
- Simple alternative format expanders
- Custom expander support allows for expanding out-of-branch objects
- Readable code generation, for ease of debugging and speed

### Installation

```bash
composer require yeast/loafpan
```

### Very quick use

```php
$config      = json_decode($json);

$loafpan     = new Loafpan($loafpanCacheDirectory);
// Don't forget to annotate your class.
$configClass = $loafpan->expand(MyConfig::class, $config);
```

## Usage

To start using Loafpan, you first need to annotate your objects, in the next few examples we will create to fake classes
to explain the details

An expandable object is called a "Unit", Units either need to be either annotated with `Unit` or have a custom expander
registered. However for now we'll cover annotated Units

In the first example, Sandwich is a purely setter based unit, and will use the default constructor to instantiate the
object and then set the properties manually.

Loafpan is here guided by the `Field` attributes, which signal which properties can be applied from an object.

```php
// The first argument of `Unit` is the description of the object
// This is used in the JSON Schema generation
#[Unit("This is a very nice sandwich")]
class Sandwich {
    // A custom field name can be given with the `name` parameter
    #[Field(name: "title")]
    public string $name = "";
    // since PHP has no native support for generics or typed arrays (yet)
    // one can override the type, and use list<T> to define the actual type
    #[Field("The toppings of this sandwich", type: 'list<Yeast\Demo\Topping>')]
    public array $toppings = [];
}
```

Topping however is a purely expander based, and will take only a string, as this is the only instantiation method
available

Expander functions are public static functions with either 1 or 2 arguments (the optional second one being a Loafpan
instance), the first argument defines the input type that can be used to expand the object from, e.g. Topping can be
made from a string alone

```php
#[Unit("What goes on the bread stays on the bread")]
class Topping {
    private function __construct(private bool $wet = false) {}
    
    #[Expander]
    public static function fromName(string $name) {
        return new static($name === 'water' ? true : false);
    }
}
```

While Topping only defines 1 Expander, you can add multiple, be aware that the results can be unpredictable when the
types overlap.

With the 2 classes we just defined, a valid json object for Sandwich would be

```json5
{
  // because the "name" property has set it's name to "title",
  // the json object must use title
  "title": "Soggy sandwich",
  "toppings": [
    "water",
    "2 pounds of lead"
  ]
}
```

which will roughly translate into

```php
$sandwich = new Sandwich();
$sandwich->name = "Soggy sandwich";
$sandwich->toppings = [
   Topping::fromString("water"),
   Topping::fromString("2 pounds of lead")
];
```

Combining this all together requires us to create a Loafpan class

```php
$loafpan = new Loafpan($yourLoafpanCacheDirectory);
/** @var Sandwich $sandwich */
$sandwich = $loafpan->expand(Sandwich::class, [
    "title" => "Soggy sandwich",
    "toppings" => [
        "water",
        "2 pounds of lead"
    ]
]);

echo "I have a sandwich called " . $sandwich->name . " the topping:\n";
foreach ($sandwich->toppping as $topping) {
    echo " - " . ($topping->wet ? 'wet' : 'not wet') . "\n";
}
```

If the options given here doesn't give you enough flexibility, you can always implement your own expander. Just
implement `UnitExpander` (`\Yeast\Loafpan\UnitExpander`) on a class and either register it to loafpan by using
the `registerExpander` function on a Loafpan instance or set the `expander` parameter on the `Unit` attribute

See [src/Expander](src/Expander) for some examples of custom UnitExpanders

## Default expanders

- `list<T>` - only accepts an array list with items of type T
- `map<T>` - only accepts an associative array with items of type T
- `DateTime`/`DateTimeImmutable` - only accepts a string with an ISO-8601 formatted date
- `Ramsey\Uuid\Uuid`/`Ramsey\Uuid\UuidInterface` - only accepts a string with a properly formatted UUID

## Todo

- Generate in-depth errors about invalid input