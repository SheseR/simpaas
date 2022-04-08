<?php declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Mysql;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Levtechdev\Simpaas\Database\Mysql\Expression\ExpressionInterface;
use Levtechdev\Simpaas\Exceptions\EntityNotValidException;
use Levtechdev\Simpaas\Exceptions\MysqlCallbackException;
use Levtechdev\Simpaas\Exceptions\MysqlUpsertException;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Model\AbstractModel;

class MysqlAdapter implements DbAdapterInterface
{
    const LOG_CHANNEL = 'mysql';
    const LOG_FILE    = 'mysql_errors.log';

    const DDL_DESCRIBE     = 1;
    const DDL_CREATE       = 2;
    const DDL_INDEX        = 3;
    const DDL_FOREIGN_KEY  = 4;
    const DDL_CACHE_PREFIX = 'DB_PDO_MYSQL_DDL';
    const DDL_CACHE_TAG    = 'DB_PDO_MYSQL_DDL';

    // PDOException specification https://www.php.net/manual/en/pdo.errorinfo.php
    const MAX_RECONNECTION_ATTEMPTS  = 6;
    const MAX_DEADLOCK_WAIT_ATTEMPTS = 5;
    const CONNECTION_ERRORS          = [
        2006, // SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
        2013, // SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query
    ];
    const DEADLOCK_ERRORS            = [
        1205,
        // SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction
        1213,
        // SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction
    ];

    protected MySqlConnection $connection;

    protected \Monolog\Logger $errorLogger;
    protected \Monolog\Logger $queryLogger;

    /**
     * Tables DDL cache
     */
    protected array $ddlCache = [];

    protected bool $logAllQueries     = true;
    protected bool $isDdlCacheAllowed = true;

    protected array $columnTypes = [];

    /**
     * MysqlAdapter constructor.
     *
     * @param Logger $logger
     *
     * @throws \Exception
     */
    public function __construct(Logger $logger)
    {
        $this->logAllQueries = env('MYSQL_DB_QUERY_DEBUG', false);
        $this->errorLogger = $logger->getLogger(
            static::LOG_CHANNEL,
            base_path(Logger::LOGS_DIR . static::LOG_FILE)
        );

        $this->queryLogger = $logger->getLogger(
            static::LOG_CHANNEL,
            base_path(Logger::LOGS_DIR . DbAdapterInterface::DATABASE_QUERY_LOG_FILE)
        );

        $this->setConnection(app('db')->connection()) ;
    }

