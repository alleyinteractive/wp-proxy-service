# WP Proxy Service

[![Coding Standards](https://github.com/alleyinteractive/wp-proxy-service/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/alleyinteractive/wp-proxy-service/actions/workflows/coding-standards.yml)
[![Testing Suite](https://github.com/alleyinteractive/wp-proxy-service/actions/workflows/unit-test.yml/badge.svg)](https://github.com/alleyinteractive/wp-proxy-service/actions/workflows/unit-test.yml)

A library to proxy a remote request through a WP REST API endpoint.

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/wp-proxy-service
```

## Usage

Use this package like so:

```php
$package = Alley\WP\Proxy_Service\Service();
$package->init();
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley
Interactive](https://github.com/alleyinteractive). Like what you see? [Come work
with us](https://alley.com/careers/).

- [Alley](https://github.com/Alley)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
