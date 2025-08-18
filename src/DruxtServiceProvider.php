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

      // Set allowed headers to '*' by default when empty/undefined.
      if (empty($cors_config['allowedHeaders'])) {
        $cors_config['allowedHeaders'] = ['*'];
      }

      $container->setParameter('cors.config', $cors_config);
    }
  }

}
