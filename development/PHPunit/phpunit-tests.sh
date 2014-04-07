#!/bin/sh
php -d asp_tags=On /usr/bin/phpunit --verbose SmartyTests.php > test_results.txt
