<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database;

class SearchCriteria
{
    const DEFAULT_PAGE      = 1;
    const DEFAULT_PAGE_SIZE = 25;
    const SORT_ORDER_ASC    = 'asc';
    const SORT_ORDER_DESC   = 'desc';

    const FILTER      = 'filter';
    const SORT        = 'sort';
    const DATA_FIELDS = 'fields';
    const PAGINATION  = 'pagination';
    const PREFERENCE  = 'ref';
    const LIMIT       = 'limit';

    const INJECTION_POSITIONS = '_product_positions';

    /**
     * Operators
     */
    const EQ         = 'eq';
    const NEQ        = 'ne';
    const LIKE       = 'like';
    const NOT_LIKE   = 'nlike';
    const BETWEEN    = 'between';
    const REGEXP     = 'regexp';
    const NOT_REGEXP = 'nregexp';
    const EXISTS     = 'exists';
    const NOT_EXISTS = 'not_exists';
    const NOT_IN     = 'nin';
    const NOT_NULL   = 'not_null';
    const IN         = 'in';
    const LT         = 'lt';
    const GT         = 'gt';
    const LTE        = 'lte';
    const GTE        = 'gte';

    const SUPPORTED_OPERATORS = [
        self::EQ,
        self::NEQ,
        self::LIKE,
        self::NOT_LIKE,
        self::NOT_NULL,
        self::BETWEEN,
        self::REGEXP,
        self::NOT_REGEXP,
        self::EXISTS,
        self::NOT_EXISTS,
        self::NOT_IN,
        self::IN,
        self::LT,
        self::GT,
        self::LTE,
        self::GTE,
    ];

    const OPERATORS_PDO_MAP = [
        self::EQ         => '=',
        self::NEQ        => '!=',
        self::LIKE       => 'like',
        self::NOT_LIKE   => 'not like',
        self::BETWEEN    => null, // required separate handler
        self::NOT_NULL   => null, // required separate handler
        self::REGEXP     => 'regexp',
        self::NOT_REGEXP => 'not regexp',
        self::EXISTS     => '=',  // check by null
        self::NOT_EXISTS => '!=', // check by null
        self::NOT_IN     => 'not in',
        self::IN         => 'in',
        self::LT         => '<',
        self::GT         => '>',
        self::LTE        => '<=',
        self::GTE        => '>=',
    ];

    const CONDITION_AND = 'and';
    const CONDITION_OR  = 'or';
}
