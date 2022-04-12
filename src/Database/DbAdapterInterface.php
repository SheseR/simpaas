<?php

namespace Levtechdev\Simpaas\Database;

interface DbAdapterInterface
{
    const DATABASE_QUERY_LOG_FILE = 'database_queries.log';
    const ERROR_LOG_FILE          = 'db_errors.log';
}