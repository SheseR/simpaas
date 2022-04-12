<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database;

class SearchCriteria
{
    const DEFAULT_PAGE = 1;
    const DEFAULT_PAGE_SIZE = 25;
    /**
     * Must be less then 10000 so that max_results_window index setting not to be changed (for ES)
     */
    const MAX_PAGE_SIZE = 5000;

    const SORT_ORDER_ASC = 'asc';
    const SORT_ORDER_DESC = 'desc';

    const FILTER = 'filter';
    const POST_FILTER = 'post_filter';
    const SORT = 'sort';
    const DATA_FIELDS = 'fields';
    const PAGINATION = 'pagination';
    const PREFERENCE = 'ref';
    const FROM = 'from';
    const TO = 'to';
    const SIZE = 'size';
    const VIRTUAL_FACETS = 'virtual_facets';
    const LIMIT = 'limit';

    const INJECTION_POSITIONS = '_product_positions';
    // loaded in searchCriteria handler
    const VIRTUAL_FACET_DATA = 'virtual_facet_data';
    const ATTRIBUTE_VIRTUAL_FACET_NAME_MAP = 'attribute_virtual_facet_name_map';
    /**
     * Operators
     */
    const EQ = 'eq';
    const NEQ = 'ne';
    const LIKE = 'like';
    const IN_OR_LIKES = 'in_or_likes';
    const NOT_LIKE = 'nlike';
    const BETWEEN = 'between';
    const REGEXP = 'regexp';
    const NOT_REGEXP = 'nregexp';
    const EXISTS = 'exists';
    const NOT_EXISTS = 'not_exists';
    const NOT_IN = 'nin';
    const NOT_NULL = 'not_null';
    const IN = 'in';
    const LT = 'lt';
    const GT = 'gt';
    const LTE = 'lte';
    const GTE = 'gte';

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
        self::EQ => '=',
        self::NEQ => '!=',
        self::LIKE => 'like',
        self::NOT_LIKE => 'not like',
        self::BETWEEN => null, // required separate handler
        self::NOT_NULL => null, // required separate handler
        self::REGEXP => 'regexp',
        self::NOT_REGEXP => 'not regexp',
        self::EXISTS => '=',  // check by null
        self::NOT_EXISTS => '!=', // check by null
        self::NOT_IN => 'not in',
        self::IN => 'in',
        self::LT => '<',
        self::GT => '>',
        self::LTE => '<=',
        self::GTE => '>=',
    ];

    const CONDITION_AND = 'and';
    const CONDITION_OR = 'or';
}
