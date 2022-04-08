<?php

namespace Levtechdev\Simpaas\Console\Command\Management;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Generator;
use Levtechdev\Simpaas\Authorization\Repository\RoleRepository;
use Levtechdev\Simpaas\Authorization\Repository\RuleRepository;
use Levtechdev\Simpaas\Authorization\Repository\UserRepository;

class SwaggerCommand extends Command
{
    const SCAN_PATH           = 'app';
    const PATH                = 'public' . DS . 'swagger' . DS;
    const SWAGGER_SCHEMA_FILE = 'api.json';
    const FORMAT              = 'json'; // json|yaml

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'swagger:generate-doc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate IMS Swagger API documentation (into "./public/swagger/api.json" file)';

    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;

    /**
     * @var RoleRepository
     */
    private RoleRepository $roleRepository;

    /**
     * @var RuleRepository
     */
    private RuleRepository $ruleRepository;

    /**
     * @var array
     */
    private array $pathMap;

    /**
     * Swagger constructor.
     *
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param RuleRepository $ruleRepository
     */
    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        RuleRepository $ruleRepository
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->ruleRepository = $ruleRepository;

        $this->pathMap = $this->getPathMap();

        parent::__construct();
    }

    /**
     * For testing import products
     */
    public function handle()
    {
        $openApi = Generator::scan([base_path(self::SCAN_PATH)]);
        $this->enrichPathDescriptionByClientAccess($openApi);

        $openApi->saveAs(base_path(self::PATH . self::SWAGGER_SCHEMA_FILE), self::FORMAT);

        $this->info('Swagger API documentation created successfully');
    }

    /**
     * @return array $pathMap['endpoint']['method'] = ['client_id_1', ..., 'client_id_N']
     */
    protected function getPathMap(): array
    {
        $pathMap = [];

        $roleIds = $userRoles = [];
        foreach ($this->userRepository->getList([]) as $user) {
            foreach ($user->getRoleIds() as $roleId) {
                $userRoles[$roleId][] = $user->getClientId();
            }

            $roleIds = array_merge($roleIds, $user->getRoleIds());
        }

        $ruleIds = $clientRoles = [];
        foreach ($this->roleRepository->getList(['ids' => $roleIds]) as $role) {
            $ruleIds = array_merge($ruleIds, $role->getRuleIds());

            if (isset($userRoles[$role->getId()])) {
                $clientRoles = array_merge(
                    $clientRoles,
                    array_fill_keys($role->getRuleIds(), $userRoles[$role->getId()])
                );
            }
        }

        foreach ($this->ruleRepository->getList(['ids' => $ruleIds]) as $rule) {
            $endpoint = $rule->getEndpoint();
            $clients = $clientRoles[$rule->getId()] ?? ['N/A'];

            if ($endpoint === '*') {
                $clients = ['All users'];
            }

            foreach ($rule->getMethods() as $method) {
                if (isset($pathMap[$endpoint][$method])) {
                    $pathMap[$endpoint][$method] = array_merge($pathMap[$endpoint][$method], $clients);

                    continue;
                }

                $pathMap[$endpoint][$method] = $clients;
            }
        }

        return $pathMap;
    }

    /**
     * @param OpenApi $openApi
     */
    private function enrichPathDescriptionByClientAccess(OpenApi $openApi): void
    {
        if (empty($openApi->paths)) {

            return;
        }

        foreach ($openApi->paths as $path) {
            if ($this->isOperationAvailable($path->get)) {
                $path->get->description .= $this->getAccessForClients($path->path, Request::METHOD_GET);
            }

            if ($this->isOperationAvailable($path->put)) {
                $path->put->description .= $this->getAccessForClients($path->path, Request::METHOD_PUT);
            }

            if ($this->isOperationAvailable($path->post)) {
                $path->post->description .= $this->getAccessForClients($path->path, Request::METHOD_POST);
            }

            if ($this->isOperationAvailable($path->delete)) {
                $path->delete->description .= $this->getAccessForClients($path->path, Request::METHOD_DELETE);
            }
        }
    }

    /**
     * @param Operation|string $operation
     *
     * @return bool
     */
    private function isOperationAvailable($operation): bool
    {
        return $operation instanceof Operation && $operation->deprecated !== true;
    }

    /**
     * @param string $apiPath
     * @param string $apiMethod
     *
     * @return string
     */
    private function getAccessForClients(string $apiPath, string $apiMethod): string
    {
        $apiPath = ltrim($apiPath, '/');
        $method = '*'; // all methods

        if (!isset($this->pathMap[$apiPath])) {
            $this->warn(sprintf('OpenApi path %s has been skipped. Path not found in role rules map.', $apiPath));

            return '';
        }

        // primarily OpenApi method
        if (isset($this->pathMap[$apiPath][$apiMethod])) {
            $method = $apiMethod;
        }

        if (isset($this->pathMap[$apiPath][$method])) {

            return sprintf(
                '<br><b>Access for</b>: %s',
                implode(', ', $this->pathMap[$apiPath][$method])
            );
        }

        return '';
    }
}