    /**
     * @param $connection
     * @return $this
     */
    public function setConnection($connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return MySqlConnection
     */
    public function getConnection(): MySqlConnection
    {
        return $this->connection;
    }

    /**
     * @param        $pdo
     * @param string $sql
     * @param array  $bindings
     *
     * @return mixed
     * @throws MysqlCallbackException
     */
    protected function executeQuery($pdo, string $sql, array $bindings): mixed
    {
        $statement = $pdo->prepare($sql);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $startT = microtime(true);
        $statement = $this->statementExecute($statement, $bindings, function ($statement, $bindings) {
            return $statement->execute($bindings);
        });
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $statement;
    }

    /**
     * @param string $tableName
     *
     * @return array
     */
    public function describeTable(string $tableName): array
    {
        $cacheKey = $tableName;
        $ddl = $this->loadDdlCache($cacheKey, self::DDL_DESCRIBE);
        if ($ddl === false) {
            $ddl = $this->getConnection()
                ->getPdo()
                ->query('DESCRIBE ' . $tableName)
                ->fetchAll();

            $this->saveDdlCache($cacheKey, self::DDL_DESCRIBE, $ddl);
        }

        return $ddl;
    }

    /**
     * Retrieve Id for cache
     *
     * @param string $tableKey
     * @param int    $ddlType
     *
     * @return string
     */
    protected function getDdlCacheId(string $tableKey, int $ddlType): string
    {
        return sprintf('%s_%s_%s', self::DDL_CACHE_PREFIX, $tableKey, $ddlType);
    }

    /**
     * Load DDL data from cache
     * Return false if cache does not exists
     *
     * @param string $tableCacheKey the table cache key
     * @param int    $ddlType       the DDL constant
     *
     * @return bool|array
     */
    public function loadDdlCache(string $tableCacheKey, int $ddlType): bool|array
    {
        if (!$this->isDdlCacheAllowed) {

            return false;
        }
        if (isset($this->ddlCache[$ddlType][$tableCacheKey])) {

            return $this->ddlCache[$ddlType][$tableCacheKey];
        }

        // @todo implement caching for table DDL data (create also command to clean DDL cache)
//        if ($this->cacheAdapter) {
//            $cacheId = $this->getDdlCacheId($tableCacheKey, $ddlType);
//            $data = $this->cacheAdapter->load($cacheId);
//            if ($data !== false) {
//                $data = $this->serializer->unserialize($data);
//                $this->ddlCache[$ddlType][$tableCacheKey] = $data;
//            }
//
//            return $data;
//        }

        return false;
    }

    /**
     * @param string $table
     *
     * @return array
     */
    public function getColumnTypes(string $table): array
    {
        if (!empty($this->columnTypes[$table])) {

            return $this->columnTypes[$table];
        }

        $describeTable = $this->describeTable($table);
        if (empty($describeTable)) {

            return [];
        }

        foreach ($describeTable as $column) {
            $this->columnTypes[$table][$column['Field']] = $column['Type'];
        }

        return $this->columnTypes[$table];
    }

    /**
     * Save DDL data into cache
     *
     * @param string $tableCacheKey
     * @param int    $ddlType
     * @param array  $data
     *
     * @return $this
     */
    public function saveDdlCache(string $tableCacheKey, int $ddlType, array $data): self
    {
        if (!$this->isDdlCacheAllowed) {
            return $this;
        }
        $this->ddlCache[$ddlType][$tableCacheKey] = $data;

        // @todo implement caching for table DDL data (create also command to clean DDL cache)
//        if ($this->cacheAdapter) {
//            $cacheId = $this->getDdlCacheId($tableCacheKey, $ddlType);
//            $data = $this->serializer->serialize($data);
//            $this->cacheAdapter->save($data, $cacheId, [self::DDL_CACHE_TAG]);
//        }

        return $this;
    }

    /**
     * Reset cached DDL data from cache
     * if table name is null - reset all cached DDL data
     *
     * @param string|null $tableName
     *
     * @return $this
     */
    public function resetDdlCache(string $tableName = null): self
    {
        if (!$this->isDdlCacheAllowed) {

            return $this;
        }
        if ($tableName === null) {
            $this->ddlCache = [];
//            if ($this->cacheAdapter) {
//                $this->cacheAdapter->clean(Cache::CLEANING_MODE_MATCHING_TAG, [self::DDL_CACHE_TAG]);
//            }
        } else {
            $cacheKey = $tableName;

            $ddlTypes = [self::DDL_DESCRIBE, self::DDL_CREATE, self::DDL_INDEX, self::DDL_FOREIGN_KEY];
            foreach ($ddlTypes as $ddlType) {
                unset($this->ddlCache[$ddlType][$cacheKey]);
            }

//            if ($this->cacheAdapter) {
//                foreach ($ddlTypes as $ddlType) {
//                    $cacheId = $this->getDdlCacheId($cacheKey, $ddlType);
//                    $this->cacheAdapter->remove($cacheId);
//                }
//            }
        }

        return $this;
    }

    /**
     * Disallow DDL caching
     *
     * @return $this
     */
    public function disallowDdlCache(): self
    {
        $this->isDdlCacheAllowed = false;

        return $this;
    }

    /**
     * Allow DDL caching
     * @return $this
     */
    public function allowDdlCache(): self
    {
        $this->isDdlCacheAllowed = true;

        return $this;
    }

    /**
     * @param string      $table
     * @param string|int  $id
     * @param string $idFieldName
     *
     * @return mixed
     */
    public function selectRecord(string $table, string|int $id, string $idFieldName = AbstractModel::ID_FIELD_NAME): mixed
    {
        $queryBuilder = $this->getConnection()
            ->query()
            ->select()
            ->from($table)
            ->where($idFieldName, '=', $id)
            ->limit(1);

        return $this->getConnection()->selectOne($queryBuilder->toSql(), $queryBuilder->getBindings(), true);
    }

    /**
     * We use unbuffered query. So we cannot use another query in this scope
     * ! Use $statement::closeCursor(), for using buffering queury
     *
     * @param Builder $builder
     *
     * @return bool|\PDOStatement
     */
    public function fetch(Builder $builder): bool|\PDOStatement
    {
        $sql = $builder->toSql();

        $pdo = $this->getConnection()->getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $statement = $pdo->prepare($sql);

        $startT = microtime(true);
        $bindings = $builder->getBindings();
        $statement->execute($builder->getBindings());
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $statement;
    }

    /**
     * @param Builder $builder
     *
     * @return \Generator
     */
    public function cursor(Builder $builder): \Generator
    {
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();

        $startT = microtime(true);
        $res = $this->getConnection()->cursor($sql, $bindings);
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $res;
    }
    /**
     * @param Builder $queryBuilder
     *
     * @return array
     */
    public function select(Builder $queryBuilder): array
    {
        $sql = $queryBuilder->toSql();
        $bindings = $queryBuilder->getBindings();

        $startT = microtime(true);
        $result = $this->getConnection()->select($sql, $bindings, true);
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $result;
    }

    /**
     * @param string $table
     * @param array  $ids
     * @param string $idFieldName
     * @param array  $selectFields
     *
     * @return array
     */
    public function selectRecords(
        string $table,
        array $ids,
        string $idFieldName = AbstractModel::ID_FIELD_NAME,
        array $selectFields = []
    ): array {
        $queryBuilder = $this->getConnection()
            ->query()
            ->select($selectFields)
            ->from($table)
            ->whereIn($idFieldName, $ids);

        return $this->select($queryBuilder);
    }

    /**
     * @param string $table
     * @param array $data
     * @param string|int $id
     * @param string $idFieldName
     * @return int
     * @throws MysqlCallbackException
     */
    public function updateRecord(string $table, array $data, string|int $id, string $idFieldName = AbstractModel::ID_FIELD_NAME): int
    {
        unset($data[$idFieldName]);

        $queryBuilder = $this->getConnection()
            ->query()
            ->from($table)
            ->where($idFieldName, '=', $id)
            ->limit(1);

        return $this->update($queryBuilder, $data);
    }

    /**
     * Supported only whereIN condition
     *
     * $condition=> ['column3' => [value4, value 5]]
     * $data = [
     *      column1 => value 1,
     *      column2 => value 2,
     * ]
     * @param string $table
     * @param array $data
     * @param array $conditions
     * @return int
     *
     * @throws MysqlCallbackException
     */
    public function updateRecords(string $table, array $data, array $conditions): int
    {
        $queryBuilder = $this->getConnection()
            ->query()
            ->from($table);

        // @todo need to be refactored to allow any filters
        if (!empty($conditions)) {
            foreach ($conditions as $field => $values) {
                if (!is_array($values)) {
                    $values = [$values];
                }
                $queryBuilder->whereIn($field, $values);
            }
        }

        return $this->update($queryBuilder, $data);
    }

    /**
     * @param string $table
     * @param array  $bulkData
     * @param string $identifierFieldName
     *
     * @return array
     *
     * @throws MysqlUpsertException
     */
    public function updateRecordsByArray(
        string $table,
        array $bulkData,
        string $identifierFieldName = AbstractModel::ID_FIELD_NAME
    ): array {
        if (empty($bulkData[0])) {

            return [];
        }

        $setExpression = '';
        foreach ($bulkData[0] as $column => $value) {
            if ($column == $identifierFieldName) {
                continue;
            }

            if ($value instanceof ExpressionInterface) {
                $setExpression .= sprintf('`%s` = %s,', $column, $value->getUpdateExpression());

                continue;
            }

            $setExpression .= sprintf('`%s` = :%s,', $column, $column);
        }
        $setExpression = substr($setExpression, 0, -1);
        $conditionExpresion = '`' . $identifierFieldName . '` = :' . $identifierFieldName;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            $setExpression,
            $conditionExpresion
        );

        $results = [];
        $pdo = $this->getConnection()->getPdo();
        try {
            $statement = $pdo->prepare($sql);
            $startT = microtime(true);
            foreach ($bulkData as $data) {
                $results[] = $this->statementExecute($statement, $data, function ($statement, $data) {
                    return $statement->execute($data);
                });
            }
            $this->logQuery($sql, microtime(true) - $startT, $bulkData);
        } catch (\Throwable $e) {
            throw new MysqlUpsertException($e->getMessage());
        }

        return array_filter($results);
    }

