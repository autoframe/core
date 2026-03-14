# TAP explained:

The tap function allows us to simplify the example in one statement, like this:
```php
return Tap::tap(new stdClass, function($person) {
    $person->name = 'John';
});
```

Since the tap function always returns the first argument, $value, we can immediately access the members of that $value (new stdClass).

```php
echo Tap::tap(new stdClass, function($person) {
    $person->name = 'Jane';
})->name; // Jane

```

Again, without the tap function, the above example would take the shape of this:

```php
$person = new stdClass;
$person->name = 'Jane';
echo $person->name; // Jane
```

Use tap to return the object instance

```php
$person->setName('John'); // --> returns 'Done'.
// For some reason we want to ignore the returned value 'Done':
Tap::tap($person)->setName('John'); // --> returns $person.
// Notice the No. of arguments in Tap::tap(). No callback.

```