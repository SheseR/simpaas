<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Authorization\Model;

use Exception;
use UnexpectedValueException;
use Levtechdev\SimPaas\Helper\RandomHash;
use Levtechdev\SimPaas\Authorization\ResourceModel\Role as RoleResourceModel;

/**
 * Override this class in your app by Service Provider. It gives you the possibility to implement new roles via constants
 *
 * @method array getRuleIds()
 * @method Role setRuleIds(array $ruleIds)
 */
class Role extends Base
{
    const ENTITY           = 'auth_role';
    const ENTITY_ID_PREFIX = 'arol_';

    const ROLE_CLI     = 'CLI';
    const ROLE_ALL     = 'role_all';

    public function __construct(
        RoleResourceModel $resourceModel,
        RandomHash $hashHelper,
        private Rule $ruleModel,
        array $data = []
    ) {
        parent::__construct($resourceModel, $hashHelper, $data);
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function createNewObject(array $data = []): static
    {
        return new static($this->getResource(), $this->hashHelper, $this->ruleModel, $data);
    }

    /**
     * Check rules
     *
     * @throws Exception
     */
    protected function validateData(): void
    {
        $ruleIds = $this->getRuleIds();

        if (!empty($ruleIds) && is_array($ruleIds)) {
            if (count($ruleIds) != $this->ruleModel->exists($ruleIds)) {
                throw new UnexpectedValueException('Some of specified rules do not exist');
            }
        } else {
            throw new UnexpectedValueException('No specified rules');
        }
    }

    /**
     * @param string $ruleId
     *
     * @return $this
     */
    public function assignRuleId(string $ruleId): self
    {
        $ruleIds = $this->getRuleIds();
        $ruleIds[] = $ruleId;

        return $this->setRuleIds($ruleIds);
    }
}
