<?php
const DS = DIRECTORY_SEPARATOR;

use Levtechdev\SimPaas\Helper\DateHelper;

if (!function_exists('human_file_size')) {
    /**
     * Returns a human readable file size
     *
     * @param integer $bytes
     * Bytes contains the size of the bytes to convert
     *
     * @param integer $decimals
     * Number of decimal places to be returned
     *
     * @return string a string in human readable format
     *
     * */
    function human_file_size(int $bytes, int $decimals = 2): string
    {
        $sz = 'BKMGTPE';
        $factor = (int)floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $sz[$factor];
    }
}

if (!function_exists('in_arrayi')) {

    /**
     * Checks if a value exists in an array in a case-insensitive manner
     *
     * @param mixed $needle
     *                      The searched value
     *
     * @param       $haystack
     *                      The array
     *
     * @param bool  $strict [optional]
     *                      If set to true type of needle will also be matched
     *
     * @return bool true if needle is found in the array,
     * false otherwise
     */
    function in_arrayi(string $needle, $haystack, bool $strict = false)
    {
        return in_array(strtolower($needle), array_map('strtolower', $haystack), $strict);
    }
}

if (!function_exists('arr_merge_recursive')) {
    function arr_merge_recursive(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => & $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = arr_merge_recursive($merged[$key], $value);
            } else if (is_numeric($key)) {
                if (!in_array($value, $merged)) {
                    $merged[] = $value;
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

if (!function_exists('convert_to_bytes')) {
    /**
     * Convert PHP INI memory limit value to a proper bytes version
     *
     * @param string $size
     *
     * @return int|string
     */
    function convert_to_bytes($size)
    {
        if ('-1' === $size) {
            return -1;
        }

        $size = strtolower($size);
        $max = strtolower(ltrim($size, '+'));
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int)$max;
        }

        switch (substr($size, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}

if (!function_exists('get_constant')) {
    function get_constant($const, $default = null)
    {
        return (defined($const)) ? constant($const) : $default;
    }
}

if (!function_exists('is_rtl')) {
    /**
     * Is RTL
     * Check if there RTL characters (Arabic, Persian, Hebrew)
     * https://gist.github.com/khal3d/4648574
     *
     * @param $string
     *
     * @return bool
     */
    function is_rtl($string)
    {
        $rtlCharsPattern = '/[\x{0590}-\x{05ff}\x{0600}-\x{06ff}]/u';

        return (bool)preg_match($rtlCharsPattern, $string);
    }
}

if (!function_exists('is_hebrew')) {
    function is_hebrew($string): bool
    {
        return is_rtl($string);
    }
}

if (!function_exists('array_keys_r')) {
    function array_keys_r($array): array
    {
        $keys = array_keys($array);

        foreach ($array as $i) {
            if (is_array($i)) {
                $keys = array_merge($keys, array_keys_r($i));
            }
        }

        return $keys;
    }
}

if (!function_exists('debug')) {
    function debug($stringMessage, $processId = null)
    {
        $microtime = explode('.', microtime(true));
        echo sprintf(
            '[%s.%s] [PID: %s]: %s' . PHP_EOL,
            date(DateHelper::DATE_FORMAT),
            $microtime[1] ?? '0',
            $processId != null ? $processId : getmypid(),
            $stringMessage
        );
    }
}

if (!function_exists('is_maintenance')) {
    function is_maintenance(): bool
    {
        return file_exists(constant('MAINTENANCE_FLAG_FILE'));
    }
}

if (!function_exists('is_maintenance_rom')) {
    function is_maintenance_rom(): bool
    {
        return file_exists(constant('MAINTENANCE_ROM_FILE'));
    }
}

// @todo it does not use in IMS, and there is dependency Symfony\Component\Uid\Uuid (symfony/uid)
//if (!function_exists('com_create_guid')) {
//    function com_create_guid()
//    {
//        return (string)Symfony\Component\Uid\Uuid::v4();
//    }
//}

if (!function_exists('as_array_of')) {
    /**
     * to define array of some type in refactorable manner - preserving ::class constant usage as $type
     *
     * @param string $type
     *
     * @return string
     */
    function as_array_of(string $type): string
    {
        return sprintf('%s[]', $type);
    }
}

if (!function_exists('action')) {
    function action(string $class, string $method): string
    {
        return sprintf('%s@%s', $class, $method);
    }
}

if (!function_exists('strip_accents')) {
    /**
     * Strip Accents from the string
     *
     * @param string
     *
     * @return string
     */
    function strip_accents($string): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    }
}

if (!function_exists('offset_sec')) {
    /**
     * Difference between actual and getting value in sec
     *
     * @param string
     *
     * @return string
     */
    function offset_sec($time)
    {
        return microtime(true) - $time;
    }
}

if (!function_exists('get_path_from_url')) {
    /**
     * Get path from url
     *
     * @param string
     *
     * @return string
     */
    function get_path_from_url($url)
    {
        return trim(parse_url($url, PHP_URL_PATH), '/');
    }
}
