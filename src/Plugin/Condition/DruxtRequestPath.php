<?php

namespace Drupal\druxt\Plugin\Condition;

use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Provides a 'Request Path' condition.
 */
class DruxtRequestPath extends RequestPath {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $account = \Drupal::currentUser();
    return druxt_access_check($account) ? !$this->isNegated() : parent::evaluate();
  }

}
