<?php

namespace Drupal\druxt\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\path_alias\AliasManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * The base class for Decoupled Router PathTranslatorSubscribers.
 */
class PathTranslatorBase implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The canonical URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $canonicalUrl;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The route entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Route match info.
   *
   * @var array
   */
  protected $matchInfo;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The resolved URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $resolvedUrl;

  /**
   * The router.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $router;

  /**
   * RouterPathTranslatorSubscriber constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $router
   *   The router.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The alias manager.
   */
  public function __construct(
    ContainerInterface $container,
    LoggerInterface $logger,
    UrlMatcherInterface $router,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    AliasManagerInterface $aliasManager
  ) {
    $this->container = $container;
    $this->logger = $logger;
    $this->router = $router;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->aliasManager = $aliasManager;
  }

  /**
   * Get the underlying entity and the type of ID param enhancer for the routes.
   *
   * @param array $match_info
   *   The router match info.
   *
   * @return array
   *   The pair of \Drupal\Core\Entity\EntityInterface and bool with the
   *   underlying entity and the info weather or not it uses UUID for the param
   *   enhancement. It also returns the name of the parameter under which the
   *   entity lives in the route ('node' vs 'entity').
   */
  protected function findEntityAndKeys(array $match_info) {
    return [];
  }

  /**
   * An array of dependencies for the PathTranslatorSubscriber.
   *
   * @return array
   *   Array of dependencies.
   */
  public function getDependencies() {
    return [];
  }

  /**
   * Processes a path translation request.
   *
   * @param \Drupal\decoupled_router\PathTranslatorEvent $event
   *   The Path Translator event.
   */
  public function onPathTranslation(PathTranslatorEvent $event) {
    // Ensure a valid response object.
    $response = $event->getResponse();
    if (!$response instanceof CacheableJsonResponse) {
      $this->logger->error('Unable to get the response object for the decoupled router event.');
      return;
    }

    // Check module dependencies.
    $dependencies = $this->getDependencies();
    foreach ($dependencies as $dependency) {
      if (!$this->moduleHandler->moduleExists($dependency)) {
        return;
      }
    }

    // Find matching route for queried path.
    $path = $event->getPath();
    $path = $this->cleanSubdirInPath($path, $event->getRequest());
    try {
      $this->matchInfo = $this->router->match($path);
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

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    /** @var bool $param_uses_uuid */
    list(
      $this->entity,
      $param_uses_uuid,
      $route_parameter_entity_key
    ) = $this->findEntityAndKeys($this->matchInfo);

    // Return if no entity found, allowing for another subscriber to resolve the
    // route.
    if (!$this->entity) {
      return;
    }
    $response->addCacheableDependency($this->entity);

    // Check that user has view access for the entity.
    $can_view = $this->entity->access('view', NULL, TRUE);
    if (!$can_view->isAllowed()) {
      $response->setData([
        'message' => 'Access denied for entity.',
        'details' => 'This user does not have access to view the resolved entity. Please authenticate and try again.',
      ]);
      $response->setStatusCode(403);
      $response->addCacheableDependency($can_view);
      return;
    }

    // Get Canonical URL.
    try {
      $this->canonicalUrl = $this->getCanonicalUrl($this->entity);
      $response->addCacheableDependency($this->canonicalUrl);
    }
    catch (EntityMalformedException $e) {
      $response->setData([
        'message' => 'Unable to build entity URL.',
        'details' => 'A valid entity was found but it was impossible to generate a valid canonical URL for it.',
      ]);
      $response->setStatusCode(500);
      watchdog_exception('decoupled_router', $e);
      return;
    }

    // Get Resolved URL.
    $entity_params = [];
    if ($route_parameter_entity_key) {
      $entity_param = $param_uses_uuid ? $this->entity->id() : $this->entity->uuid();
      $entity_params[$route_parameter_entity_key] = $entity_param;
    }
    $this->resolvedUrl = Url::fromRoute($this->matchInfo[RouteObjectInterface::ROUTE_NAME], $entity_params, ['absolute' => TRUE])->toString(TRUE);
    $response->addCacheableDependency($this->resolvedUrl);

    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheContexts(['url.path.is_front'])
    );
    $response->addCacheableDependency($this->entity->access('view label', NULL, TRUE));

    $output = $this->getJsonOutput();

    // If the route is JSON API, it means that JSON API is installed and its
    // services can be used.
    if ($this->moduleHandler->moduleExists('jsonapi')) {
      $entity_type_id = $this->entity->getEntityTypeId();
      /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $rt_repo */
      $rt_repo = $this->container->get('jsonapi.resource_type.repository');
      $rt = $rt_repo->get($entity_type_id, $this->entity->bundle());
      $type_name = $rt->getTypeName();
      $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
      $entry_point_url = Url::fromRoute('jsonapi.resource_list', [], ['absolute' => TRUE])->toString(TRUE);
      $route_name = sprintf('jsonapi.%s.individual', $type_name);
      $individual = Url::fromRoute(
        $route_name,
        [
          static::getEntityRouteParameterName($route_name, $entity_type_id) => $this->entity->uuid(),
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
      $output['meta'] = [
        'deprecated' => [
          'jsonapi.pathPrefix' => $this->t(
            'This property has been deprecated and will be removed in the next version of Decoupled Router. Use @alternative instead.', ['@alternative' => 'basePath']
          ),
        ],
      ];
    }

    $response->setStatusCode(200);
    $response->setData($output);

    $event->stopPropagation();
  }

  /**
   * Get the JSON API Output data.
   *
   * @return array
   *   The JSON Output array.
   */
  protected function getJsonOutput() {
    $output = [
      'resolved' => $this->resolvedUrl->getGeneratedUrl(),
      'isHomePath' => $this->resolvedPathIsHomePath($this->resolvedUrl->getGeneratedUrl()),
      'entity' => [
        'canonical' => $this->canonicalUrl->getGeneratedUrl(),
        'type' => $this->entity->getEntityTypeId(),
        'bundle' => $this->entity->bundle(),
        'id' => $this->entity->id(),
        'uuid' => $this->entity->uuid(),
      ],
    ];

    if ($this->entity->access('view label', NULL, TRUE)->isAllowed()) {
      $output['label'] = $this->entity->label();
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PathTranslatorEvent::TRANSLATE][] = ['onPathTranslation'];
    return $events;
  }

  /**
   * Removes the subdir prefix from the path.
   *
   * @param string $path
   *   The path that can contain the subdir prefix.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to extract the path prefix from.
   *
   * @return string
   *   The clean path.
   */
  protected function cleanSubdirInPath($path, Request $request) {
    // Remove any possible leading subdir information in case Drupal is
    // installed under http://example.com/d8/index.php
    $regexp = preg_quote($request->getBasePath(), '/');
    return preg_replace(sprintf('/^%s/', $regexp), '', $path);
  }

  /**
   * Computes the name of the entity route parameter for JSON API routes.
   *
   * @param string $route_name
   *   A JSON API route name.
   * @param string $entity_type_id
   *   The corresponding entity type ID.
   *
   * @return string
   *   Either 'entity' or $entity_type_id.
   *
   * @todo Remove this once decoupled_router requires jsonapi >= 8.x-2.0.
   */
  protected static function getEntityRouteParameterName($route_name, $entity_type_id) {
    static $first;

    if (!isset($first)) {
      $route_parameters = \Drupal::service('router.route_provider')
        ->getRouteByName($route_name)
        ->getOption('parameters');
      $first = isset($route_parameters['entity'])
        ? 'entity'
        : $entity_type_id;
      return $first;
    }

    return $first === 'entity'
      ? 'entity'
      : $entity_type_id;
  }

  /**
   * Checks if the resolved path is the home path.
   *
   * @param string $resolved_url
   *   The resolved url from the request.
   *
   * @return bool
   *   True if the resolved path is the home path, false otherwise.
   */
  protected function resolvedPathIsHomePath($resolved_url) {
    $home_path = $this->configFactory->get('system.site')->get('page.front');
    $home_url = Url::fromUserInput($home_path, ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();

    return $resolved_url === $home_url;
  }

}
