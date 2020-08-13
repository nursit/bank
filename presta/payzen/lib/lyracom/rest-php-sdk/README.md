# Lyra Network REST api SDK


[![Build Status](https://travis-ci.org/lyra/rest-php-sdk.svg?branch=master)](https://travis-ci.org/lyra/rest-php-sdk)
[![Coverage Status](https://coveralls.io/repos/github/lyra/rest-php-sdk/badge.svg?branch=master)](https://coveralls.io/github/lyra/rest-php-sdk?branch=master)
[![Latest Stable Version](https://poser.pugx.org/lyracom/rest-php-sdk/v/stable)](https://packagist.org/packages/lyracom/rest-php-sdk)
[![Latest Unstable Version](https://poser.pugx.org/lyracom/rest-php-sdk/v/unstable)](//packagist.org/packages/lyracom/rest-php-sdk)
[![Total Downloads](https://poser.pugx.org/lyracom/rest-php-sdk/downloads)](https://packagist.org/packages/lyracom/rest-php-sdk)
[![License](https://poser.pugx.org/lyracom/rest-php-sdk/license)](https://packagist.org/packages/lyracom/rest-php-sdk)

Lyra Network REST API SDK.

## Requirements

PHP 5.4.0 and later.

## Installation

Lyra Network REST api SDK is available via [Composer/Packagist](https://packagist.org/packages/lyracom/rest-php-sdk). Just add this line to your `composer.json` file:

```json
"lyracom/rest-php-sdk": "4.0.*"
```

or

```sh
composer require lyracom/rest-php-sdk:4.0.*
```

To use the SDK, use Composer's [autoload](https://getcomposer.org/doc/00-intro.md#autoloading):

```php
require_once('vendor/autoload.php');
```

## Manual Installation

If you do not want to use Composer, you can download the [latest release from github](https://github.com/lyra/rest-php-sdk/releases). 
To use the SDK, include the `autoload.php` file:

```php
require_once('/path/to/php-sdk/autoload.php');
```

## SDK Usage

A simple integration example is [available here](https://github.com/lyra/rest-php-examples/blob/master/www/SDKTest.php)

You can also take a look to our github examples repository: https://github.com/lyra/rest-php-examples

## Run tests

start docker using docker compose:

```sh
docker-compose up -d
````

Install deps
```sh
docker exec -ti lyra-php-api-sdk composer install
```

and run the test suite with:

```sh
docker exec -ti lyra-php-api-sdk ./vendor/bin/phpunit src/
```

## License

This project is licensed under [MIT License](http://en.wikipedia.org/wiki/MIT_License)