<?php

return [

    // --------------- Mysql config ---------------------- //
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'read' => [
                'host' => [
                    env('DB_HOST_READ', 'simpaas-mysql')
                ],
            ],
            'write' => [
                'host' => [
                    env('DB_HOST_WRITE', 'simpaas-mysql')
                ],
            ],
            'sticky'    => true,
            'driver'    => 'mysql',
            'port'      => env('DB_PORT', 3306),
            'database'  => env('DB_DATABASE', 'simpaas'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', 'password'),
            'charset'   => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
            'prefix'    => env('DB_PREFIX', ''),
            'timezone'  => env('DB_TIMEZONE', '+00:00'),
            'strict'    => env('DB_STRICT_MODE', false),
        ],

    ],

    /*
        |--------------------------------------------------------------------------
        | Migration Repository Table
        |--------------------------------------------------------------------------
        |
        | This table keeps track of all the migrations that have already run for
        | your application. Using this information, we can determine which of
        | the migrations on disk haven't actually been run in the database.
        |
        */

    'migrations' => 'migrations',

    // ---------------Mysql config End ---------------------- //


    // ---------------Redis config     ---------------------- //
    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */
    'redis'         => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
        'deduplication_hash' => [
            'host'     => env('REDIS_DEDUPLICATION_HOST', '127.0.0.1'),
            'password' => env('REDIS_DEDUPLICATION_PASSWORD', null),
            'port'     => env('REDIS_DEDUPLICATION_PORT', 6379),
            'database' => env('REDIS_DEDUPLICATION_DB', 3),
        ],
        'cache'   => [
            'host'     => env('REDIS_CACHE_HOST', '127.0.0.1'),
            'password' => env('REDIS_CACHE_PASSWORD', null),
            'port'     => env('REDIS_CACHE_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 0),
        ],
    ],

    // ---------------Redis config  END  ------------------------- //

    // ---------------Elasticsearch config ---------------------- //
    'elasticsearch' => [
        'default' => [
            'read_client'  => [
                'hosts' => [
                    [
                        'host' => env('ELASTICSEARCH_DEFAULT_READ_HOST'),
                        'port' => env('ELASTICSEARCH_DEFAULT_READ_PORT'),
                    ]
                ],
                'auth' => [
                    'user' => env('ELASTICSEARCH_DEFAULT_AUTH_USER', null),
                    'password' => env('ELASTICSEARCH_DEFAULT_AUTH_PASSWORD', null),
                ],
                'logger' => [
                    'channel' => 'elasticsearch',
                    'log_file' =>  'elasticsearch.log'
                ]
            ],
            'write_client' => [
                'hosts' => [
                    [
                        'host' => env('ELASTICSEARCH_DEFAULT_WRITE_HOST'),
                        'port' => env('ELASTICSEARCH_DEFAULT_WRITE_PORT'),
                    ]
                ],
                'auth' => [
                    'user' => env('ELASTICSEARCH_DEFAULT_AUTH_USER', null),
                    'password' => env('ELASTICSEARCH_DEFAULT_AUTH_PASSWORD', null),
                ],
                'logger' => [
                    'channel' => 'elasticsearch',
                    'log_file' =>  'elasticsearch.log'
                ]
            ],
        ],
    ]

    // ---------------Elasticsearch config END ---------------------- //
];
