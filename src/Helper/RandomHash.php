<?php

namespace Levtechdev\SimPaas\Helper;

class RandomHash extends Core
{
    const DEFAULT_ALPHABET_NAME  = 'default';
    const ALPHA_CAPITALIZED_ONLY = 'alpha_capitalized';
    const NUMBERS_ONLY           = 'numbers';

    /** @var array */
    protected array $alphabets = [];

    /**
     * Set custom range of symbols to generate random string
     *
     * @param string $name
     * @param string $alphabet
     */
    public function setAlphabet(string $name, string $alphabet): void
    {
        $this->alphabets[$name] = [
            'alphabet' => $alphabet,
            'length'   => strlen($alphabet)
        ];
    }

    /**
     * @param string $name
     * @return array
     */
    public function getAlphabet(string $name): array
    {
        return $this->alphabets[$name] ?? [];
    }

    /**
     * @return $this
     */
    protected function setupDefaultAlphabets(): self
    {
        if (empty($this->alphabets[self::DEFAULT_ALPHABET_NAME]['alphabet'])) {
            $this->setAlphabet(
                self::DEFAULT_ALPHABET_NAME,
                implode(range('a', 'z'))
                . implode(range('A', 'Z'))
                . implode(range(0, 9))
            );
        }

        if (empty($this->alphabets[self::ALPHA_CAPITALIZED_ONLY]['alphabet'])) {
            $this->setAlphabet(
                self::ALPHA_CAPITALIZED_ONLY, implode(range('A', 'Z'))
            );
        }

        if (empty($this->alphabets[self::NUMBERS_ONLY]['alphabet'])) {
            $this->setAlphabet(
                self::NUMBERS_ONLY, implode(range(0, 9))
            );
        }

        return $this;
    }

    /**
     * Generate and return the random string of specified length using specified alphabet of symbols
     *
     * @param int    $length
     * @param string $prefix
     * @param string $alphabetName
     *
     * @return string
     */
    public function generate(int $length, string $prefix = '', string $alphabetName = self::DEFAULT_ALPHABET_NAME): string
    {
        $this->setupDefaultAlphabets();

        if (empty($this->alphabets[$alphabetName]['alphabet'])) {
            throw new \InvalidArgumentException('Specified alphabet does not exist');
        }

        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $randomKey = $this->getRandomInteger(0, $this->alphabets[$alphabetName]['length']);
            $token .= $this->alphabets[$alphabetName]['alphabet'][$randomKey];
        }

        return sprintf('%s%s', $prefix, $token);
    }

    /**
     * Get random number
     *
     * @param int $min
     * @param int $max
     *
     * @return int
     */
    protected function getRandomInteger(int $min, int $max): int
    {
        $range = ($max - $min);

        if ($range < 0) {
            // Not so random...
            return $min;
        }

        $log = log($range, 2);

        // Length in bytes.
        $bytes = (int)($log / 8) + 1;

        // Length in bits.
        $bits = (int)$log + 1;

        // Set all lower bits to 1.
        $filter = (int)(1 << $bits) - 1;

        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));

            // Discard irrelevant bits.
            $rnd = $rnd & $filter;
        } while ($rnd >= $range);

        return ($min + $rnd);
    }
}