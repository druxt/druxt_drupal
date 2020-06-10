<?php

namespace Drupal\druxt_connect\EventSubscriber;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Drupal\decoupled_router\EventSubscriber\RouterPathTranslatorSubscriber;
use Drupal\decoupled_router\PathTranslatorEvent;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

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
    if (!$this->moduleHandler->moduleExists('jsonapi_views')) {
      return;
    }

    $entity_type_manager = $this->container->get('entity_type.manager');
    $views_storage = $entity_type_manager->getStorage('view');

    $path = $event->getPath();
    $path = $this->cleanSubdirInPath($path, $event->getRequest());
    try {
      $match_info = $this->router->match($path);
    }
    catch (ResourceNotFoundException $exception) {
      // If URL is external, we won't perform checks for content in Drupal,
      // but assume that it's working.
      if (UrlHelper::isExternal($path)) {
        $response->setStatusCode(200);
        $response->setData(array(
          'resolved' => $path,
        ));
      }
      return;
    }
    catch (MethodNotAllowedException $exception) {
      $response->setStatusCode(403);
      return;
    }

    $route = $match_info[RouteObjectInterface::ROUTE_OBJECT];
    $resolved_url = Url::fromRoute($route, [], ['absolute' => TRUE])->toString(TRUE);
    $response->addCacheableDependency($resolved_url);

    $jsonapi_route = implode('.', ['jsonapi_views', $match_info['view_id'], $match_info['display_id']]);
    $resolved_jsonapi_url = Url::fromRoute($jsonapi_route, [], ['absolute' => TRUE])->toString(TRUE);
    $response->addCacheableDependency($resolved_jsonapi_url);

    $is_home_path = $this->resolvedPathIsHomePath($resolved_url->getGeneratedUrl());
    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheContexts(['url.path.is_front'])
    );

    $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
    $entry_point_url = Url::fromRoute('jsonapi.resource_list', [], ['absolute' => TRUE])->toString(TRUE);

    $output = [
      'resolved' => $resolved_url->getGeneratedUrl(),
      'isHomePath' => $is_home_path,
      'view' => [
        'view_id' => $match_info['view_id'],
        'display_id' => $match_info['display_id'],
      ],
      'label' => $match_info['_title'],
      'jsonapi' => [
        'individual' => false,
        'resourceName' => false,
        'basePath' => $jsonapi_base_path,
        'entryPoint' => $entry_point_url->getGeneratedUrl(),
      ],
      'jsonapi_views' => $resolved_jsonapi_url->getGeneratedUrl(),
    ];

    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }
}
