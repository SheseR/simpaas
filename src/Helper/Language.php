<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Helper;

class Language extends Core
{
    const LANG_EN = 'en';
    const LANG_HE = 'he';
    const DEFAULT_LANG = self::LANG_EN;

    const SUPPORTED_LANGUAGES = [
        self::LANG_EN => self::LANG_EN,
        self::LANG_HE => self::LANG_HE,
    ];

    /**
     * @var null|string
     */
    protected ?string $currentLanguage = null;

    /**
     * Set current language mode for further data representation in API responses when dealing with language fields
     * Method called from language middleware
     *
     * @param string|null $language
     *
     * @return $this
     */
    public function setLanguage(?string $language): self
    {
        if (empty($language) || $language == '{lang}') {

            return $this;
        }

        $this->currentLanguage = self::SUPPORTED_LANGUAGES[$language] ?? self::DEFAULT_LANG;

        return $this;
    }

    /**
     * Get current data representing language
     *
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->currentLanguage;
    }
}