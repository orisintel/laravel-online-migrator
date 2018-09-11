# Laravel Online Migrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/orisintel/laravel-online-migrator.svg?style=flat-square)](https://packagist.org/packages/orisintel/laravel-online-migrator)
[![Build Status](https://img.shields.io/travis/orisintel/laravel-online-migrator/master.svg?style=flat-square)](https://travis-ci.org/orisintel/laravel-online-migrator)
[![Total Downloads](https://img.shields.io/packagist/dt/orisintel/laravel-online-migrator.svg?style=flat-square)](https://packagist.org/packages/orisintel/laravel-online-migrator)

This package minimizes disruptions when applying Laravel's database migrations
using tools like [Percona Online Schema Change](https://www.percona.com/doc/percona-toolkit/LATEST/pt-online-schema-change.html).
For example, one can write (mostly) standard Laravel migration files then run
"php artisan migrate". Database changes will be automatically automatically
converted into PTOSC commands.

## Installation

You can install the package via composer:

``` bash
composer require orisintel/laravel-online-migrator
```

The `pt-online-schema-change` command from Percona's toolkit must be in the path
where migrations will be applied or rolled back.

### Configuration

Add provider to `config/app.php`:
``` php
'providers' => [
    // ...
    OrisIntel\OnlineMigrator\OnlineMigratorServiceProvider::class,
```

## Usage

Run Artisan's migrate to apply migrations online*.
``` bash
php artisan migrate
```
\*Limitations are documented below.

Preview what changes it would make.
``` bash
php artisan migrate --pretend
```

Add PTOSC options using environment variables:
``` bash
PTOSC_OPTIONS='--check-unique-key-change'  \
  php artisan migrate
```

Flag migrations known to be incompatible with this tool using the built-in trait:
``` php
class MyMigration extends Migration
{
    use \OrisIntel\OnlineMigrator\Trait\OnlineIncompatible
```

### Limitations
- Only supports Mysql, specifically versions supported by pt-online-schema-change
- Creating tables requires primary keys defined in their own PHP statements like
  `$table->primary('my-id');`
- Stateful migrations, like those selecting _then_ saving rows,
  will instead need to do one of the following:
  - Use non-selecting queries like `MyModel::where(...)->update(...)`
  - Pipe the raw SQL like `\DB::statement('UPDATE ... SET ... WHERE ...');`
  - Use the OnlineMigratorIncompatible trait to mark the migration as incompatible
- Migrations which need two stages, such as to avoid unintended back-filling of
  a new default, must be split into separate migration classes or else the query
  extraction will fail; much like "--pretend".


### Testing

``` bash
composer test
```

Output is verbose because `passthru` is used to help debug production problems.
Executing as `phpunit --testdox` may be more readable until the verbosity can be
tamed.

### Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email security@orisintel.com
instead of using the issue tracker.

## Credits

- [Paul R. Rogers](https://github.com/paulrrogers)
- [All Contributors](../../contributors)
- [Percona Team](https://www.percona.com/about-percona/team) for `pt-online-schema-change`

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