    /**
     * @param Builder $queryBuilder
     * @param array   $data
     *
     * @return int
     * @throws MysqlCallbackException
     */
    protected function update(Builder $queryBuilder, array $data): int
    {
        //@todo locked attributes support is not implemented
        $grammar = $queryBuilder->getGrammar();
        $sql = $grammar->compileUpdate($queryBuilder, $data);

        $dataBindings = array_filter($data, function ($value) {
            return !($value instanceof Expression);
        });

        $bindings = $grammar->prepareBindingsForUpdate($queryBuilder->getRawBindings(), $dataBindings);

        $startT = microtime(true);
        $result = $this->statementExecute($sql, $bindings, function ($sql, $bindings) {
            return $this->getConnection()->update($sql, $bindings);
        });
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $result;
    }

    /**
     * Returns first insert ID
     *
     * @param string $table
     * @param array  $data
     * @param bool $insertOrIgnore
     *
     * @return false|int|string
     *
     * @throws MysqlCallbackException
     */
    public function insertArray(string $table, array $data, bool $insertOrIgnore = false): false|int|string
    {
        $queryBuilder = $this->getConnection()
            ->query()
            ->from($table);

        $queryBuilder->setBindings($data);

        $grammar = $queryBuilder->getGrammar();
        if ($insertOrIgnore) {
            $sql = $grammar->compileInsertOrIgnore($queryBuilder, $data);
        } else {
            $sql = $grammar->compileInsert($queryBuilder, $data);
        }

        $startT = microtime(true);
        $bindings = $queryBuilder->getBindings();
        $result = $this->statementExecute($sql, $bindings, function ($sql, $bind) {
            return $this->getConnection()->insert($sql, $bind);
        });

        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        if ($result) {
            /**
             * Warning, see https://www.php.net/manual/en/pdo.lastinsertid.php
             *
             * When using MySQL or MariaDB while inserting multiple rows in a single query
             * (INSERT INTO table (a,b,c) VALUES (1,2,3), (2,3,4), ...) to a table with auto_increment column,
             * PDO::lastInsertId does NOT return the autogenerated id of the last row.
             * Instead, the FIRST generated id is returned.
             * This may very well be explained by taking a look at MySQL and MariaDB's documentation.
             *
             * Also when working with transactions in mysql it will return 0 instead of the insert id.
             */
            return (int)$this->getConnection()->getPdo()->lastInsertId();
        }

        return false;
    }

