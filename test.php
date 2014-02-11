#!/usr/bin/env php
<?php

namespace Malenki;

if(count($_SERVER['argv']) == 1)
{
    fwrite(STDERR, 'You must give URL to parse!');
    fwrite(STDERR, PHP_EOL);
    exit(1);
}
include('vendor/autoload.php');



$md = new Microdata($_SERVER['argv'][1]);
print($md);
