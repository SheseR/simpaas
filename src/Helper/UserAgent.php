<?php

namespace Levtechdev\Simpaas\Helper;

class UserAgent
{
    // General token that says the browser is Mozilla compatible,
    // and is common to almost every browser today.
    const MOZILLA = 'Mozilla/5.0 ';

    /**
     * Processors by Arch.
     */
    public $processors = [
        'lin' => ['i686', 'x86_64'],
        'mac' => ['Intel', 'PPC', 'U; Intel', 'U; PPC'],
        'win' => ['foo']
    ];

    /**
     * Browsers
     *
     * Weighting is based on market share to determine frequency.
     */
    public $browsers = [
        34 => [
            89 => ['chrome', 'win'],
            9  => ['chrome', 'mac'],
            2  => ['chrome', 'lin']
        ],
        32 => [
            100 => ['iexplorer', 'win']
        ],
        25 => [
            83 => ['firefox', 'win'],
            16 => ['firefox', 'mac'],
            1  => ['firefox', 'lin']
        ],
        7 => [
            95 => ['safari', 'mac'],
            4  => ['safari', 'win'],
            1  => ['safari', 'lin']
        ],
        2 => [
            91 => ['opera', 'win'],
            6  => ['opera', 'lin'],
            3  => ['opera', 'mac']
        ]
    ];

    /**
     * List of Lanuge Culture Codes (ISO 639-1)
     *
     * @see: http://msdn.microsoft.com/en-gb/library/ee825488(v=cs.20).aspx
     */
    public $languages = [
        'af-ZA', 'ar-AE', 'ar-BH', 'ar-DZ', 'ar-EG', 'ar-IQ', 'ar-JO', 'ar-KW', 'ar-LB',
        'ar-LY', 'ar-MA', 'ar-OM', 'ar-QA', 'ar-SA', 'ar-SY', 'ar-TN', 'ar-YE', 'be-BY',
        'bg-BG', 'ca-ES', 'cs-CZ', 'Cy-az-AZ', 'Cy-sr-SP', 'Cy-uz-UZ', 'da-DK', 'de-AT',
        'de-CH', 'de-DE', 'de-LI', 'de-LU', 'div-MV', 'el-GR', 'en-AU', 'en-BZ', 'en-CA',
        'en-CB', 'en-GB', 'en-IE', 'en-JM', 'en-NZ', 'en-PH', 'en-TT', 'en-US', 'en-ZA',
        'en-ZW', 'es-AR', 'es-BO', 'es-CL', 'es-CO',  'es-CR', 'es-DO', 'es-EC', 'es-ES',
        'es-GT', 'es-HN', 'es-MX', 'es-NI', 'es-PA', 'es-PE', 'es-PR', 'es-PY', 'es-SV',
        'es-UY', 'es-VE', 'et-EE', 'eu-ES', 'fa-IR', 'fi-FI', 'fo-FO', 'fr-BE', 'fr-CA',
        'fr-CH', 'fr-FR', 'fr-LU', 'fr-MC', 'gl-ES', 'gu-IN', 'he-IL', 'hi-IN', 'hr-HR',
        'hu-HU', 'hy-AM', 'id-ID', 'is-IS', 'it-CH', 'it-IT', 'ja-JP', 'ka-GE', 'kk-KZ',
        'kn-IN', 'kok-IN', 'ko-KR', 'ky-KZ', 'Lt-az-AZ', 'lt-LT', 'Lt-sr-SP', 'Lt-uz-UZ',
        'lv-LV', 'mk-MK', 'mn-MN', 'mr-IN', 'ms-BN', 'ms-MY', 'nb-NO', 'nl-BE', 'nl-NL',
        'nn-NO', 'pa-IN', 'pl-PL', 'pt-BR', 'pt-PT', 'ro-RO', 'ru-RU', 'sa-IN', 'sk-SK',
        'sl-SI', 'sq-AL', 'sv-FI', 'sv-SE', 'sw-KE', 'syr-SY', 'ta-IN', 'te-IN', 'th-TH',
        'tr-TR', 'tt-RU', 'uk-UA', 'ur-PK', 'vi-VN', 'zh-CHS', 'zh-CHT', 'zh-CN', 'zh-HK',
        'zh-MO', 'zh-SG', 'zh-TW',
    ];

