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

Now, you can **check microdata** with schemas defined on <www.schema.org> using their JSON definition type from website or from your own JSON file stored on your file system.

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
$md->availableChecking(); // no arg: takes JSON from official website
//or
$md->availableChecking('all.json'); // arg: takes JSON from file system
var_dump($md->extract()); // If errors found, they will be present into the returned JSON
```
