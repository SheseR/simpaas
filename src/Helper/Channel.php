<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Helper;

class Channel extends Core
{
    const DEFAULT_CHANNEL = 'chnl_ao_il';

    protected ?string $currentChannel = null;

    /**
     * @param string|null $value
     *
     * @return $this
     */
    public function setChannel(?string $value): self
    {
        $this->currentChannel = $value ?? self::DEFAULT_CHANNEL;

        return $this;
    }

    /**
     * @return string
     */
    public function getChannel(): string
    {
        return $this->currentChannel ?? self::DEFAULT_CHANNEL;
    }
}
