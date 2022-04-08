<?php

namespace Levtechdev\Simpaas\Helper;

/**
 * Class Math
 *
 * @package App\Core\Helper
 */
class Math
{
    const MIN_PERCENTAGE_VALUE = 0;
    const MAX_PERCENTAGE_VALUE = 1;

    /**
     * @param float $value
     *
     * @return float
     */
    public function round(float $value): float
    {
        return round($value, 4);
    }

    /**
     * @param float|int $value
     *
     * @return int
     */
    public function normalize(float|int $value): int
    {
        if ($value <= self::MIN_PERCENTAGE_VALUE) {

            return self::MIN_PERCENTAGE_VALUE;
        }

        if ($value >= self::MAX_PERCENTAGE_VALUE) {

            return self::MAX_PERCENTAGE_VALUE;
        }

        return $value;
    }
}
