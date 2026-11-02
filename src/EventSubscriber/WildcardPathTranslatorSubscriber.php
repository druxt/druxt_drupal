<?php

declare(strict_types=1);

namespace Drupal\druxt\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
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
  public static function getSubscribedEvents(): array {
    // This answers for whatever is left, so it runs after every other
    // resolver.
    $events[PathTranslatorEvent::TRANSLATE][] = ['onPathTranslation', -100];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function onPathTranslation(PathTranslatorEvent $event): void {
    $response = $event->getResponse();

    $path = $event->getPath();
    $path = $this->cleanSubdirInPath($path, $event->getRequest());
    try {
      $match_info = $this->router->match($path);
    }
    catch (ResourceNotFoundException) {
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
    catch (MethodNotAllowedException) {
      $response->setStatusCode(403);
      return;
    }
    // @phpstan-ignore catch.neverThrown (Drupal's router throws it from parameter conversion, undeclared by the matcher interface.)
    catch (ParamNotConvertedException) {
      // The path names an entity that does not exist, such as `/user/999`.
      // Answering that is the entity resolver's business, and the uncaught
      // exception would have failed the whole request.
      return;
    }

    $route_name = $match_info[RouteObjectInterface::ROUTE_NAME];

    // A path carrying an entity belongs to the entity resolver, which
    // answers with the entity, its uuid and its JSON:API link, or with a 403.
    // The 403 does not stop the event, so this resolver still runs after it,
    // and an answer here would replace that 403.
    foreach ($match_info as $value) {
      if ($value instanceof EntityInterface) {
        return;
      }
    }

    // The route is rebuilt with the values the path gave it, so a route with
    // parameters that are not entities, such as a password reset link,
    // resolves rather than failing for want of them.
    $parameters = isset($match_info['_raw_variables']) ? $match_info['_raw_variables']->all() : [];
    try {
      $resolved_url = Url::fromRoute($route_name, $parameters, ['absolute' => TRUE])->toString(TRUE);
    }
    catch (MissingMandatoryParametersException) {
      // A path this resolver cannot rebuild is not one it can answer for,
      // so it stands aside rather than failing the request.
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
