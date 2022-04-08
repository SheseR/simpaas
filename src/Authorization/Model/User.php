<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Authorization\Model;

use Firebase\JWT\JWT;
use Levtechdev\SimPaas\Helper\RandomHash;
use Levtechdev\SimPaas\Authorization\ResourceModel\User as UserResource;

/**
 * @method string getToken()
 * @method string getClientId()
 * @method User setClientId(string $clientId)
 * @method array getRoleIds()
 * @method setToken(string $token)
 * @method User setRoleIds(array $roleIds)
 */
class User extends Base
{
    const ENTITY           = 'auth_user';
    const ENTITY_ID_PREFIX = 'ausr_';

    const USER_CLI     = 'CLI_USER';
    const USER_NO_AUTH = 'NO_AUTH_USER';

    const CLIENT_ID_ADMIN      = 'admin_user';
    const CLIENT_ID_FMS        = 'fms_user';
    const CLIENT_ID_MAGENTO    = 'magento_user';
    const CLIENT_ID_CDMS       = 'cdms_user';
    const CLIENT_ID_OMS        = 'oms_user';
    const CLIENT_ID_PORTAL     = 'portal_user';
    const CLIENT_ID_PORTAL_DEV = 'protal_dev_user';
    const CLIENT_ID_CA         = 'ca_user';
    const CLIENT_ID_YSELFIE    = 'yselfie_user';
    const CLIENT_ID_PIM        = 'pim_user';

    public function __construct(
        UserResource $resourceModel,
        RandomHash $hashHelper,
        private Role $roleModel,
        array $data = []
    ) {
        parent::__construct($resourceModel, $hashHelper, $data);
    }

    /**
     * @param array $data
     *
     * @return $this
     */
     protected function createNewObject(array $data = []): static
     {
         return new static($this->getResource(), $this->hashHelper, $this->roleModel, $data);
     }

    /**
     * @param string|null $userId
     * @param string $clientId
     *
     * @return string
     */
    public function generateToken(?string $userId, string $clientId): string
    {
        return JWT::encode(
            [
                'created_at' => time(),
                'client_id'  => $clientId,
                'uid'        => $userId,
            ],
            env('APP_KEY'),
            'HS256'
        );
    }

    /**
     * Check rules
     *
     * @throws \Exception
     */
    protected function validateData(): void
    {
        $roleIds = $this->getRoleIds();

        if (!empty($roleIds) && is_array($roleIds)) {
            if (count($roleIds) != $this->roleModel->exists($roleIds)) {
                throw new \UnexpectedValueException('Some of specified roles do not exist');
            }
        } else {
            throw new \UnexpectedValueException('No specified roles');
        }
    }

    /**
     * @param string $roleId
     *
     * @return User
     */
    public function assignRoleId(string $roleId): User
    {
        $ruleIds = $this->getRoleIds();
        $ruleIds[] = $roleId;

        return $this->setRoleIds($ruleIds);
    }
}
