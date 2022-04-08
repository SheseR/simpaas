<?php

namespace Levtechdev\Simpaas\Repository\Mysql;

use Levtechdev\Simpaas\Collection\AbstractCollection;
use Levtechdev\Simpaas\Collection\Mysql\AbstractMysqlCollection;
use Levtechdev\Simpaas\Exceptions\NotImplementedException;
use Levtechdev\Simpaas\Repository\AbstractRepository;
use Levtechdev\Simpaas\Exceptions\MysqlUpsertException;
use Levtechdev\Simpaas\Validation\ValidationErrorsTrait;

class AbstractMysqlRepository extends AbstractRepository
{
    protected const ERROR_MESSAGE_MAX_LENGTH = 50;
    use ValidationErrorsTrait;

    public function __construct(AbstractMysqlCollection $collection)
    {
        parent::__construct($collection);
    }

    public function massSave(array $data): AbstractCollection|AbstractMysqlCollection
    {
        $this->cleanValidationErrors();

        /** @var AbstractMysqlCollection $collection */
        $collection = $this->getCollection();
        $connection = $collection->getAdapter()->getConnection();
        try {
            // pre-generate essential data
            $collection->bulkBeforeUpsert($data);

            // Query #1
            $this->validateBulkData($data);

            if (empty($data)) {
                // report invalid data
                $this->massSaveAfter($collection);

                return $collection;
            }

            $connection->beginTransaction();

            /**
             * Bulk insert or update
             * Query #2, #3
             */
            // @todo resulted collection doesn't contain autoincrement DB ids, but they are not used anywhere in API
            $collection = $collection->bulkUpsert($data);

            $this->massSaveAfter($collection);

            $connection->commit();
        } catch (\Throwable $e) {
            try {
                $connection->rollBack();
            } catch (\Throwable $e) {
                //
            }

            if ($e instanceof MysqlUpsertException) {

                throw $e;
            }

            $this->generateValidationErrorForBatch($data, $e->getMessage());

            // report errors
            $this->massSaveAfter($collection);
        }

        return $collection;
    }

    /**
     * @param AbstractCollection $collection
     *
     * @return AbstractCollection
     * @throws NotImplementedException
     */
    public function massDelete(AbstractCollection $collection): AbstractCollection
    {
        // TODO: Implement massDelete() method.
        throw new NotImplementedException();
    }

    /**
     * @param AbstractMysqlCollection $collection
     *
     * @return AbstractMysqlCollection
     */
    protected function massSaveAfter(AbstractMysqlCollection $collection): AbstractMysqlCollection
    {
        return $collection;
    }

    /**
     * @param array $batch
     * @param string $errorMessage
     * @return $this
     */
    protected function generateValidationErrorForBatch(array $batch, string $errorMessage): self
    {
        foreach ($batch as $index => $item) {
            $this->addValidationError([
                'id' => $item['id'] ?? null,
                'message'     => strtok(wordwrap($errorMessage, static::ERROR_MESSAGE_MAX_LENGTH, "...\n"), "\n")
            ], $item['id'] ?? null);
        }

        return $this;
    }

    /**
     * Validate mass save input data
     *
     * No valid items are unset from input array, return validation errors
     *
     * @param array $data
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function validateBulkData(array &$data): array
    {
        $this->cleanValidationErrors();

        $errorData = [];

        if (empty(array_filter($data))) {
            throw new \InvalidArgumentException('Data is missing');
        }

        $this->setValidationErrors($errorData);

        return $errorData;
    }
}