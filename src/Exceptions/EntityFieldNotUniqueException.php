<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Exceptions;

class EntityFieldNotUniqueException extends \Exception
{
    public function __construct(
        ?string $inputEntityId,
        array $existingEntityIds,
        array $fields,
        $code = 500,
        \Throwable $previous = null
    ) {
        $message = sprintf(
            'Cannot persist input entity "%s" which is not unique by "%s" field(s). Found existing entity(ies) "%s" with the same unique field(s)',
            $inputEntityId ?? '[NEW_ENTITY]',
            implode(',', $fields),
            !empty($existingEntityIds) ? implode(',', $existingEntityIds) : '[COLLECTION]',
        );

        parent::__construct($message, $code, $previous);
    }
}