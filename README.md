microdata
=========

Get microdata form web page !

Microdata are like tree object. You can get microdata for given URL or given content as string:

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
//or
$md = new Microdata($some_content, Microdata::AS_STRING);
var_dump($md->extract());
```

In string context, printis JSON microdata tree:

```php
use \Malenki\Microdata;
$md = new Microdata('http://www.some-url.com/path/page.html');
print($md);
```

Next enhencement will include microdata checking! Stay in touch!
