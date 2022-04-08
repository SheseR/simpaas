<?php

namespace Levtechdev\Simpaas\Helper;

class StringFilter extends Core
{
    /**
     * Default charset
     */
    const ICONV_CHARSET = 'UTF-8';

    /**
     * Symbol convert table
     *
     * @var array
     */
    protected array $convertTable = [
        '&amp;' => 'and',
        '@' => 'at',
        '©' => 'c',
        '®' => 'r',
        'À' => 'a',
        'Á' => 'a',
        'Â' => 'a',
        'Ä' => 'a',
        'Å' => 'a',
        'Æ' => 'ae',
        'Ç' => 'c',
        'È' => 'e',
        'É' => 'e',
        'Ë' => 'e',
        'Ì' => 'i',
        'Í' => 'i',
        'Î' => 'i',
        'Ï' => 'i',
        'Ò' => 'o',
        'Ó' => 'o',
        'Ô' => 'o',
        'Õ' => 'o',
        'Ö' => 'o',
        'Ø' => 'o',
        'Ù' => 'u',
        'Ú' => 'u',
        'Û' => 'u',
        'Ü' => 'u',
        'Ý' => 'y',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'p',
        'ÿ' => 'y',
        'Ā' => 'a',
        'ā' => 'a',
        'Ă' => 'a',
        'ă' => 'a',
        'Ą' => 'a',
        'ą' => 'a',
        'Ć' => 'c',
        'ć' => 'c',
        'Ĉ' => 'c',
        'ĉ' => 'c',
        'Ċ' => 'c',
        'ċ' => 'c',
        'Č' => 'c',
        'č' => 'c',
        'Ď' => 'd',
        'ď' => 'd',
        'Đ' => 'd',
        'đ' => 'd',
        'Ē' => 'e',
        'ē' => 'e',
        'Ĕ' => 'e',
        'ĕ' => 'e',
        'Ė' => 'e',
        'ė' => 'e',
        'Ę' => 'e',
        'ę' => 'e',
        'Ě' => 'e',
        'ě' => 'e',
        'Ĝ' => 'g',
        'ĝ' => 'g',
        'Ğ' => 'g',
        'ğ' => 'g',
        'Ġ' => 'g',
        'ġ' => 'g',
        'Ģ' => 'g',
        'ģ' => 'g',
        'Ĥ' => 'h',
        'ĥ' => 'h',
        'Ħ' => 'h',
        'ħ' => 'h',
        'Ĩ' => 'i',
        'ĩ' => 'i',
        'Ī' => 'i',
        'ī' => 'i',
        'Ĭ' => 'i',
        'ĭ' => 'i',
        'Į' => 'i',
        'į' => 'i',
        'İ' => 'i',
        'ı' => 'i',
        'Ĳ' => 'ij',
        'ĳ' => 'ij',
        'Ĵ' => 'j',
        'ĵ' => 'j',
        'Ķ' => 'k',
        'ķ' => 'k',
        'ĸ' => 'k',
        'Ĺ' => 'l',
        'ĺ' => 'l',
        'Ļ' => 'l',
        'ļ' => 'l',
        'Ľ' => 'l',
        'ľ' => 'l',
        'Ŀ' => 'l',
        'ŀ' => 'l',
        'Ł' => 'l',
        'ł' => 'l',
        'Ń' => 'n',
        'ń' => 'n',
        'Ņ' => 'n',
        'ņ' => 'n',
        'Ň' => 'n',
        'ň' => 'n',
        'ŉ' => 'n',
        'Ŋ' => 'n',
        'ŋ' => 'n',
        'Ō' => 'o',
        'ō' => 'o',
        'Ŏ' => 'o',
        'ŏ' => 'o',
        'Ő' => 'o',
        'ő' => 'o',
        'Œ' => 'oe',
        'œ' => 'oe',
        'Ŕ' => 'r',
        'ŕ' => 'r',
        'Ŗ' => 'r',
        'ŗ' => 'r',
        'Ř' => 'r',
        'ř' => 'r',
        'Ś' => 's',
        'ś' => 's',
        'Ŝ' => 's',
        'ŝ' => 's',
        'Ş' => 's',
        'ş' => 's',
        'Š' => 's',
        'š' => 's',
        'Ţ' => 't',
        'ţ' => 't',
        'Ť' => 't',
        'ť' => 't',
        'Ŧ' => 't',
        'ŧ' => 't',
        'Ũ' => 'u',
        'ũ' => 'u',
        'Ū' => 'u',
        'ū' => 'u',
        'Ŭ' => 'u',
        'ŭ' => 'u',
        'Ů' => 'u',
        'ů' => 'u',
        'Ű' => 'u',
        'ű' => 'u',
        'Ų' => 'u',
        'ų' => 'u',
        'Ŵ' => 'w',
        'ŵ' => 'w',
        'Ŷ' => 'y',
        'ŷ' => 'y',
        'Ÿ' => 'y',
        'Ź' => 'z',
        'ź' => 'z',
        'Ż' => 'z',
        'ż' => 'z',
        'Ž' => 'z',
        'ž' => 'z',
        'ſ' => 'z',
        'Ə' => 'e',
        'ƒ' => 'f',
        'Ơ' => 'o',
        'ơ' => 'o',
        'Ư' => 'u',
        'ư' => 'u',
        'Ǎ' => 'a',
        'ǎ' => 'a',
        'Ǐ' => 'i',
        'ǐ' => 'i',
        'Ǒ' => 'o',
        'ǒ' => 'o',
        'Ǔ' => 'u',
        'ǔ' => 'u',
        'Ǖ' => 'u',
        'ǖ' => 'u',
        'Ǘ' => 'u',
        'ǘ' => 'u',
        'Ǚ' => 'u',
        'ǚ' => 'u',
        'Ǜ' => 'u',
        'ǜ' => 'u',
        'Ǻ' => 'a',
        'ǻ' => 'a',
        'Ǽ' => 'ae',
        'ǽ' => 'ae',
        'Ǿ' => 'o',
        'ǿ' => 'o',
        'ə' => 'e',
        'Ё' => 'jo',
        'Є' => 'e',
        'І' => 'i',
        'Ї' => 'i',
        'А' => 'a',
        'Б' => 'b',
        'В' => 'v',
        'Г' => 'g',
        'Д' => 'd',
        'Е' => 'e',
        'Ж' => 'zh',
        'З' => 'z',
        'И' => 'i',
        'Й' => 'j',
        'К' => 'k',
        'Л' => 'l',
        'М' => 'm',
        'Н' => 'n',
        'О' => 'o',
        'П' => 'p',
        'Р' => 'r',
        'С' => 's',
        'Т' => 't',
        'У' => 'u',
        'Ф' => 'f',
        'Х' => 'h',
        'Ц' => 'c',
        'Ч' => 'ch',
        'Ш' => 'sh',
        'Щ' => 'sch',
        'Ъ' => '-',
        'Ы' => 'y',
        'Ь' => '-',
        'Э' => 'je',
        'Ю' => 'ju',
        'Я' => 'ja',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'j',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '-',
        'ы' => 'y',
        'ь' => '-',
        'э' => 'je',
        'ю' => 'ju',
        'я' => 'ja',
        'ё' => 'jo',
        'є' => 'e',
        'і' => 'i',
        'ї' => 'i',
        'Ґ' => 'g',
        'ґ' => 'g',
        'א' => 'a',
        'ב' => 'b',
        'ג' => 'g',
        'ד' => 'd',
        'ה' => 'h',
        'ו' => 'v',
        'ז' => 'z',
        'ח' => 'h',
        'ט' => 't',
        'י' => 'i',
        'ך' => 'k',
        'כ' => 'k',
        'ל' => 'l',
        'ם' => 'm',
        'מ' => 'm',
        'ן' => 'n',
        'נ' => 'n',
        'ס' => 's',
        'ע' => 'e',
        'ף' => 'p',
        'פ' => 'p',
        'ץ' => 'C',
        'צ' => 'c',
        'ק' => 'q',
        'ר' => 'r',
        'ש' => 'w',
        'ת' => 't',
        '™' => 'tm',
        'α' => 'a',
        'ά' => 'a',
        'Ά' => 'a',
        'Α' => 'a',
        'β' => 'b',
        'Β' => 'b',
        'γ' => 'g',
        'Γ' => 'g',
        'δ' => 'd',
        'Δ' => 'd',
        'ε' => 'e',
        'έ' => 'e',
        'Ε' => 'e',
        'Έ' => 'e',
        'ζ' => 'z',
        'Ζ' => 'z',
        'η' => 'i',
        'ή' => 'i',
        'Η' => 'i',
        'θ' => 'th',
        'Θ' => 'th',
        'ι' => 'i',
        'ί' => 'i',
        'ϊ' => 'i',
        'ΐ' => 'i',
        'Ι' => 'i',
        'Ί' => 'i',
        'κ' => 'k',
        'Κ' => 'k',
        'λ' => 'l',
        'Λ' => 'l',
        'μ' => 'm',
        'Μ' => 'm',
        'ν' => 'n',
        'Ν' => 'n',
        'ξ' => 'x',
        'Ξ' => 'x',
        'ο' => 'o',
        'ό' => 'o',
        'Ο' => 'o',
        'Ό' => 'o',
        'π' => 'p',
        'Π' => 'p',
        'ρ' => 'r',
        'Ρ' => 'r',
        'σ' => 's',
        'ς' => 's',
        'Σ' => 's',
        'τ' => 't',
        'Τ' => 't',
        'υ' => 'u',
        'ύ' => 'u',
        'Υ' => 'y',
        'Ύ' => 'y',
        'φ' => 'f',
        'Φ' => 'f',
        'χ' => 'ch',
        'Χ' => 'ch',
        'ψ' => 'ps',
        'Ψ' => 'ps',
        'ω' => 'o',
        'ώ' => 'o',
        'Ω' => 'o',
        'Ώ' => 'o',
    ];

