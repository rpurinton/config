# RPurinton Config

A basic configuration loading utility for PHP projects.

## Installation

You can install the package via Composer:

```bash
composer require rpurinton/config
```

## Usage

Here's a simple example of how to use the configuration loader:

```php
require 'vendor/autoload.php';

use RPurinton\Config;

$config = new Config();
$config->load('path/to/config/file');

// Access configuration values
$value = $config->get('key');
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).