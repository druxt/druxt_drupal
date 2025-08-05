<?php

namespace Drupal\druxt\Plugin\Condition;

use Drupal\system\Plugin\Condition\RequestPath;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Request Path' condition.
 */
class DruxtRequestPath extends RequestPath {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return druxt_access_check($this->currentUser) ? !$this->isNegated() : parent::evaluate();
  }

}
