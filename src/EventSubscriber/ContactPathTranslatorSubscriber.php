<?php

namespace Drupal\druxt\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
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
class ContactPathTranslatorSubscriber extends RouterPathTranslatorSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onPathTranslation(PathTranslatorEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof CacheableJsonResponse) {
      $this->logger->error('Unable to get the response object for the decoupled router event.');
      return;
    }
    if (!$this->moduleHandler->moduleExists('contact')) {
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

    if ($match_info['_route'] !== 'contact.site_page') {
      return;
    }

    $config = $this->configFactory->get('contact.settings');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $contact_storage = $entity_type_manager->getStorage('contact_form');
    $contact_form = $contact_storage->load($config->get('default_form'));

    $route = $match_info[RouteObjectInterface::ROUTE_OBJECT];
    $resolved_url = Url::fromRoute($route, [], ['absolute' => TRUE])->toString(TRUE);
    $response->addCacheableDependency($resolved_url);

    $is_home_path = $this->resolvedPathIsHomePath($resolved_url->getGeneratedUrl());
    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheContexts(['url.path.is_front'])
    );

    $output = [
      'resolved' => $resolved_url->getGeneratedUrl(),
      'isHomePath' => $is_home_path,
      'entity' => [
        'canonical' => $resolved_url->getGeneratedUrl(),
        'type' => $contact_form->getEntityTypeId(),
        'bundle' => $contact_form->bundle(),
        'id' => $contact_form->id(),
        'uuid' => $contact_form->get('uuid'),
      ],
      'label' => $match_info['_title'],
    ];

    // If the route is JSON API, it means that JSON API is installed and its
    // services can be used.
    if ($this->moduleHandler->moduleExists('jsonapi')) {
      $contact_form_type_id = $contact_form->getEntityTypeId();

      /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $rt_repo */
      $rt_repo = $this->container->get('jsonapi.resource_type.repository');
      $rt = $rt_repo->get($contact_form_type_id, $contact_form->bundle());
      $type_name = $rt->getTypeName();
      $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
      $entry_point_url = Url::fromRoute('jsonapi.resource_list', [], ['absolute' => TRUE])->toString(TRUE);
      $route_name = sprintf('jsonapi.%s.individual', $type_name);
      $individual = Url::fromRoute(
        $route_name,
        [
          static::getEntityRouteParameterName($route_name, $contact_form_type_id) => $contact_form->uuid(),
        ],
        ['absolute' => TRUE]
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

    $response->addCacheableDependency($contact_form);
    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }

}
