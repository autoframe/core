*PHP array utilities like custom sort, merge for settings profiles, etc*

Namespace:
- Autoframe\\Core\\Exception

Includes:
- Namespace: 
- Class: AfrException
- Extends: Exception
- Implements: Throwable
- Overwritable: define a php path that can be included once with a class named AfrException using the desired namespace

If you don't like the default exception class, you can use your own:

```php

    define('Autoframe\Core\Exception\SWAP_AFR_EXCEPTION','..dir../CustomAfrException.php');
	CustomAfrException.php =>
        class AfrException extends Exception implements Throwable {...}

```
