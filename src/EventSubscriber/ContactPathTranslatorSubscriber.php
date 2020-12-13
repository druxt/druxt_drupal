<?php

namespace Drupal\druxt\EventSubscriber;

use Drupal\Core\Url;

/**
 * Event subscriber that processes a path translation with the router info.
 */
class ContactPathTranslatorSubscriber extends PathTranslatorBase {

  /**
   * {@inheritDoc}
   */
  public function getDependencies() {
    return ['contact'];
  }

  /**
   * {@inheritDoc}
   */
  protected function findEntityAndKeys(array $match_info) {
    if ($match_info['_route'] !== 'contact.site_page') {
      return;
    }

    $config = $this->configFactory->get('contact.settings');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $contact_storage = $entity_type_manager->getStorage('contact_form');
    $contact_form = $contact_storage->load($config->get('default_form'));

    return [$contact_form];
  }

  /**
   * {@inheritDoc}
   */
  protected function getCanonicalUrl($entity) {
    return Url::fromRoute('contact.site_page', [], ['absolute' => TRUE])->toString(TRUE);
  }

}
