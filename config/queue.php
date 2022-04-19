<?php

return [
    'rabbitmq' => [
        'connections' => [
            'default' => [
                'hostname'                => env('RABBITMQ_HOST', 'simpaas-rabbitmq'),
                'port'                    => env('RABBITMQ_PORT', 5672),
                'username'                => env('RABBITMQ_USER', 'guest'),
                'password'                => env('RABBITMQ_PASSWORD', 'guest'),
                'vhost'               => '/',
                'lazy'                => true,
                'read_write_timeout' => 8,
                'connect_timeout'  => 10.0,
                'heartbeat'           => 4,
            ]
        ],
        'exchanges' => [
            'exchange_1'  => [
                'connection' => 'default',
                'name' => 'exchange_1',
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
                    'bind' => [
                        [
                            'queue' => 'queue_1',
                            'routing_key' => '#.queue_1.#'
                        ]
                    ],
                ],
            ],
        ],
        'queues'              => [
            'cams'                => [
                'connection' => 'default',
                'name' => 'cams',
                'attributes' => [
                    // optional fields
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                    'internal' => false,
                    'nowait' => false,
                    'exclusive' => false,
                    // bind with an exchange
                    'bind' => [
                        [
                            'exchange' => 'amq.direct',
                            'routing_key' => 'cams'
                        ]
                    ],
                    'arguments' => [
                        'x-dead-letter-exchange'    => '',
                        'x-dead-letter-routing-key' => 'retry_cams',
                        'x-max-priority'            => 1
                    ]
                ],
                'retry_queue'        => [
                    'name'        => 'retry_cams',
                    'exchange'    => [
                        'name'    => 'retry',
                        'options' => null,
                    ],
                    'type'        => 'direct',
                    'durable'     => true,
                    'arguments'     => [
                        'x-dead-letter-exchange'    => '',
                        'x-dead-letter-routing-key' => 'cams',
                        'x-message-ttl'             => 1800000, // 30m - TTL period in milliseconds, used to decide when to retry
                    ],
                    'retry_count' => 3,
                ],
            ],
            'queue_1'                => [
                'connection' => 'default',
                'name' => 'queue_1',
                'attributes' => [
                    // optional fields
                    'passive' => false,
                    'durable' => true,
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
                            'routing_key' => 'queue_1'
                        ]
                    ],
                    'arguments' => [
                        'x-dead-letter-exchange'    => '',
                        'x-dead-letter-routing-key' => 'retry_queue_1',
                        'x-max-priority'            => 3
                    ]
                ],
                'retry_queue'        => [
                    'name'        => 'retry_queue_1',
                    'exchange'    => [
                        'name'    => 'retry',
                        'options' => null,
                    ],
                    'type'        => 'direct',
                    'durable'     => true,
                    'arguments'     => [
                        'x-dead-letter-exchange'    => '',
                        'x-dead-letter-routing-key' => 'queue_1',
                        'x-message-ttl'             => 1800000, // 30m - TTL period in milliseconds, used to decide when to retry
                    ],
                    'retry_count' => 3,
                ],
            ],

        ],
        'publishers' => [
            // publisher alias name => queue name or exchange name
            'cams-publisher'       => 'cams',
            'publisher-exchange_1' => 'exchange_1',
            'publisher-queue_1'    => 'queue_1'
        ],
        'consumers' => [
            'cams-consumer' => [
                'queue' => 'cams',
                'prefetch_count' => 300,
                'idle_ttl' => 2,
                'message_processor' => \Levtechdev\Simpaas\Cams\Queue\MessageProcessor::class,
                'log_file' => 'queue/cams.log',
                'processor' => [
                    'class'   => 'CamsQueueProcessor.php',
                    'worker' => 'CamsWorker.php',
                    'log_file' => 'cams-processor.log',
                    'options'  => [
                        'auto_scale'           => true,
                        'num_workers'          => 1,
                        'max_num_workers'      => env('CAMS_QUEUE_MAX_NUMBER_WORKERS', 2), // used when autoscaling to speed up queue processing when a lot of items are in the queue
                        'cycle_time'           => 3,
                        'alert_threshold_size' => 8,
                        'auto_scale_mpw'       => 50, // messages per worker multiplier used in autoscale detection
                    ]
                ]
            ],
            'consumer_1' => [
                'queue' => 'queue_1',
                'prefetch_count' => 2,
                'idle_ttl' => 2,
                'message_processor' => \App\Queue\TestMessageProcessor::class,
                'log_file' => 'queue/queue_1.log',
                'processor' => [
                    'class'   => 'Queue1QueueProcessor.php',
                    'consumer' => [
                        'worker' => 'Queue1Worker.php',
                        'ttl'    => 30, // consumer timeout when idle before terminating (exiting) - if worker will not get qos_prefetch_count messages then it will allow processing any consumed messages only after this ttl timesout
                    ],
                    'log_file' => 'queue1-processor.log',
                    'options'  => [
                        'auto_scale'           => true,
                        'num_workers'          => 1,
                        'max_num_workers'      => env('QUEUE_MAX_NUMBER_WORKERS', 2), // used when autoscaling to speed up queue processing when a lot of items are in the queue
                        'cycle_time'           => 3,
                        'alert_threshold_size' => 8,
                        'auto_scale_mpw'       => 50, // messages per worker multiplier used in autoscale detection
                    ]
                ]
            ]
        ],
    ],
];
