<?php

namespace Drupal\druxt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Enable CORS by default.
 */
class DruxtServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $cors_config = $container->getParameter('cors.config');
    if (!$cors_config['enabled']) {
      $cors_config['enabled'] = TRUE;
      $container->setParameter('cors.config', $cors_config);
    }
  }

}