    /**
     * Generate Device Platform
     *
     * Uses a random result with a weighting related to frequencies.
     * @throws \Exception
     */
    public function generatePlatform()
    {
        $rand = mt_rand(1, 100);
        $sum = 0;

        foreach ($this->browsers as $share => $freq_os) {
            $sum += $share;

            if ($rand <= $sum) {
                $rand = mt_rand(1, 100);
                $sum = 0;

                foreach ($freq_os as $share => $choice) {
                    $sum += $share;

                    if ($rand <= $sum) {

                        return $choice;
                    }
                }
            }
        }

        throw new \Exception('Sum of $browsers frequency is not 100.');
    }

    /**
     * @param $array
     *
     * @return mixed
     */
    protected function arrayRandom($array)
    {
        $i = array_rand($array, 1);

        return $array[$i];
    }

    /**
     * @param array $lang
     *
     * @return mixed
     */
    protected function getLanguage($lang = [])
    {
        return $this->arrayRandom(empty($lang) ? $this->languages : $lang);
    }

    /**
     * @param $os
     *
     * @return mixed
     */
    protected function getProcessor($os)
    {
        return $this->arrayRandom($this->processors[$os]);
    }

    /**
     * @return string
     */
    protected function getVersionNt()
    {
        // Win2k (5.0) to Win 7 (6.1).
        return mt_rand(5, 6) . '.' . mt_rand(0, 1);
    }

    /**
     * @return string
     */
    protected function getVersionOsx()
    {
        return '10_' . mt_rand(5, 7) . '_' . mt_rand(0, 9);
    }

    /**
     * @return string
     */
    protected function getVersionWebkit()
    {
        return mt_rand(531, 536) . mt_rand(0, 2);
    }

    /**
     * @return string
     */
    protected function getVersionChrome()
    {
        return mt_rand(13, 15) . '.0.' . mt_rand(800, 899) . '.0';
    }

    /**
     * @return string
     */
    protected function getVersionGecko()
    {
        return mt_rand(17, 31) . '.0';
    }

    /**
     * @return string
     */
    protected function getVersionIe()
    {
        return mt_rand(7, 9) . '.0';
    }

    /**
     * @return string
     */
    protected function getVersionTrident()
    {
        // IE8 (4.0) to IE11 (7.0).
        return mt_rand(4, 7) . '.0';
    }

    /**
     * @return string
     */
    protected function getVersionNet()
    {
        // generic .NET Framework common language run time (CLR) version numbers.
        $frameworks = [
            '2.0.50727',
            '3.0.4506',
            '3.5.30729',
        ];

        $rev = '.' . mt_rand(26, 648);

        return $this->arrayRandom($frameworks) . $rev;
    }

    /**
     * @return string
     */
    protected function getVersionSafari()
    {
        if (mt_rand(0, 1) == 0) {
            $ver = mt_rand(4, 5) . '.' . mt_rand(0, 1);
        } else {
            $ver = mt_rand(4, 5) . '.0.' . mt_rand(1, 5);
        }

        return $ver;
    }

    /**
     * @return string
     */
    protected function getVersionOpera()
    {
        return mt_rand(15, 19) . '.0.' . mt_rand(1147, 1284) . mt_rand(49, 100);
    }

    /**
     * Opera
     *
     * @see: http://dev.opera.com/blog/opera-user-agent-strings-opera-15-and-beyond/
     *
     * @param $arch
     *
     * @return string
     */
    public function opera($arch)
    {
        $opera = ' OPR/' . $this->getVersionOpera();

        // WebKit Rendering Engine (WebKit = Backend, Safari = Frontend).
        $engine = $this->getVersionWebkit();
        $webkit = ' AppleWebKit/' . $engine . ' (KHTML, like Gecko)';
        $chrome = ' Chrome/' . $this->getVersionChrome();
        $safari = ' Safari/' . $engine;

        switch ($arch) {
            case 'lin':

                return '(X11; Linux {proc}) ' . $webkit . $chrome . $safari . $opera;
            case 'mac':
                $osx = $this->getVersionOsx();

                return '(Macintosh; U; {proc} Mac OS X ' . $osx . ')' . $webkit . $chrome . $safari . $opera;
            case 'win':
                // fall through.
            default:
                $nt = $this->getVersionNt();

                return '(Windows NT ' . $nt . '; WOW64) ' . $webkit . $chrome . $safari . $opera;
        }
    }

