# microdata

Get microdata from web page !

## Install

You can install this lib using [Composer](https://getcomposer.org/) or by cloning this repository.

By using **Composer**, just add following lines into your `composer.json` file and run `composer update`:
```json
{
    "require": {
        "malenki/microdata": "dev-master"
    }
}
```

By cloning this repository, just do `git clone https://github.com/malenkiki/microdata.git`.

## Coding using the library

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
$md->availableChecking(); // no arg: it takes JSON from official website
//or
$md->availableChecking('all.json'); // arg: it takes JSON from file system
print($md); // If errors found, they will be present into the returned JSON
```

## Using CLI app

This library comes with a small CLI application too.

Its use is simple. If you have not idea how to used it, just do `bin/microdata --help` or read following lines.

To get microdata from an URL, do:

```
$ bin/microdata 'http://some.url/path/'
```

Same as previous but getting JSON in place:

```
$ bin/microdata --json 'http://some.url/path/'
```

You can request checking too (only schemas defined on schema.org):

```
$ bin/microdata --check 'http://some.url/path/'
```

Same as previous but with a JSON schema on local filesystem:

```
$ bin/microdata --check --check-file foo.json 'http://some.url/path/'
```

You can even use pipe or standard input too:

```
$ echo '<p itemscope itemtype="http://schema.org/Product"><span itemprop="name">Truc</span></p>' | bin/microdata --pipe

```
