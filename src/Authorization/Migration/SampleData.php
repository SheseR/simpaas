<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Authorization\Migration;

use Levtechdev\SimPaas\Authorization\Model\Role;
use Levtechdev\SimPaas\Authorization\Model\User;
use Levtechdev\SimPaas\Authorization\Repository\RoleRepository;
use Levtechdev\SimPaas\Authorization\Repository\RuleRepository;
use Levtechdev\SimPaas\Authorization\Repository\UserRepository;
use Levtechdev\SimPaas\Authorization\ResourceModel\Collection\RoleCollection;
use Levtechdev\SimPaas\Authorization\ResourceModel\Collection\RuleCollection;
use Levtechdev\SimPaas\Database\SampleDataInterface;
use Exception;
use Levtechdev\SimPaas\Exceptions\EntityNotFoundException;
use SimPass\Authorization\ResourceModel\Collection\UserCollection;

/**
 *  * Override this class in your app by Service Provider.
 */
class SampleData implements SampleDataInterface
{
    // Override it
    protected array $roleRulesMap = [
        // --- Full roles --- //
        Role::ROLE_ALL                     => [
            '*' => ['*'],
        ]
    ];

    // Override it
    protected array $userRolesMap = [
        User::CLIENT_ID_ADMIN    => [
            Role::ROLE_ALL
        ],
    ];

    /** @var array */
    protected array $cacheRoleNameRulesIds = [];

    /** @var array */
    protected array $cacheRoleNameRoleId = [];

    public function __construct(
        protected RuleRepository $ruleRepository,
        protected RoleRepository $roleRepository,
        protected UserRepository $userRepository
    ) {
    }

    /**
     * Install sample data: old data will be overridden (updated)
     *
     * @throws Exception
     */
    public function install()
    {
        $this->installRules();
        $this->installRoles();
        $this->installUsers();
    }

    /**
     * @return $this
     * @throws Exception
     */
    protected function installRules(): self
    {
        /** @var RuleCollection $rolesCollection */
        $rulesCollection = $this->ruleRepository->getList([]);

        if ($rulesCollection->count() > 0) {
            $this->ruleRepository->massDelete($rulesCollection);
        }

        foreach ($this->getRoleRulesMap() as $roleName => $rules) {
            foreach ($rules as $endpoint => $methods) {
                $ruleModel = $this->ruleRepository->save([
                    'name'     => $roleName,
                    'endpoint' => $endpoint,
                    'methods'  => $methods
                ]);

                $this->cacheRoleNameRulesIds[$roleName][] = $ruleModel->getId();
            }
        }

        return $this;
    }

    /**
     * @return $this
     *
     * @throws EntityNotFoundException
     */
    protected function installRoles(): self
    {
        /** @var RoleCollection $rolesCollection */
        $rolesCollection = $this->roleRepository->getList([]);

        if ($rolesCollection->count() > 0) {
            $this->roleRepository->massDelete($rolesCollection);
        }

        foreach ($this->getRoleRulesMap() as $roleName => $rules) {
            if (empty($this->cacheRoleNameRulesIds[$roleName])) {
                throw new EntityNotFoundException(sprintf('Entity Rules with name "%s" not found in cache', $roleName));
            }

            $roleModel = $this->roleRepository->save(
                [
                    'name'     => $roleName,
                    'rule_ids' => $this->cacheRoleNameRulesIds[$roleName] ?? []
                ]
            );

            $this->cacheRoleNameRoleId[$roleName] = $roleModel->getId();
        }

        return $this;
    }

    /**
     * @return $this
     *
     * @throws Exception
     */
    protected function installUsers(): self
    {
        /** @var UserCollection $userCollection */
        $userCollection = $this->userRepository->getList([]);

        foreach ($this->getUserRolesMap() as $userClientId => $rolesName) {

            $rolesIds = array_values(array_filter($this->cacheRoleNameRoleId, function ($roleName) use ($rolesName) {
                if (in_array($roleName, $rolesName)) {

                    return true;
                }
            }, ARRAY_FILTER_USE_KEY));

            /** @var User $userModel */
            $userModel = $userCollection->getItemByClientId($userClientId);
            if (empty($userModel->getId())) {
                $userModel->setClientId($userClientId)
                    ->setRoleIds($rolesIds)
                    ->save();

                $userModel->generateToken($userModel->getId(), $userModel->getClientId());
            } else {

                $userModel->setRoleIds($rolesIds);
                $userModel->save();
            }
        }

        $deletedUsers = array_diff($userCollection->getColumnValues('client_id'), array_keys($this->userRolesMap));
        if (!empty($deletedUsers)) {
            foreach ($deletedUsers as $deletedUser) {
                $userModel = $userCollection->getItemByClientId($deletedUser);
                $userModel->delete();
            }
        }

        return $this;
    }

    /**
     * @return array
     *
     * @example [
     *     role_name => [
     *        endpoint => [HTTP_METHODS] or []
     *     ],
     *     Role::ROLE_ALL => [
     *        '*'    => ['*'],
     *    ],
     * ]
     */
    protected function getRoleRulesMap(): array
    {
        return $this->roleRulesMap;
    }

    /**
     * @return array
     * @example [
     *      User:CLIENT_ID_ADMIN => [
     *        Role::ROLE_ALL
     *   ]
     * ]
     */
    protected function getUserRolesMap(): array
    {
        return $this->userRolesMap;
    }
}
