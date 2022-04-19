# SimPaas app

### System Requirements
| Software      | Version                                                                                                                              |
| ------------- |--------------------------------------------------------------------------------------------------------------------------------------|
| PHP Composer  | `2.3.x`                                                                                                                              |
| PHP Framework | `Lumen 9.0.x`                                                                                                                        |
| Web Server    | `Nginx 1.x`                                                                                                                          |
| PHP FPM       | `8.0.x`                                                                                                                              |
| PHP Modules   | `curl, procps, pcntl, gd, iconv, intl, mbstring, bcmath, sockets, opcache, openssl, SimpleXML, soap, libxml, zip, json, xmlrpc, xsl` |
| ElasticSearch | `7.16.4`                                                                                                                             |                                                                                                                                    |
| Redis (backend cache) | `6.2.x`                                                                                                                              |
| Redis (database storage) | `6.2.x`                                                                                                                              |
| RabittMQ | `RabbitMQ 3.8.x Erlang 23.2.x`                                                                                                       |                                                                                                              |

## Install Simpaas framework in the app

``
composer require levtechdev\simpaaas
``

### Required configs
copy configs (global, log) from simpass/config to app/config
```php
// bootstrap
$app->configure('global');
$app->configure('log');
```

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
1. Override Authorization/Migration/SampleData.php in your app with new role rules and Register it in app Service Provider
2. Init users
 ```php 
 php artisan app:init --initUsers
 ```

### Elasticsearch
1. Add to bootstrap  
```php
// bootstrap
$app->configure('database') // and configure env
...
$app->register(Levtechdev\Simpaas\ElasticsearchProvider::class);
```
2. Define Entity in the Elasticsearch scope (Model, Resource, Collection)
3. Create Class witch are implemented EntityResourceModelMapperInterface
   example:
```php
class NewClass implements EntityResourceModelMapperInterface
   protected array $mapping = [
         TestElasticModel::ENTITY => TestElasticResourceModel::class,
   ];

   public function getResourceModelClassNameByEntityType(string $type): string|false
   {
   return $this->mapping[$type] ?? false;
   }

   public function getInstalledResources(): array
   {
   return $this->mapping;
   }
}
```
NOTE: Bind this interface with NewClass in app service provider

5. Create entity json schema into public/json-schema/v1/definitions/catalog.json
6. Create index 
```phpr
  php artisan elastic:create catalog_entity  
```

### Swagger
1. Copy Levtechdev\Simpaas\Helper\Swagger to any app folder (for example: app/Core/Helper) and configure comments according to the app
2. Copy swagger folder with static(from IMS or CDMS public/swagger) content to public/ folder
3. run 
```php 
   php artisan swag:gen (NOTE: Required user initialization) 
```

### RabbitMq
1. Copy simpaas\config\queue.php to app/config/queue.php and configure exchanges, queues, consumers, publishers
2. Configure env according to queue.php
3. Add to your app bootrstrap
```php
 $app->configure('queue');
 $app->register(Levtechdev\Simpaas\RabbitMqProvider::class);    
```
4. Init queue entities 
```php
   php artisan rabbitmq:setup    
```
5. Queue entities list is 
```php
   php artisan rabbitmq:list
```   
6. copy shell/queue to shell/queue folder and create processor and workers
7. NOTE: Enable queue consuming: add queue name into local/queue/enabled_queue (separator is ,)
8. In your App implement
```php
   Queue/RabbitMq/Processor/AbstractMessageProcessor::class,
   Queue/Manager/AbstractConsumer::class
   Queue/Manager/AbstractPublisher::class
 ```
### Cams
```
.env
CAMS_HOST=
CAMS_AUTH_USER=
CAMS_AUTH_PASSWORD=
CAMS_QUEUE_MAX_NUMBER_WORKERS=
```
1. Configure cams queue entity in config/queue.php.. See example in simpaas\config\queue.php
2. Use Levtechdev\Simpaas\Queue\PublisherManager class for sending items to cams queue 

### Option config
#### Log
1. Debug level, see Levtechdev\Simpaas\Service\DebugLogTrait
```
 DEBUG_LEVEL=true   - write debug log see  
```