<?php

namespace Levtechdev\Simpaas\Service\Logger\Monolog\Formatter;

/**
 * Encodes whatever record data is passed to it as XML string output
 */
class XmlFormatter extends AbstractFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        return $this->toXml(parent::format($record)) . "\n";
    }

    /**
     * Return the XML string representation of a value
     *
     * @param array $data
     * @param string $rootNodeName
     * @param null|\SimpleXMLElement $xml
     *
     * @return bool|string
     */
    protected function toXml(array $data, string $rootNodeName = 'item', $xml = null): bool|string
    {
        if ($xml === null) {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
        }

        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                // Do not allow numeric only nodes
                $key = "node_" . (string)$key;
            } else {
                // Remove any none alphanumeric symbols from node name
                $key = preg_replace('/[^a-z0-9]/i', '', $key);
            }

            if (is_array($value)) {
                $node = $xml->addChild($key);
                $this->toXml($value, $rootNodeName, $node);
            } else {
                $value = htmlentities($value);
                $xml->addChild($key, $value);
            }
        }

        return $xml->asXML();
    }
}