    public function filterString(string $string): string
    {
        $string = strtr($string, $this->convertTable);

        return '"libiconv"' == ICONV_IMPL ? iconv(
            self::ICONV_CHARSET,
            'ascii//ignore//translit',
            $string
        ) : $string;
    }

    /**
     * Filter value
     *
     * @param string $string
     * @return string
     */
    public function filterUrl(string $string): string
    {
        $string = preg_replace('#[^+0-9a-z]+#i', '-', $this->filterString($string));
        $string = strtolower($string);

        return trim($string, '-');
    }

    /**
     * Clean non UTF-8 characters
     *
     * @param string $string
     *
     * @return array|false|string|null
     */
    public function cleanString(string $string): array|false|string|null
    {
        return mb_convert_encoding($string, self::ICONV_CHARSET);
    }

    /**
     * Pass through to mb_substr()
     *
     * @param string $string
     * @param int $offset
     * @param ?int $length
     * @return string
     */
    public function substr(string $string, int $offset, int $length = null): string
    {
        $string = $this->cleanString($string);
        if ($length === null) {
            $length = $this->strlen($string) - $offset;
        }

        return mb_substr($string, $offset, $length, self::ICONV_CHARSET);
    }

    /**
     * Retrieve string length using default charset
     *
     * @param string $string
     * @return int
     */
    public function strlen(string $string): int
    {
        return mb_strlen($string, self::ICONV_CHARSET);
    }

