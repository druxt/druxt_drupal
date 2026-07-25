<?php

declare(strict_types=1);

namespace Drupal\druxt\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\decoupled_router\EventSubscriber\RouterPathTranslatorSubscriber;
use Drupal\decoupled_router\PathTranslatorEvent;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Event subscriber that processes a path translation with the router info.
 */
class WildcardPathTranslatorSubscriber extends RouterPathTranslatorSubscriber {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function onPathTranslation(PathTranslatorEvent $event): void {
    $response = $event->getResponse();
    if (!$response instanceof CacheableJsonResponse) {
      $this->logger->error('Unable to get the response object for the decoupled router event.');
      return;
    }

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
        $response->setData([
          'resolved' => $path,
        ]);
      }
      return;
    }
    catch (MethodNotAllowedException $exception) {
      $response->setStatusCode(403);
      return;
    }

    $route_name = $match_info[RouteObjectInterface::ROUTE_NAME];
    try {
      $resolved_url = Url::fromRoute($route_name, [], ['absolute' => TRUE])->toString(TRUE);
    }
    catch (MissingMandatoryParametersException $exception) {
      $response->setData([
        'message' => 'Unable to build route URL.',
        'details' => $exception->getMessage(),
      ]);
      $response->setStatusCode(500);
      return;
    }
    $response->addCacheableDependency($resolved_url);

    $is_home_path = $this->resolvedPathIsHomePath($resolved_url->getGeneratedUrl());
    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheContexts(['url.path.is_front'])
    );

    $output = [
      'resolved' => $resolved_url->getGeneratedUrl(),
      'isHomePath' => $is_home_path,
      'label' => $match_info['_title'],
      'context' => $match_info,
    ];

    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }

}
