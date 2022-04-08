<?php declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Mysql\Expression;

class LockedAttributesExpression implements ExpressionInterface
{
    const LOCKED_ATTRIBUTE_EXPRESSION            = 'IF(JSON_EXTRACT(locked_attributes, \'$.%1$s\') IS NULL,  VALUES(`%1$s`), %1$s)';
    const LOCKED_ATTRIBUTE_EXPRESSION_FOR_UPDATE = 'IF(JSON_EXTRACT(locked_attributes, \'$.%1$s\') IS NULL, :%1$s, %1$s)';
    /**
     * The value of the expression.
     *
     * @var mixed
     */
    protected mixed $value;

    /** @var string */
    protected string $column;

    /**
     * LockedAttributesExpression constructor.
     *
     * @param string $column
     * @param        $value
     */
    public function __construct(string $column, $value)
    {
        $this->column = $column;
        $this->value = $value;
    }

    /**
     * Get the value of the expression.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
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
        return sprintf(self::LOCKED_ATTRIBUTE_EXPRESSION, $this->getColumn());
    }

    /**
     * @return string
     */
    public function getUpdateExpression(): string
    {
        return sprintf(self::LOCKED_ATTRIBUTE_EXPRESSION_FOR_UPDATE, $this->getColumn());
    }

    /**
     * Get the value of the expression.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getValue();
    }
}
