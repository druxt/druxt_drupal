<?php

namespace Drupal\druxt\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\decoupled_router\EventSubscriber\RouterPathTranslatorSubscriber;
use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\views\Views;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Event subscriber that processes a path translation with the router info.
 */
class ViewsPathTranslatorSubscriber extends RouterPathTranslatorSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onPathTranslation(PathTranslatorEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof CacheableJsonResponse) {
      $this->logger->error('Unable to get the response object for the decoupled router event.');
      return;
    }

    // @todo jsonapi_views is dependency in druxt.info.yml, it is always enabled, can't we eleminate this check?
    if (!$this->moduleHandler->moduleExists('jsonapi_views')) {
      return;
    }

    $path = $event->getPath();
    $path = $this->cleanSubdirInPath($path, $event->getRequest());
    if ($this->languageManager->isMultilingual()) {
      $path = $this->getPathFromAlias($path);
    }

    try {
      $match_info = $this->router->match($path);
    }
    catch (ResourceNotFoundException $exception) {
      // If URL is external, we won't perform checks for content in Drupal,
      // but assume that it's working.
      if (UrlHelper::isExternal($path)) {
        $response->setStatusCode(200);
        $response->setData([
          'resolved' => $path,
        ]);
      }
      // @todo shouldn't there be an else { $response->setStatusCode(404)?
      return;
    }
    catch (MethodNotAllowedException $exception) {
      // @todo Shouldn't this be a 405 not a 403?
      $response->setStatusCode(403);
    }

    $entity_type_manager = $this->container->get('entity_type.manager');
    $views_storage = $entity_type_manager->getStorage('view');
    $view = $views_storage->load($match_info['view_id']);
    $executable = Views::executableFactory()->get($view);
    $executable->setDisplay($match_info['display_id']);

    // Determine langcode.
    $langcode = NULL;
    $language = NULL;
    if ($this->languageManager->isMultilingual()) {
      $destination = parse_url($event->getPath(), PHP_URL_PATH);
      $language_negotiation_url = $this->languageManager->getNegotiator()
        ->getNegotiationMethodInstance('language-url');
      $router_request = Request::create($destination);
      $langcode = $language_negotiation_url->getLangcode($router_request);
      $language = $this->languageManager->getLanguage($langcode);
    }

    $route = $match_info[RouteObjectInterface::ROUTE_OBJECT];
    $resolved_url = Url::fromRoute(
      $route,
      [],
      [
        'absolute' => TRUE,
        'language' => $language
      ]
    )->toString(TRUE);
    $response->addCacheableDependency($resolved_url);

    $is_home_path = $this->resolvedPathIsHomePath($resolved_url->getGeneratedUrl());
    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheContexts(['url.path.is_front'])
    );

    $output = [
      'resolved' => $resolved_url->getGeneratedUrl(),
      'isHomePath' => $is_home_path,
      'view' => [
        'uuid' => $view->get('uuid'),
        'view_id' => $match_info['view_id'],
        'display_id' => $match_info['display_id'],
        'langcode' => $langcode
      ],
      'label' => $executable->getTitle(),
    ];

    // If the route is JSON API, it means that JSON API is installed and its
    // services can be used.
    if ($this->moduleHandler->moduleExists('jsonapi')) {
      $view_type_id = $view->getEntityTypeId();

      /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $rt_repo */
      $rt_repo = $this->container->get('jsonapi.resource_type.repository');
      $rt = $rt_repo->get($view_type_id, $view->bundle());
      $type_name = $rt->getTypeName();
      $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
      $entry_point_url = Url::fromRoute(
        'jsonapi.resource_list',
        [],
        [
          'absolute' => TRUE,
          'language' => $language,
        ]
      )->toString(TRUE);
      $route_name = sprintf('jsonapi.%s.individual', $type_name);
      $individual = Url::fromRoute(
        $route_name,
        [
          static::getEntityRouteParameterName($route_name, $view_type_id) => $view->uuid(),
        ],
        [
          'absolute' => TRUE,
          'language' => $language,
        ]
      )->toString(TRUE);
      $response->addCacheableDependency($entry_point_url);
      $response->addCacheableDependency($individual);

      $output['jsonapi'] = [
        'individual' => $individual->getGeneratedUrl(),
        'resourceName' => $type_name,
        'pathPrefix' => trim($jsonapi_base_path, '/'),
        'basePath' => $jsonapi_base_path,
        'entryPoint' => $entry_point_url->getGeneratedUrl(),
      ];
      $deprecation_message = 'This property has been deprecated and will be removed in the next version of Decoupled Router. Use @alternative instead.';
      $output['meta'] = [
        'deprecated' => [
          //phpcs:disable
          'jsonapi.pathPrefix' => $this->t($deprecation_message, ['@alternative' => 'basePath']),
        ],
      ];
    }

    if ($this->moduleHandler->moduleExists('jsonapi_views')) {
      $parts = [
        'jsonapi_views',
        $match_info['view_id'],
        $match_info['display_id'],
      ];
      $jsonapi_views_route = implode('.', $parts);
      $resolved_jsonapi_views_url = Url::fromRoute(
        $jsonapi_views_route,
        [],
        [
          'absolute' => TRUE,
          'language' => $language
        ]
      )->toString(TRUE);
      $response->addCacheableDependency($resolved_jsonapi_views_url);

      $output['jsonapi_views'] = $resolved_jsonapi_views_url->getGeneratedUrl();
    }

    $response->addCacheableDependency($view);
    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }

}
