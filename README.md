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

$config = new Config("MySQL");
print_r($config);

/*
Example Output:

RPurinton\Config Object
(
    [config:RPurinton\Config:public] => Array
        (
            [host] => localhost
            [user] => root
            [pass] => password
            [db] => my_database
        )
)
*/

// Change the configuration file
$config->config['pass'] = 'new_password';
$config->save();

```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).