    /**
     * Safari
     *
     * @param $arch
     *
     * @return string
     */
    public function safari($arch)
    {
        $version = ' Version/' . $this->getVersionSafari();

        // WebKit Rendering Engine (WebKit = Backend, Safari = Frontend).
        $engine = $this->getVersionWebkit();
        $webkit = ' AppleWebKit/' . $engine . ' (KHTML, like Gecko)';
        $safari = ' Safari/' . $engine;

        switch ($arch) {
            case 'mac':
                $osx = $this->getVersionOsx();

                return '(Macintosh; U; {proc} Mac OS X ' . $osx . '; {lang})' . $webkit . $version . $safari;
            case 'win':
                // fall through.
            default:
                $nt = $this->getVersionNt();

                return '(Windows; U; Windows NT ' . $nt . ')' . $webkit . $version . $safari;
        }
    }

    /**
     * Internet Explorer
     *
     * @see: http://msdn.microsoft.com/en-gb/library/ms537503(v=vs.85).aspx
     *
     * @param $arch
     *
     * @return string
     */
    public function iexplorer($arch)
    {
        $nt = $this->getVersionNt();
        $ie = $this->getVersionIe();
        $trident = $this->getVersionTrident();
        $net = $this->getVersionNet();

        return '(compatible'
            . '; MSIE ' . $ie
            . '; Windows NT ' . $nt
            . '; WOW64' // A 32-bit version of Internet Explorer is running on a 64-bit processor.
            . '; Trident/' . $trident
            . '; .NET CLR ' . $net
            . ')';
    }

    /**
     * Firefox User-Agent
     *
     * @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Gecko_user_agent_string_reference
     *
     * @param $arch
     *
     * @return string
     */
    public function firefox($arch)
    {
        // The release version of Gecko.
        $gecko = $this->getVersionGecko();

        // On desktop, the gecko trail is fixed.
        $trail = '20100101';

        $release = 'rv:' . $gecko;
        $version = 'Gecko/' . $trail . ' Firefox/' . $gecko;

        switch ($arch) {
            case 'lin':

                return '(X11; Linux {proc}; ' . $release . ') ' . $version;
            case 'mac':
                $osx = $this->getVersionOsx();

                return '(Macintosh; {proc} Mac OS X ' . $osx . '; ' . $release . ') ' . $version;
            case 'win':
                // fall through.
            default:
                $nt = $this->getVersionNt();

                return '(Windows NT ' . $nt . '; {lang}; ' . $release . ') ' . $version;
        }
    }

    /**
     * @param $arch
     *
     * @return string
     */
    public function chrome($arch)
    {
        $chrome = ' Chrome/' . $this->getVersionChrome();

        // WebKit Rendering Engine (WebKit = Backend, Safari = Frontend).
        $engine = $this->getVersionWebkit();
        $webkit = ' AppleWebKit/' . $engine . ' (KHTML, like Gecko)';
        $safari = ' Safari/' . $engine;

        switch ($arch) {
            case 'lin':

                return '(X11; Linux {proc}) ' . $webkit . $chrome . $safari;
            case 'mac':
                $osx = $this->getVersionOsx();

                return '(Macintosh; U; {proc} Mac OS X ' . $osx . ')' . $webkit . $chrome . $safari;
            case 'win':
                // fall through.
            default:
                $nt = $this->getVersionNt();

                return '(Windows NT ' . $nt . ') ' . $webkit . $chrome . $safari;
        }
    }

    /**
     * @param string[] $lang
     * @return string|string[]
     * @throws \Exception
     */
    public function random($lang = ['en-US'])
    {
        list($browser, $os) = $this->generatePlatform();

        return $this->generate($browser, $os, $lang);
    }

    /**
     * @param string $browser
     * @param string $os
     * @param string[] $lang
     * @return string|string[]
     */
    public function generate($browser = 'chrome', $os = 'win', $lang = ['en-US'])
    {
        $ua = self::MOZILLA . $this->$browser($os);

        $tags = [
            '{proc}' => $this->getProcessor($os),
            '{lang}' => $this->getLanguage($lang),
        ];

        $ua = str_replace(array_keys($tags), array_values($tags), $ua);

        return $ua;
    }
}
