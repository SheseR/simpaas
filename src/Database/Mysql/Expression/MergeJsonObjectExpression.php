<?php declare(strict_types=1);

namespace Levtechdev\SimPaas\Database\Mysql\Expression;

class MergeJsonObjectExpression implements ExpressionInterface
{
    const EXPRESSION            = 'JSON_MERGE_PATCH(%s, \'%s\')';
    const EXPRESSION_FOR_UPDATE = 'JSON_MERGE_PATCH(%1$s, :%1$s)';
    /**
     * The value of the expression.
     *
     * @var array
     */
    protected array $values = [];

    /** @var string */
    protected string $column;

    /**
     * MergeJsonExpression constructor.
     *
     * @param string $column
     * @param array  $values
     */
    public function __construct(string $column, array $values = [])
    {
        $this->column = $column;
        $this->values = $values;
    }

    /**
     * @return mixed
     */
    public function getValues(): mixed
    {
        return $this->values;
    }

    /**
     * @return string
     */
    public function getUpdateExpression(): string
    {
        return sprintf(self::EXPRESSION_FOR_UPDATE, $this->getColumn());
    }

    /**
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * @return string
     */
    public function getUpsertExpression(): string
    {
        if (empty($this->values)) {

            return sprintf(self::EXPRESSION, $this->getColumn(), '{}');
        }

        return sprintf(self::EXPRESSION, $this->getColumn(), json_encode($this->getValues()));
    }

    /**
     * Get the value of the expression.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (empty($this->values)) {

            return '{}';
        }

        return json_encode($this->values);
    }
}
