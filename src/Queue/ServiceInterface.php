<?php

namespace Levtechdev\Simpaas\Queue;

interface ServiceInterface
{
    public function setLogger(\Psr\Log\LoggerInterface $logger): self;

    /**
     * @param array $batchData
     * @return array
     * [
     *     'delivery_tag' => [(required) status => status, (optional) message => array]
     * ]
     */
    public function execute(array $batchData): array;
}