    /**
     * Binary-safe variant of strSplit()
     * + option not to break words
     * + option to trim spaces (between each word)
     * + option to set character(s) (pcre pattern) to be considered as words separator
     *
     * @param string $value
     * @param int $length
     * @param bool $keepWords
     * @param bool $trim
     * @param string $wordSeparatorRegex
     * @return string[]
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function split(
        string $value,
        int $length = 1,
        bool $keepWords = false,
        bool $trim = false,
        string $wordSeparatorRegex = '\s'
    ): array {
        $result = [];
        $strLen = $this->strlen($value);
        if (!$strLen || !is_int($length) || $length <= 0) {
            return $result;
        }
        if ($trim) {
            $value = trim(preg_replace('/\s{2,}/siu', ' ', $value));
        }
        // do a usual str_split, but safe for our encoding
        if (!$keepWords || $length < 2) {
            for ($offset = 0; $offset < $strLen; $offset += $length) {
                $result[] = $this->substr($value, $offset, $length);
            }
        } else {
            // split smartly, keeping words
            $split = preg_split('/(' . $wordSeparatorRegex . '+)/siu', $value, null, PREG_SPLIT_DELIM_CAPTURE);
            $index = 0;
            $space = '';
            $spaceLen = 0;
            foreach ($split as $key => $part) {
                if ($trim) {
                    // ignore spaces (even keys)
                    if ($key % 2) {
                        continue;
                    }
                    $space = ' ';
                    $spaceLen = 1;
                }
                if (empty($result[$index])) {
                    $currentLength = 0;
                    $result[$index] = '';
                    $space = '';
                    $spaceLen = 0;
                } else {
                    $currentLength = $this->strlen($result[$index]);
                }
                $partLength = $this->strlen($part);
                // add part to current last element
                if ($currentLength + $spaceLen + $partLength <= $length) {
                    $result[$index] .= $space . $part;
                } elseif ($partLength <= $length) {
                    // add part to new element
                    $index++;
                    $result[$index] = $part;
                } else {
                    // break too long part recursively
                    foreach ($this->split($part, $length, false, $trim, $wordSeparatorRegex) as $subPart) {
                        $index++;
                        $result[$index] = $subPart;
                    }
                }
            }
        }
        // remove last element, if empty
        $count = count($result);
        if ($count) {
            if ($result[$count - 1] === '') {
                unset($result[$count - 1]);
            }
        }
        // remove first element, if empty
        if (isset($result[0]) && $result[0] === '') {
            array_shift($result);
        }
        return $result;
    }

    /**
     * Capitalize first letters and convert separators if needed
     *
     * @param string $str
     * @param string $sourceSeparator
     * @param string $destinationSeparator
     * @return string
     */
    public function upperCaseWords(string $str, string $sourceSeparator = '_', string $destinationSeparator = '_'): string
    {
        return str_replace(' ', $destinationSeparator, ucwords(str_replace($sourceSeparator, ' ', $str)));
    }

    /**
     * @param $str
     *
     * @return null|string|string[]
     */
    public function sanitizeString($str): array|string|null
    {
        return preg_replace(['/>/', '/\s\s+/'], ['', ' '], filter_var(($str), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
    }
}