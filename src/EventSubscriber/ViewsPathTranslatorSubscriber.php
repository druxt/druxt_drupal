<?php

namespace Drupal\druxt\EventSubscriber;

use Drupal\Core\Url;

/**
 * Event subscriber that processes a path translation with the router info.
 */
class ViewsPathTranslatorSubscriber extends PathTranslatorBase {

  /**
   * The View Display.
   *
   * @var array
   */
  protected $display;

  /**
   * {@inheritDoc}
   */
  protected function findEntityAndKeys(array $match_info) {
    if (!$match_info['view_id']) {
      return [FALSE];
    }

    $entity_type_manager = $this->container->get('entity_type.manager');
    $views_storage = $entity_type_manager->getStorage('view');
    $view = $views_storage->load($match_info['view_id']);

    $this->display = $view->getDisplay($match_info['display_id']);

    return [$view, TRUE, NULL];
  }

  /**
   * {@inheritDoc}
   */
  protected function getCanonicalUrl() {
    return Url::fromUri("internal:/{$this->display['display_options']['path']}", ['absolute' => TRUE])->toString(TRUE);
  }

  /**
   * {@inheritDoc}
   */
  public function getDependencies() {
    return ['views'];
  }

  /**
   * {@inheritDoc}
   */
  protected function getJsonOutput() {
    return parent::getJsonOutput() + [
      'view' => [
        'view_id' => $this->matchInfo['view_id'],
        'display_id' => $this->matchInfo['display_id'],
      ],
    ];
  }

}
