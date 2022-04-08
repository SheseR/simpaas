<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Helper;

class Price
{
    const DECIMAL_PRECISION = 4;
    const MIN_BASE_PRICE    = 1.00; // USD

    /**
     * @param array equal $product->getPrice() e.g. $prices[$priceName => $priceValue]
     *
     * @return array
     */
    public function roundPrices(array $prices): array
    {
        $roundedPrices = [];
        foreach ($prices as $priceName => $value) {
            $roundedPrices[$priceName] = $this->dropDecimals($value);
        }

        return $roundedPrices;
    }

    /**
     * Drop decimals from price
     *
     * @param float $value
     * @param int $precision quantity symbols after point
     *
     * @return float
     */
    public function dropDecimals(float $value, int $precision = self::DECIMAL_PRECISION): float
    {
        $precision = pow(10, $precision);

        return (float)(floor($value * $precision) / $precision);
    }

    /**
     * @param float $base
     * @param float $percent
     *
     * @return float
     */
    public function increaseSumByPercent(float $base, float $percent): float
    {
        return $base + ($base * $percent / 100);
    }

    /**
     * @param float $base
     * @param float $percent
     *
     * @return float
     */
    public function decreaseSumByPercent(float $base, float $percent): float
    {
        return $base - ($base * $percent / 100);
    }
}
