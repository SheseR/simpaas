<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Helper;

class Dimension
{
    const WEIGHT_UNIT_OZ  = 'oz';
    const WEIGHT_UNIT_OZS = 'ozs';
    const WEIGHT_UNIT_ML  = 'ml';

    const WEIGHT_UNIT_KG  = 'kg';
    const WEIGHT_UNIT_KGS = 'kgs';
    const WEIGHT_UNIT_LB  = 'lb';
    const WEIGHT_UNIT_LBS = 'lbs';

    const LENGTH_UNIT_INCH = 'inch';
    const LENGTH_UNIT_IN   = 'in';
    const LENGTH_UNIT_CM   = 'cm';
    const LENGTH_UNIT_M    = 'm';
    const LENGTH_UNIT_MM   = 'mm';

    const LENGTH_WEIGHT_DIVIDER = 6000;

    protected array $weightMultipliers = [
        self::WEIGHT_UNIT_LBS => [
            self::WEIGHT_UNIT_KGS => 0.45359237,
            self::WEIGHT_UNIT_LBS => 1,
            self::WEIGHT_UNIT_OZS => 16
        ],
        self::WEIGHT_UNIT_LB  => [
            self::WEIGHT_UNIT_KGS => 0.45359237,
            self::WEIGHT_UNIT_LBS => 1,
            self::WEIGHT_UNIT_OZS => 16
        ],
        self::WEIGHT_UNIT_KGS => [
            self::WEIGHT_UNIT_KGS => 1,
            self::WEIGHT_UNIT_LBS => 2.20462262,
            self::WEIGHT_UNIT_OZS => 35.274
        ],
        self::WEIGHT_UNIT_KG  => [
            self::WEIGHT_UNIT_KGS => 1,
            self::WEIGHT_UNIT_LBS => 2.20462262,
            self::WEIGHT_UNIT_OZS => 35.274
        ],
        self::WEIGHT_UNIT_OZS => [
            self::WEIGHT_UNIT_KGS => 0.0283495,
            self::WEIGHT_UNIT_LBS => 0.0625,
            self::WEIGHT_UNIT_OZS => 1
        ],
        self::WEIGHT_UNIT_OZ  => [
            self::WEIGHT_UNIT_KGS => 0.0283495,
            self::WEIGHT_UNIT_LBS => 0.0625,
            self::WEIGHT_UNIT_OZS => 1
        ]
    ];

    protected array $lengthMultipliers = [
        self::LENGTH_UNIT_M    => [
            self::LENGTH_UNIT_MM   => 1000,
            self::LENGTH_UNIT_CM   => 100,
            self::LENGTH_UNIT_INCH => 39.3701,
            self::LENGTH_UNIT_IN   => 39.3701,
            self::LENGTH_UNIT_M    => 1
        ],
        self::LENGTH_UNIT_INCH => [
            self::LENGTH_UNIT_MM   => 25.4,
            self::LENGTH_UNIT_CM   => 2.54,
            self::LENGTH_UNIT_INCH => 1,
            self::LENGTH_UNIT_IN   => 1,
            self::LENGTH_UNIT_M    => 0.0254
        ],
        self::LENGTH_UNIT_IN   => [
            self::LENGTH_UNIT_MM   => 25.4,
            self::LENGTH_UNIT_CM   => 2.54,
            self::LENGTH_UNIT_INCH => 1,
            self::LENGTH_UNIT_IN   => 1,
            self::LENGTH_UNIT_M    => 0.0254
        ],
        self::LENGTH_UNIT_CM   => [
            self::LENGTH_UNIT_MM   => 10,
            self::LENGTH_UNIT_CM   => 1,
            self::LENGTH_UNIT_INCH => 0.393700787,
            self::LENGTH_UNIT_IN   => 0.393700787,
            self::LENGTH_UNIT_M    => 0.01
        ],
        self::LENGTH_UNIT_MM   => [
            self::LENGTH_UNIT_MM   => 1,
            self::LENGTH_UNIT_CM   => 0.1,
            self::LENGTH_UNIT_INCH => 0.0393701,
            self::LENGTH_UNIT_IN   => 0.0393701,
            self::LENGTH_UNIT_M    => 0.001,
        ],
    ];

    protected array $weightFluidMultipliers = [
        self::WEIGHT_UNIT_OZ  => [
            self::WEIGHT_UNIT_OZS => 1,
            self::WEIGHT_UNIT_ML  => 29.5735,
        ],
        self::WEIGHT_UNIT_ML  => [
            self::WEIGHT_UNIT_OZS => 0.033814,
            self::WEIGHT_UNIT_ML  => 1,
        ],
    ];

    /**
     * @param $from
     * @param $to
     *
     * @return int|float|null
     */
    public function getWeightMultipliers($from, $to): int|float|null
    {
        return $this->weightMultipliers[$from][$to] ?? null;
    }

    /**
     * @param $from
     * @param $to
     *
     * @return int|float|null
     */
    public function getWeightFluidMultipliers($from, $to): int|float|null
    {
        return $this->weightFluidMultipliers[$from][$to] ?? null;
    }

    /**
     * @param float $length
     * @param string|null $lengthUnit
     *
     * @return float
     */
    public function getConvertedLength(float $length, ?string $lengthUnit): float
    {
        return ($this->lengthMultipliers[$lengthUnit][self::LENGTH_UNIT_CM] ?? 0) * $length;
    }
}
