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
      // Enable CORS by default.
      $cors_config['enabled'] = TRUE;

      // Set allowed headers to '*' by default.
      if (count($cors_config['allowedHeaders']) === 0) {
        $cors_config['allowedHeaders'][] = '*';
      }

      $container->setParameter('cors.config', $cors_config);
    }
  }

}
