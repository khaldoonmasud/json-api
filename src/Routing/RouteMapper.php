<?php

namespace Drupal\smc_api\Routing;

use Drupal\node\NodeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Sets the 'api_json' format & the internal JSON API route
 * on all requests to JSON API-managed routes.
 */
class RouteMapper implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configuration;

  /**
   * Path prefix.
   *
   * @var array|mixed|null
   */
  protected $pathPrefix;

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a PathAndFormatSetter object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The mapping key value store.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    AliasManagerInterface $alias_manager,
    ConfigFactory $configFactory
  ) {
    $this->httpKernel = $http_kernel;
    $this->aliasManager = $alias_manager;
    $this->configuration = $configFactory;
    $this->pathPrefix = 'api';
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    // Make sure we're only transforming appropriate requests.
    if ($this->isJsonApiRequest($request)) {
      // Get path prefix from configuration.
      $path_prefix = '/' . $this->pathPrefix;

      // Remove "/api" from request alias.
      $alias = substr($request->getPathInfo(), strlen($path_prefix));

      // The path alias manager resolves the alias to an internal path.
      $path = $this->aliasManager->getPathByAlias($alias);

      $redirectJson = $this->getRedirectJson($request, $path);
      if (!empty($redirectJson)) {
        return new JsonResponse($redirectJson);
      }
      $request = $this->transform($request, $path, $alias, $path_prefix);
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Format redirect JSON.
   *
   * @param array $redirect
   *   Values for JSON data.
   *
   * @return array
   *   Fromatted JSON array.
   */
  private function formatRedirectJson(array $redirect) {
    $redirect_response = [];
    if (!empty($redirect)) {
      $redirect = array_shift($redirect);
      $redirect_uri = $redirect->getRedirect();
      $redirect_uri = str_replace('internal:', '', $redirect_uri);
      $redirect_uri = $redirect_uri['uri'];
      $alias = \Drupal::service('path.alias_manager')->getAliasByPath($redirect_uri);
      $redirect_response = [
        'redirect' => [
          'to' => $alias,
          'from' => $redirect->getSourcePathWithQuery(),
          'status' => $redirect->getStatusCode(),
        ],
      ];
    }
    return $redirect_response;
  }

  /**
   * Redirect response..
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $path
   *   Source path to find the redirect if any.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The current response.
   */
  protected function getRedirectJson(Request $request, string $path) {
    $redirect_repository = \Drupal::service('redirect.repository');
    $path = ltrim($path, '/');
    $redirect = $redirect_repository->findBySourcePath($path);
    return $this->formatRedirectJson($redirect);
  }

  /**
   * Checks whether the current request is a JSON API request.
   *
   * Inspects:
   * - possible conflict with default JSON API urls
   * - request path
   * - 'Accept' request header value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   Whether the current request is a JSON API request.
   */
  protected function isJsonApiRequest(Request $request) {

    // Don't touch "original" JSON-API-route requests, only handle requests on.
    // Configured path prefix routes and check if the 'Accept' header includes.
    // the-JSON API MIME type.
    return strpos($request->getPathInfo(), '/jsonapi/') === FALSE && $this->pathPrefixApplies($request);

  }

  /**
   * Modifies the request to act as an alias to a JSON-API route request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $path
   *   URL path.
   * @param string $alias
   *   URL alias.
   * @param string $path_prefix
   *   Path Prefix.
   *
   * @return \Symfony\Component\HttpFoundation\Request|static
   *   The transformed request object
   */
  protected function transform(
    Request $request,
    string $path,
    string $alias,
    string $path_prefix
  ) {
    // Handle special case of front page url w/ no node path.
    if ($path === '/' && $alias === '/') {
      $path = $this->configuration->get('system.site')->get('page.front');
    }

    $page_found = FALSE;
    if (preg_match('/^\/node\/(\d+)/', $path, $matches) && isset($matches[1])) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($matches[1]);
      if ($node instanceof NodeInterface) {
        $path = '/jsonapi/node/' . $node->getType() . '/' . $node->uuid();
        $page_found = TRUE;
      }
    }

    if (!$page_found) {
      // Make JSON API return 404 error code.
      $path = '/jsonapi/node/page/' . str_replace('/', '-', $path);
    }

    // Replace immutable request with modified clone.
    $request = $this->duplicateRequest($request, $path_prefix, $alias, $path);

    return $request;
  }

  /**
   * Check for path prefix.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if path info have slash in the path.
   */
  protected function pathPrefixApplies(Request $request) {
    return strpos($request->getPathInfo(), '/' . $this->pathPrefix) === 0;
  }

  /**
   * Duplicate request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The original request.
   * @param string $path_prefix
   *   The configured path prefix.
   * @param string $alias
   *   The resolved path alias.
   * @param string $path
   *   The resolved internal path.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The duplicated and modified request
   */
  protected function duplicateRequest(
    Request $request,
    $path_prefix,
    $alias,
    $path
  ) {
    // Replace REQUEST_URI in request server parameters.
    $server_parameter_bag = $request->server->all();

    // Remove _format query string.
    $server_parameter_bag['REQUEST_URI'] = str_replace('_format=api_json', '', $server_parameter_bag['REQUEST_URI']);
    $server_parameter_bag['REQUEST_URI'] = str_replace('?&', '?', $server_parameter_bag['REQUEST_URI']);
    $request->query->remove('_format');

    $server_parameter_bag['REQUEST_URI'] = str_replace($path_prefix . $alias,
      $path,
      $request->getRequestUri());

    // Clone immutable request with new server paramters.
    return $request->duplicate(NULL, NULL, NULL, NULL, NULL,
      $server_parameter_bag);
  }

}
