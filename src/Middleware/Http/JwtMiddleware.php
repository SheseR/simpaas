<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Middleware\Http;

use Closure;
use DomainException;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;
use Firebase\JWT\{JWT, BeforeValidException, ExpiredException, SignatureInvalidException};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Levtechdev\Simpaas\Authorization\Helper\Auth;
use Levtechdev\Simpaas\Authorization\Model\Rule;
use Levtechdev\Simpaas\Authorization\Model\User;
use Levtechdev\Simpaas\Authorization\Repository\RoleRepository;
use Levtechdev\Simpaas\Authorization\Repository\RuleRepository;
use Levtechdev\Simpaas\Authorization\Repository\UserRepository;
use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\RoleCollection;
use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\RuleCollection;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Exceptions\InvalidAppEnvironmentException;
use Levtechdev\Simpaas\Exceptions\NotAuthenticatedException;
use Levtechdev\Simpaas\Exceptions\NotAuthorizedException;

class JwtMiddleware
{
    public const NAME = 'jwt';

    const ROUTE_PATTERN_PARAMS = [
        'id',
        'gid',
        'fileId',
        'product_id'
    ];

    const ROUTE_ARGUMENT_PATTERN = '~{%s}~';

    /**
     * JwtMiddleware constructor.
     *
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param RuleRepository $ruleRepository
     * @param Auth           $authHelper
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected RoleRepository $roleRepository,
        protected RuleRepository $ruleRepository,
        protected Auth $authHelper
    ) {

    }

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return JsonResponse|mixed
     *
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                throw new NotAuthenticatedException('Authorization token has not been provided');
            }

            $appKey = env('APP_KEY');
            if (!$appKey) {
                throw new InvalidAppEnvironmentException('App key is not defined');
            }

            $credentials = JWT::decode($token, $appKey, ['HS256']);

            if (empty($credentials->uid)) {
                throw new NotAuthenticatedException();
            }

            if (extension_loaded('newrelic')) {
                newrelic_add_custom_parameter('api_user_id', $credentials->uid);
            }
            /** @var User $userModel */
            $userModel = $this->userRepository->getById($credentials->uid);

            if ($userModel->getToken() !== $token) {
                throw new NotAuthenticatedException();
            }
            if (extension_loaded('newrelic')) {
                newrelic_add_custom_parameter('api_client_id', $userModel->getClientId());
            }

            $roleIds = $userModel->getRoleIds();

            /** @var RoleCollection $roles */
            $roles = $this->roleRepository->getList([
                    'ids' => $roleIds
                ]
            );

            $ruleIds = [];
            foreach ($roles as $role) {
                $ruleIds = array_merge($ruleIds, $role->getRuleIds());
            }

            $rules = $this->ruleRepository->getList([
                    'ids' => $ruleIds
                ]
            );

            $access = false;
            $requestMethod = strtolower($request->method());
            $requestUri = $this->setApiVersionToEndpoint(strtolower($request->path()));

            $mergedRules = $this->mergeRules($rules);
            foreach ($mergedRules as $endpoint => $methods) {
                $endpointCheck = $methodCheck = false;
                $allowedMethods = array_map('strtolower', $methods);
                $allowedEndpoint = strtolower($endpoint);
                if ($allowedEndpoint !== '*') {
                    $allowedEndpoint = $this->setApiVersionToEndpoint($allowedEndpoint);
                }

                /** Start section: This section for endpoints like products/{id} */
                foreach (self::ROUTE_PATTERN_PARAMS as $param) {
                    $requestGetParam = $request->route($param);
                    if (empty($requestGetParam)) {

                        continue;
                    }

                    $requestGetParam = strtolower($requestGetParam);
                    $pattern = sprintf(self::ROUTE_ARGUMENT_PATTERN, $param);
                    if (preg_match($pattern, $allowedEndpoint) && $requestGetParam) {
                        $allowedEndpoint = preg_replace($pattern, $requestGetParam, $allowedEndpoint);
                    }
                }
                /** End section */

                if ($allowedEndpoint === '*') {
                    $endpointCheck = true;
                } else if ($requestUri === $allowedEndpoint) {
                    $endpointCheck = true;
                }

                if (!empty($allowedMethods)) {
                    if ($allowedMethods[0] === '*') {
                        $methodCheck = true;
                    } else if (in_array($requestMethod, $allowedMethods, true)) {
                        $methodCheck = true;
                    }
                }

                if ($endpointCheck && $methodCheck) {
                    $access = true;
                    break;
                }
            }

            if (!$access) {
                throw new NotAuthorizedException();
            }
        } catch (ExpiredException | EntityNotFoundException $e) {
            throw new NotAuthorizedException('Token Expired');
        } catch (SignatureInvalidException | DomainException  $e) {
            throw new NotAuthorizedException('Token signature is invalid');
        } catch (UnexpectedValueException | InvalidArgumentException | BeforeValidException $e) {
            throw new NotAuthorizedException();
        } catch (Throwable $e) {
            throw $e;
        }

        $request->setUserResolver(function () use ($userModel) {
            return $userModel;
        });
        $this->authHelper->setUser($userModel);

        return $next($request);
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    protected function setApiVersionToEndpoint($endpoint): string
    {
        $apiVersion = config('global.api_version');
        if (substr($endpoint, 0, strlen($apiVersion)) !== $apiVersion) {
            $endpoint = $apiVersion . DS . $endpoint;
        }

        return $endpoint;
    }

    /**
     * @param RuleCollection $ruleCollection
     *
     * @return array
     */
    protected function mergeRules(RuleCollection $ruleCollection): array
    {
        $mergedRules = [];
        /** @var Rule $rule */
        foreach ($ruleCollection as $rule) {
            $endpoint = $rule->getEndpoint();
            $methods = $rule->getMethods();

            if (key_exists($endpoint, $mergedRules)) {
                if (in_array('*', $mergedRules[$endpoint])) {
                    continue;
                }

                if (in_array('*', $methods)) {
                    $mergedRules[$endpoint] = ['*'];
                    continue;
                }

                $mergedRules[$endpoint] = array_unique(array_merge($mergedRules[$endpoint], $methods));

                continue;
            }

            $mergedRules[$endpoint] = $methods;
        }

        return $mergedRules;
    }
}
