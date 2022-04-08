<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Authorization\Helper;

use Levtechdev\Simpaas\Authorization\Model\User;
use Levtechdev\Simpaas\Authorization\Repository\RoleRepository;
use Levtechdev\Simpaas\Authorization\Repository\UserRepository;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Helper\Core;
use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\UserCollection;

class Auth extends Core
{
    /** @var User  */
    private User $user;

    public function __construct(
        protected UserRepository $userRepository,
        protected RoleRepository $roleRepository
    ) {

    }

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return $this->getUser()->getId() ?? User::USER_CLI;
    }

    /**
     * @param User $user
     *
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        if (empty($this->user)) {
            $this->user = $this->userRepository->getDataModel()->factoryCreate();
        }

        return $this->user;
    }

    /**
     * @param string $clientId
     *
     * @return $this
     *
     * @throws EntityNotFoundException
     */
    public function initUserByClientId(string $clientId): self
    {
        if ($clientId == User::CLIENT_ID_ADMIN) {
            $user = $this->userRepository->getDataModel()->factoryCreate();
            $user->setData([
                'client_id' => User::CLIENT_ID_ADMIN,
                'id'        => User::CLIENT_ID_ADMIN
            ]);
            $this->setUser($user);

            return $this;
        }

       /** @var UserCollection $userCollection */
        $userCollection = $this->userRepository->getList(['client_id' => ['eq' => $clientId]])->load();

        if ($userCollection->isEmpty()) {
            throw new EntityNotFoundException(sprintf('User %s was not found', $clientId));
        }

        $this->setUser($userCollection->current());

        return $this;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function initUserById(string $id): self
    {
        /** @var User $user */
        $user = $this->userRepository->getById($id);
        $this->setUser($user);

        return $this;
    }

    /**
     * @return bool
     */
    public function isClientAdmin(): bool
    {
        return $this->getUser()->getClientId() === User::CLIENT_ID_ADMIN;
    }

    /**
     * @return bool
     */
    public function isClientCdms(): bool
    {
        return $this->getUser()->getClientId() === User::CLIENT_ID_CDMS;
    }

    /**
     * @return bool
     */
    public function isClientMagento(): bool
    {
        return $this->getUser()->getClientId() === User::CLIENT_ID_MAGENTO;
    }

    /**
     * @return bool
     */
    public function isClientFms(): bool
    {
        return $this->getUser()->getClientId() === User::CLIENT_ID_FMS;
    }

    /**
     * @return bool
     */
    public function isClientPim(): bool
    {
        return $this->getUser()->getClientId() === User::CLIENT_ID_PIM;
    }

    /**
     * @return string
     */
    public function getUserClientId(): string
    {
        return $this->getUser()->getClientId();
    }
}
