<?php

namespace Levtechdev\Simpaas\Authorization\Model;

use Levtechdev\Simpaas\Helper\RandomHash;
use Levtechdev\Simpaas\Authorization\ResourceModel\Rule as RuleResource;

class Rule extends Base
{
    const ENTITY = 'auth_rule';
    const ENTITY_ID_PREFIX = 'arul_';

    /**
     * Rule constructor.
     *
     * @param RuleResource $resourceModel
     * @param RandomHash                                    $hashHelper
     * @param array                                         $data
     */
    public function __construct(
        RuleResource $resourceModel,
        RandomHash $hashHelper,
        array $data = []
    ) {
        parent::__construct($resourceModel, $hashHelper, $data);
    }
}