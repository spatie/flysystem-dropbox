# Flysystem adapter for the Dropbox v2 API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/flysystem-dropbox.svg?style=flat-square)](https://packagist.org/packages/spatie/flysystem-dropbox)
[![Build Status](https://img.shields.io/travis/spatie/flysystem-dropbox/master.svg?style=flat-square)](https://travis-ci.org/spatie/flysystem-dropbox)
[![StyleCI](https://styleci.io/repos/88596787/shield?branch=master)](https://styleci.io/repos/88596787)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/flysystem-dropbox.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/flysystem-dropbox)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/flysystem-dropbox.svg?style=flat-square)](https://packagist.org/packages/spatie/flysystem-dropbox)

This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for Dropbox. Under the hood, the [Dropbox API v2](https://www.dropbox.com/developers/documentation/http/overview) is used.

## Installation

You can install the package via composer:

``` bash
composer require spatie/flysystem-dropbox
```

## Upgrading from thephpleague/flysystem

[thephpleague/flysystem](https://github.com/thephpleague/flysystem) under the hood uses v1 of the Dropbox API which will be turned off soon. Luckily upgrading is easy. Read [this blogpost](https://murze.be/2017/04/dropbox-will-turn-off-v1-of-their-api-soon-its-time-to-update-your-php-application/) to learn how to upgrade.

## Usage

The first thing you need to do is get an authorization token at Dropbox. A token can be generated in the [App Console](https://www.dropbox.com/developers/apps) for any Dropbox API app. You'll find more info at [the Dropbox Developer Blog](https://blogs.dropbox.com/developers/2014/05/generate-an-access-token-for-your-own-account/).

``` php
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

$client = new Client($authorizationToken);

$adapter = new DropboxAdapter($client);

$filesystem = new Filesystem($adapter, ['case_sensitive' => false]);
```
Note: Because Dropbox is not case-sensitive you’ll need to set the 'case_sensitive' option to false.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email freek@spatie.be instead of using the issue tracker.

## Postcardware

You're free to use this package (it's [MIT-licensed](LICENSE.md)), but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

We publish all received postcards [on our company website](https://spatie.be/en/opensource/postcards).

## Stuck on PHP 5?

This package is PHP 7 only. If you need PHP5 support take a look at [this fork](https://github.com/srmklive/flysystem-dropbox-v2).

## Credits

- [Alex Vanderbist](https://github.com/AlexVanderbist)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## Support us

Spatie is a webdesign agency based in Antwerp, Belgium. You'll find an overview of all our open source projects [on our website](https://spatie.be/opensource).

Does your business depend on our contributions? Reach out and support us on [Patreon](https://www.patreon.com/spatie). 
All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
