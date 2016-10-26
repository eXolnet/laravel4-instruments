# Laravel4 Instruments

This project aim to easily add metric tracking to your Laravel 4 applications. Three drivers are currently supported: StatsD, Log and Null. The following metrics are currently tracked:

* Request
* Response
* Browser timing (first byte, ready and load)
* SQL Queries
* Authentifications
* Mail
* Queue
* Cache

If your're looking for the Laravel 5 version of this library, see [this project](https://github.com/eXolnet/laravel-instruments).

## Install - WIP

Via Composer

```json
{
    "require": {
        "exolnet/laravel4-instruments": "WIP"
    }
}
```

To use the library, you must register the Laravel 4 service provider. Find the `providers` key in your `config/app.php` and register the `Instruments Service Provider`.

```php
    'providers' => array(
        // ...
        Exolnet\Instruments\InstrumentsServiceProvider::class,
    )
```

## Testing

To run the phpUnit tests, please use:

``` bash
$ vendor/bin/phpunit -c phpunix.xml
```

## License

This code is licensed under the [MIT license](http://choosealicense.com/licenses/mit/). Please see the [license file](LICENSE) for more information.
