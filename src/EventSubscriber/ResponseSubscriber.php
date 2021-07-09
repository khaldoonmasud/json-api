<?php

namespace Drupal\smc_api\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi_include\JsonapiParseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Site\Settings;

/**
 * Class ResponseSubscriber.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The parse interface.
   *
   * @var \Drupal\jsonapi_include\JsonapiParseInterface
   */
  protected $jsonapiInclude;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    $events[KernelEvents::TERMINATE][] = ['onTerminate'];

    return $events;
  }

  /**
   * Set config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function setConfig(ConfigFactoryInterface $config) {
    $this->config = $config;
  }

  /**
   * Set jsonapi parse.
   *
   * @param \Drupal\jsonapi_include\JsonapiParseInterface $jsonapi_include
   *   The parse interface.
   */
  public function setJsonapiInclude(JsonapiParseInterface $jsonapi_include) {
    $this->jsonapiInclude = $jsonapi_include;
  }

  /**
   * Set route match service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The  route match service.
   */
  public function setRouteMatch(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * This method is called the KernelEvents::RESPONSE event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The filter event.
   */
  public function onResponse(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    if (!$this->routeMatch->getRouteObject()) {
      return;
    }

    global $_smc_api_cache_tags;
    if (!empty($_smc_api_cache_tags) && \Drupal::currentUser()->isAnonymous()) {
      $min = min(array_column($_smc_api_cache_tags, 'time'));
      $event->getResponse()->setMaxAge($min);
      $event->getResponse()->setPrivate();
      $date = new \DateTime('now');
      $date->add(new \DateInterval('PT' . $min . 'S'));
      $event->getResponse()->setExpires($date);
    }

    // When user is logged in through open it connect generic service, set the node redirect url.
    $current_path = \Drupal::service('path.current')->getPath();
    if ($current_path == '/openid-connect/generic' && \Drupal::currentUser()->isAuthenticated()) {
      $response = $event->getResponse();
      if ($response->headers->has('location')) {
        // $response->headers->set('location', Settings::get('smc_api_node_url', '') . $response->headers->get('location'));
        $response->headers->set('location', Settings::get('smc_api_node_url', '') . $response->headers->get('location'));
      }
    }
    if ($current_path == '/user/logout' && \Drupal::currentUser()->isAnonymous()) {
      $request = \Drupal::request();
      if ($request->headers->has('referer')) {
        $event->getResponse()->headers->set('location', $request->headers->get('referer'));
      }
    }

    if (isset($this->routeMatch->getRouteObject()->getDefaults()['_jsonapi_resource'])) {
      $response = $event->getResponse();
      if ($response instanceof CacheableResponseInterface) {
        $response->getCacheableMetadata()->addCacheContexts(['url.query_args:jsonapi_include']);
      }
      $need_parse = TRUE;
      if ($this->config->get('jsonapi_include.settings')->get('use_include_query')) {
        $need_parse = !empty($_GET['jsonapi_include']);
      }
      if ($need_parse) {
        $content = $event->getResponse()->getContent();
        if (strpos($content, '{"jsonapi"') === 0) {
          $content = $this->jsonapiInclude->parse($content);
          $event->getResponse()->setContent($content);
        }
      }
    }
  }

  /**
   * This method is called the KernelEvents::TERMINATE event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
   *   The terminate event.
   */
  public function onTerminate(PostResponseEvent $event) {
    global $_smc_api_cache_tags;
    if (!empty($_smc_api_cache_tags)) {
      foreach ($_smc_api_cache_tags as $tag) {
        \Drupal::database()->update('cache_jsonapi_normalizations')->fields(['expire' => time() + $tag['time']])->condition('tags', "%" . $tag['tag'] . "%", 'LIKE')->execute();
      }

    }
  }

}
