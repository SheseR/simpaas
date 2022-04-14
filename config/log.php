<?php

return [
    'verbosity_level' => (env('APP_ENV') === \Levtechdev\Simpaas\Helper\SystemInfo::APP_ENV_QA)
        ? \Levtechdev\Simpaas\Helper\Logger::LOG_VERBOSITY_LEVEL_MODERATE
        : \Levtechdev\Simpaas\Helper\Logger::LOG_VERBOSITY_LEVEL_VERBOSE,

    'format'          => env('LOG_FORMAT', \Levtechdev\Simpaas\Helper\Logger::LOG_FILE_FORMAT_JSON),

    'debug_filter_data_keys' => [
        'username',
        'password',
        'login',
        'passhash',
        'auth_password',
        'cc_cid',
        'card_uri',
        'cc_exp_month',
        'cc_exp_year',
        'cc_saved',
        'cc_number',
        'cc_customer',
        'Authorization',
    ],

    'debug_filter_cookie_keys' => [
        'adminhtml',
        'frontend',
        'Authorization',
        'PHPSESSID',
        'm',
    ],

    'debug_remove_keys' => [
        'token',
        '__utma',
        '__utmz',
        '_bti',
        '_bts',
        '__bxcid',
        '__bxcurr',
        '__bxprev',
        'ajs_group_id',
        'ajs_user_id',
        'ajs_anonymous_id',
        '__bxevents',
    ],

];
