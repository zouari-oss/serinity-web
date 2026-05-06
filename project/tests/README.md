# Dynamic && static tests

## Static test

To run static test:

```bash
php vendor/bin/phpstan analyse -c path_to_phpstan_neon_file --no-progress
```

## Dynamic test

To run dynamic test:

```bash
php bin/phpunit path_to_php_file
```
