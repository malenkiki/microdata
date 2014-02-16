microdata
=========

Get microdata from web page !

With this library, you get microdata as **tree object**.

You can get microdata from given **URL** or given **content as string**:

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
//or
$md = new Microdata($some_content, Microdata::AS_STRING);
var_dump($md->extract());
```

In string context, print the **JSON** microdata tree:

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
print($md);
```

You can get statistical data about amount of types found:

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
var_dump($md->getAllTypeCount());
```

Next enhencement will include **microdata checking**! Stay in touch!
