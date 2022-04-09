# SimPaas app

### Required Service Providers
```php
// bootstrap/app.php
$app->register(Levtechdev\Simpaas\RedisProvider::class);
$app->register(Levtechdev\Simpaas\CoreProvider::class);
```

### Optional Service Providers

### Register Middlewares
```php
// bootstrap/app.php
use Levtechdev\Simpaas\Middleware\Http\{JwtMiddleware, JsonSchemaMiddleware, CorsMiddleware, MaintenanceModeMiddleware, LoggerMiddleware};

// .... 
$app->routeMiddleware([
        JwtMiddleware::NAME            => JwtMiddleware::class,
        JsonSchemaMiddleware::NAME     => JsonSchemaMiddleware::class,
    ]
);

$app->middleware(
    [
        CorsMiddleware::class,
        MaintenanceModeMiddleware::class,
        LoggerMiddleware::class
    ]
);
// .... 
```

### Authorization
