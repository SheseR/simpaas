<?php

return [
    'rabbitmq' => [
        'connections' => [
            'default' => [
                'host'                => env('RABBITMQ_HOST', 'ims-rabbitmq'),
                'port'                => env('RABBITMQ_PORT', 5672),
                'user'                => env('RABBITMQ_USER', 'guest'),
                'pass'                => env('RABBITMQ_PASSWORD', 'guest'),
                'vhost'               => '/',
                'read_timeout'        => 15.0,
                'write_timeout'       => 15.0,
                'connection_timeout'  => 10.0,
                'heartbeat'           => 0.0,
                'persisted'           => true,
                'lazy'                => true,
                'qos_global'          => false, // prefetch count applied separately to each new consumer on the channel
                'qos_prefetch_size'   => 0, // no specific limit on message size when sending to consumer
                'qos_prefetch_count'  => 1, // the limit of unacknowledged messages on a channel (or connection) when consuming (aka "prefetch count")
                'ssl_on'              => false,
                'ssl_verify'          => true,
                'ssl_cacert'          => '',
                'ssl_cert'            => '',
                'ssl_key'             => '',
                'ssl_passphrase'      => '',
                'stream'              => true,
                'insist'              => false,
                'login_method'        => 'AMQPLAIN',
                'login_response'      => null,
                'locale'              => 'en_US',
                'keepalive'           => false,
                'channel_rpc_timeout' => 0.0,
                'heartbeat_on_tick'   => true,
            ]
        ],
        'exchanges' => [
            'exchange_1'  => [
                'connection' => 'default',
                'type' => 'topic',
                'attributes' => [
                    // mandatory fields
                    'exchange_type' => 'topic',
                    // optional fields - if none is set,
                    // the defaults will be used
                    'passive' => false,
                    'durable' => false,
                    'auto_delete' => false,
                    'internal' => false,
                    'nowait' => false,
                    'bind' =>[
                        'queue' => 'queue_1',
                        'routing_key' => '#.queue_1.#'
                    ],
                ],
            ],
        ],
        'queues'              => [
            // end init exchanges
            // start init queues
            'queue_1'                => [
                'connection' => 'default',
                'name' => 'queue_1',
                'attributes' => [
                    // optional fields
                    'passive' => false,
                    'durable' => false,
                    'auto_delete' => false,
                    'internal' => false,
                    'nowait' => false,
                    'exclusive' => false,
                    // bind with an exchange
                    'bind' => [
                        [
                            'exchange' => 'exchange_1',
                            'routing_key' => '#.queue_1.#'
                        ],
                        [
                            'exchange' => 'amq.direct',
                            'routing_key' => 'image_upload'
                        ]
                    ]
                ],
                'bind' => [
                    [
                        'exchange' => 'exchange_1',
                        'routing_key' => '#.queue_1.#'
                    ],
                    [
                        'exchange' => 'amq.direct',
                        'routing_key' => 'image_upload'
                    ]
                ],
                'options'            => [
                    'x-dead-letter-exchange'    => '',
                    'x-dead-letter-routing-key' => 'retry_queue_1',
                    'x-max-priority'            => 3
                ],
                'retry_queue'        => [
                    'name'        => 'retry_queue_1',
                    'exchange'    => [
                        'name'    => 'retry',
                        'options' => null,
                    ],
                    'type'        => 'direct',
                    'durable'     => true,
                    'options'     => [
                        'x-dead-letter-exchange'    => '',
                        'x-dead-letter-routing-key' => 'queue_1',
                        'x-message-ttl'             => 1800000, // 30m - TTL period in milliseconds, used to decide when to retry
                    ],
                    'retry_count' => 3,
                ],

            ],
        ],
        'publishers' => [
            'publisher-exchange_1' => 'exchange_1',
            'publisher-queue_1'    => ''
        ],
        'consumers' => [
            'consumerAliasName' => [
                'queue' => 'InternalAliasNameForTheQueue',
                'prefetch_count' => 10,
                'message_processor' => ""
            ]
        ],
        'processors' => [
            [
                'script'   => 'ImageUploadQueueProcessor.php',
                'status'   => true,
                'consumer' => [
                    'script' => 'ImageUploadWorker.php',
                    'ttl'    => 30, // consumer timeout when idle before terminating (exiting) - if worker will not get qos_prefetch_count messages then it will allow processing any consumed messages only after this ttl timesout
                ],
                'log_file' => 'queue_processor_image.log',
                'options'  => [
                    'auto_scale'           => true,
                    'num_workers'          => 1,
                    'max_num_workers'      => env('QUEUE_MAX_NUMBER_WORKERS_IMAGE_UPLOAD', 1), // used when autoscaling to speed up queue processing when a lot of items are in the queue
                    'cycle_time'           => 3,
                    'alert_threshold_size' => 8,
                    'auto_scale_mpw'       => 50, // messages per worker multiplier used in autoscale detection
                ]
            ],
        ]
    ],
];
