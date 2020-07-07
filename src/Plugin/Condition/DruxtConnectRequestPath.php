<?php

namespace Drupal\druxt_connect\Plugin\Condition;

use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Provides a 'Request Path' condition.
 */
class DruxtConnectRequestPath extends RequestPath {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $account = \Drupal::currentUser();
    return druxt_connect_access_check($account) ? TRUE : parent::evaluate();
  }

}