    /**
     * @param string $table
     * @param array $insertedFieldNames
     * @param string $selectedTable
     * @param array $selectCondition
     * @param array $optionData
     * @return mixed
     * @throws MysqlUpsertException
     */
    public function insertIgnoreFromSelect(
        string $table,
        array $insertedFieldNames,
        string $selectedTable,
        array $selectCondition,
        array $optionData = []
    ) {
        $insertedFieldsAsString = $insertedValuesAsString = '';
        foreach ($insertedFieldNames as $insertedFieldName) {
            $insertedFieldsAsString .= sprintf('`%s`,', $insertedFieldName);
            $insertedValuesAsString .= sprintf('`%s`,', $insertedFieldName);
        }

        foreach ($optionData as $optionFieldName => $optionFieldValue) {
            $insertedFieldsAsString .= sprintf('`%s`,', $optionFieldName);
            $insertedValuesAsString .= sprintf('"%s",', $optionFieldValue);
        }

        $insertedFieldsAsString = rtrim($insertedFieldsAsString, ',');
        $insertedValuesAsString = rtrim($insertedValuesAsString, ',');

        $conditionAsString = '';
        $binds = [];
        foreach ($selectCondition as $field => $values) {
            $currentOperator = '=';
            if (is_array($values)) {
                $currentOperator = 'IN';
                $wrappedValues = '(';
                foreach($values as $value) {
                    $wrappedValues .= '?,';
                    $binds[] = $value;
                }
                $wrappedValues = rtrim($wrappedValues, ',') . ')';
            } elseif ($values === null) {
                $currentOperator = 'IS NULL';
                $wrappedValues = '';
            } else {
                $wrappedValues = '?';
                $binds[] = $values;
            }

            $currentCondition = sprintf('`%s`.`%s` %s %s', $selectedTable, $field, $currentOperator, $wrappedValues);
            if (empty($conditionAsString)) {
                $conditionAsString = $currentCondition;

                continue;
            }

            $conditionAsString = sprintf('%s AND %s', $conditionAsString, $currentCondition);
        }

        $sql= sprintf(
            'INSERT IGNORE INTO `%s` (%s)
                            select %s from `%s`
                                where %s;
           ',
            $table,
            $insertedFieldsAsString,
            $insertedValuesAsString,
            $selectedTable,
            $conditionAsString
        );

        $startT = microtime(true);
        $pdo = $this->getConnection()->getPdo();
        $statement = $pdo->prepare($sql);
        try {
            $results = $this->statementExecute($statement, $binds, function ($statement, $bind) {
                return $statement->execute($bind);
            });
        } catch (\Throwable $e) {
            throw new MysqlUpsertException($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        $this->logQuery($sql, microtime(true) - $startT, $binds);

        return $results;
    }

    /**
     * @param string $table
     * @param array  $data
     * @param array  $updateFields
     *
     * @return bool
     * @throws MysqlUpsertException
     */
    public function insertOnDuplicateKeyUpdate(string $table, array $data, array $updateFields = []): bool
    {
        if (empty($data)) {

            return false;
        }

        $row = reset($data);
        $cols = array_keys($row);
        $colsCount = count($cols);
        $placeHolderPattern = sprintf('(%s)', implode(',', array_fill(0, $colsCount, '?')));

        $placeholders = $bind = [];
        foreach ($data as $item) {
            if (count($cols) != count(array_keys($item))) {
                throw new MysqlUpsertException('Invalid data for insert on duplicate key update, columns are not consistent');
            }

            // values must be sorted according order from first row
            foreach ($cols as $field) {
                if ($item[$field] instanceof ExpressionInterface) {
                    $bind[] = $item[$field]->__toString();

                    continue;
                }

                $bind[] = $item[$field];
            }
            $placeholders[] = $placeHolderPattern;
        }

        $updateValuesString = '';
        if (empty($updateFields)) {
            $updateFields = $cols;
        }

        // prepare update statement
        foreach ($updateFields as $col) {
            if ($row[$col] instanceof ExpressionInterface) {
                $updateValuesString .= sprintf('`%s` = %s,', $col, $row[$col]->getUpsertExpression());

                continue;
            }

            $updateValuesString .= sprintf('`%s` = VALUES(`%s`),', $col, $col);
        }
        $updateValuesString = substr($updateValuesString, 0, -1);

        $sql = sprintf(
            'INSERT INTO %s (`%s`) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $table,
            implode('`, `', $cols),
            implode(', ', $placeholders),
            $updateValuesString
        );

        $startT = microtime(true);
        $pdo = $this->getConnection()->getPdo();
        $statement = $pdo->prepare($sql);

        try {
            $results = $this->statementExecute($statement, $bind, function ($statement, $bind) {
                return $statement->execute($bind);
            });
        } catch (\Throwable $e) {
            throw new MysqlUpsertException($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        $this->logQuery($sql, microtime(true) - $startT, $bind);

        return true;
    }

    /**
     * @param string $table
     * @param string|int $id
     * @param string $fieldName
     *
     * @return int
     * @throws MysqlCallbackException
     */
    public function deleteRecord(string $table, string|int $id, string $fieldName = AbstractModel::ID_FIELD_NAME): int
    {
        $queryBuilder = $this->getConnection()
            ->query()
            ->from($table)
            ->where($fieldName, '=', $id)
            ->limit(1);

        return $this->delete($queryBuilder);
    }

    /**
     * @param Builder $queryBuilder
     *
     * @return int
     *
     * @throws EntityNotValidException
     * @throws MysqlCallbackException
     */
    public function delete(Builder $queryBuilder): int
    {
        $grammar = $queryBuilder->getGrammar();
        $sql = $grammar->compileDelete($queryBuilder);
        $bindings = $queryBuilder->getBindings();

        $startT = microtime(true);
        $result = $this->statementExecute($sql, $bindings, function ($sql, $bindings) {
            return $this->getConnection()->delete($sql, $bindings);
        });
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        return $result;
    }

    /**
     * @param string $table
     * @param array  $ids
     * @param string $fieldName
     * @param bool   $useReadPdo
     *
     * @return int
     */
    public function recordsExist(
        string $table,
        array $ids,
        string $fieldName = AbstractModel::ID_FIELD_NAME,
        bool $useReadPdo = true
    ): int {
        // @todo It is not clear for me
        $queryBuilder = $this->getConnection()->query()->from($table)->whereIn($fieldName, $ids);

        $grammar = $queryBuilder->getGrammar();
        $sql = $grammar->compileExists($queryBuilder);
        $bindings = $queryBuilder->getBindings();

        $startT = microtime(true);
        $results = $this->getConnection()->select($sql, $bindings, $useReadPdo);
        $this->logQuery($sql, microtime(true) - $startT, $bindings);

        // If the results has rows, we will get the row and see if the exists column is a
        // boolean true. If there is no results for this query we will return false as
        // there are no rows for this query at all and we can return that info here.
        if (isset($results[0])) {
            $results = (array)$results[0];

            return (int)$results['exists'];
        }

        return 0;
    }

    /**
     * @param Builder $queryBuilder
     *
     * @return int
     */
    public function countRecords(Builder $queryBuilder): int
    {
        $startT = microtime(true);
        $result = $queryBuilder->count();
        $this->logQuery('COUNT for ' . $queryBuilder->toSql(), microtime(true) - $startT, $queryBuilder->getBindings());

        return $result;
    }

    /**
     * @param Builder $queryBuilder
     * @param string  $fieldName
     * @param string  $function
     *
     * @return array
     */
    public function aggregation(Builder $queryBuilder, string $fieldName, string $function = 'count'): array
    {
        $queryBuilder = $queryBuilder
            ->cloneWithout(['orders', 'groups', 'columns', 'offset', 'limit'])
            ->selectRaw(sprintf('%s, %s(*) as %s', $fieldName, $function, $function))
            ->groupBy($fieldName)
            ->orderBy($fieldName);

        return $this->select($queryBuilder);
    }

    /**
     * @param $query
     * @param $time
     * @param array $bindings
     *
     * @return $this
     */
    public function logQuery($query, $time, array $bindings = []): self
    {
        if (extension_loaded('newrelic')) {
            newrelic_custom_metric('MySQL_DB', (float)($time * 1000));
        }

        if (!$this->logAllQueries) {

            return $this;
        }

        try {
            $e = new \Exception();
            $context = [
                'query_time' => $time,
                'query'      => json_encode($query),
                'bindings'   => json_encode($bindings),
                'file'       => $e->getFile() . ':' . $e->getLine(),
                'trace'      => implode('#', array_slice(explode('#', $e->getTraceAsString()), 2, 10))
            ];

            $this->queryLogger->debug('DB Query', $context);
        } catch (\Throwable $e) {
            // ignore
        }

        return $this;
    }

    /**
     * @param $statement
     * @param $bind
     * @param $callback
     *
     * @return mixed
     *
     * @throws EntityNotValidException
     * @throws MysqlCallbackException
     */
    public function statementExecute($statement, $bind, $callback): mixed
    {
        $detectingPdoCodeErrors = array_merge(self::CONNECTION_ERRORS, self::DEADLOCK_ERRORS);
        $queryAttempts = $connectionAttempts = $deadlockWaitAttempts = 0;

        while (($connectionAttempts < self::MAX_RECONNECTION_ATTEMPTS)
               && ($deadlockWaitAttempts < self::MAX_DEADLOCK_WAIT_ATTEMPTS)
        ) {
            $queryAttempts++;

            try {
                return $result = $callback($statement, $bind);
            } catch (\PDOException $e) {
                // for PDOException the getMessage() method can be empty string
                $message = !empty($e->getMessage()) ? $e->getMessage() : 'PDO Driver Error';
                list(, $pdoCodeError, $pdoMessageError) = $e->errorInfo;

                if ($pdoCodeError === 1062) {
                    throw new EntityNotValidException(sprintf('Entity not valid: %s', $pdoMessageError));
                }

                if (!in_array($pdoCodeError, $detectingPdoCodeErrors)) {

                    throw new MysqlCallbackException($message);
                }

                if (in_array($pdoCodeError, self::CONNECTION_ERRORS)) {
                    $connectionAttempts++;
                    $this->getConnection()->reconnect();
                    $message = sprintf(
                        '%s: connection failure detected. Attempt #%d', $message, $connectionAttempts
                    );
                    $this->errorLogger->critical($message, ['trace' => $e->getTraceAsString()]);
                }

                if (in_array($pdoCodeError, self::DEADLOCK_ERRORS)) {
                    $deadlockWaitAttempts++;
                    $message = sprintf(
                        '%s: deadlock detected. Attempt #%d', $message, $deadlockWaitAttempts
                    );
                    $this->errorLogger->critical($message, ['trace' => $e->getTraceAsString()]);
                }

                sleep(pow(2, $queryAttempts));
            } catch (\Throwable $e) {

                throw new MysqlCallbackException($e->getMessage());
            }
        }

        throw new MysqlCallbackException();
    }
}
