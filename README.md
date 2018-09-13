# Laravel Online Migrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/orisintel/laravel-online-migrator.svg?style=flat-square)](https://packagist.org/packages/orisintel/laravel-online-migrator)
[![Build Status](https://img.shields.io/travis/orisintel/laravel-online-migrator/master.svg?style=flat-square)](https://travis-ci.org/orisintel/laravel-online-migrator)
[![Total Downloads](https://img.shields.io/packagist/dt/orisintel/laravel-online-migrator.svg?style=flat-square)](https://packagist.org/packages/orisintel/laravel-online-migrator)

This package minimizes disruptions when applying Laravel's database migrations
using tools like [Percona Online Schema Change](https://www.percona.com/doc/percona-toolkit/LATEST/pt-online-schema-change.html)
or [InnoDB Online DDL](https://dev.mysql.com/doc/refman/5.6/en/innodb-online-ddl.html).
For example, one can write (mostly) standard Laravel migration files then run
"php artisan migrate". Database changes will be automatically converted into
PTOSC commands or online DDL queries.

## Installation

You can install the package via composer:

``` bash
composer require orisintel/laravel-online-migrator
```

The `pt-online-schema-change` command from Percona's toolkit must be in the path
where migrations will be applied or rolled back, unless InnoDB Online DDL is
being used exclusively.

### Configuration

Publish the configuration file:
``` bash
php artisan vendor:publish --provider='OrisIntel\OnlineMigrator\OnlineMigratorServiceProvider'
```

If not using discovery then add the provider to `config/app.php`:
``` php
'providers' => [
    // ...
    OrisIntel\OnlineMigrator\OnlineMigratorServiceProvider::class,
```

## Usage

Run Artisan's migrate to apply migrations online*:
``` bash
php artisan migrate
```
\*Limitations are documented below.

Preview what changes it would make:
``` bash
php artisan migrate --pretend
```

Add PTOSC options using environment variables:
``` bash
PTOSC_OPTIONS='--recursion-method=none'  php artisan migrate
```

Flag migrations known to be incompatible with this tool using the built-in trait:
``` php
class MyMigration extends Migration
{
    use \OrisIntel\OnlineMigrator\OnlineIncompatible
```

Use a different strategy for a single migration:
``` php
class MyMigration extends Migration
{
    use \OrisIntel\OnlineMigrator\InnodbOnlineDdl
```

Adding foreign key with index to existing table:
``` php
class MyColumnWithFkMigration extends Migration
{
    public function up()
    {
        Schema::table('my_table', function ($table) {
            $table->integer('my_fk_id')->index();
        });

        Schema::table('my_table', function ($table) {
            $table->foreign('my_fk_id')->references('id')->on('my_table2');
```

### Limitations
- Only supports Mysql, specifically those versions supported by PTOSC v3
- With PTOSC
  - Adding unique indexes may cause data loss unless tables are manually checked
    beforehand [because of how PTOSC works](https://www.percona.com/doc/percona-toolkit/LATEST/pt-online-schema-change.html#id7)
  - Adding not-null columns [requires a default](https://www.percona.com/doc/percona-toolkit/LATEST/pt-online-schema-change.html#cmdoption-pt-online-schema-change-alter)
  - Renaming a column or dropping a primary key [have additional risks](https://www.percona.com/doc/percona-toolkit/LATEST/pt-online-schema-change.html#id1)
  - Foreign key creation should be done separately from column creation or
    duplicate indexes may be created with slightly different naming
    - Close the `Schema::create()` call and make a separate `Schema::table()`
      call for all FKs in the migration
- With InnoDB Online DDL
  - See the [MySQL Online DDL documentation](https://dev.mysql.com/doc/refman/5.6/en/innodb-create-index-overview.html)
  - May be [problematic on AWS Aurora](https://medium.com/@soomiq/why-you-should-not-use-mysql-5-6-online-ddl-on-aws-aurora-40985d5e90f5)
- Stateful migrations, like those selecting _then_ saving rows,
  will instead need to do one of the following:
  - Use non-selecting queries like `MyModel::where(...)->update(...)`
  - Pipe the raw SQL like `\DB::statement('UPDATE ... SET ... WHERE ...');`
  - Use the `OnlineMigratorIncompatible` trait to mark the migration as
    incompatible

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

If you discover any security related issues, please email
opensource@orisintel.com instead of using the issue tracker.

## Credits

- [Paul R. Rogers](https://github.com/paulrrogers)
- [All Contributors](../../contributors)
- [Percona Team](https://www.percona.com/about-percona/team) for `pt-online-schema-change`

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
