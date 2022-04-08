<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Mysql\Expression;

/**
 * Interface ExpressionInterface
 *
 * @package App\Core\Service\Db\Adapter\Mysql\Expression
 */
interface ExpressionInterface
{
    /**
     * @return string
     */
    public function getUpsertExpression(): string;

    /**
     * @return string
     */
    public function getUpdateExpression():string;
    /**
     * @return string
     */
    public function __toString(): string;
}
