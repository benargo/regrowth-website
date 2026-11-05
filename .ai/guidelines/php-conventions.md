# PHP Conventions

## Write against PHP 8.4

Ensure new code uses syntax that is not deprecated in PHP 8.4. Always use **explicit** nullable types — `?Type` or `Type|null`, never an implicit nullable from a `null` default:

```php
// Wrong — implicit nullable, deprecated in 8.4
public function __construct(MockInterface $service = null) {}

// Right
public function __construct(?MockInterface $service = null) {}
```
