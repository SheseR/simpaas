<?php

namespace Levtechdev\SimPaas\Service\Logger\Monolog\Formatter;

/**
 * Encodes whatever record data is passed to it as php print_r() output
 *
 */
class PrintrFormatter extends AbstractFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return $this->toPrintr(parent::format($record)) . "\n";
    }

    /**
     * Return the php print_r() representation of a value
     *
     * @param  mixed             $data
     * @return string
     */
    protected function toPrintr($data)
    {
        return print_r($data, true);
    }
}
