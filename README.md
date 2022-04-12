# SimPaas app

### Required Service Providers
```php
// bootstrap/app.php
$app->register(Levtechdev\Simpaas\RedisProvider::class);
$app->register(Levtechdev\Simpaas\CoreProvider::class);
```

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
### Routing configuration
``
Change routes/web.php to routes/api.php
``
All routers must be under a prefix like 'v*' 
```php
$router->group(['prefix' => 'v1'], function () use ($router) {
    $router->.....

});
```

```php
// bootstrap/app.php
// ....
$app->router->group([], function ($router) {
    require __DIR__ . DS . '..' . DS . 'routes' . DS . 'api.php';
});

$router = $app->router->getRoutes();
foreach ($router as $key => $value) {
    $app->router->addRoute($value['method'], str_replace('/v1/', '/', $value['uri']), $value['action']);
}

return $app;
```

### Authorization

### Swagger
``
1. Copy Levtechdev\Simpaas\Helper\Swagger to any app folder (for example: app/Core/Helper) and configure comments according to the app
2. Copy swagger folder with static(from IMS or CDMS public/swagger) content to public/ folder
3. run php artisan swag:gen (NOTE: Required user initialization) 
``

