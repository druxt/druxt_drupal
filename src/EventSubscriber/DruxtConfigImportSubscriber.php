<?php

declare(strict_types=1);

namespace Drupal\druxt\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Refuses a configuration import that would expose an unsafe resource.
 *
 * The configuration schema carries the rule, but nothing runs schema
 * constraints on save or on import, so a hand-edited or imported
 * druxt.settings would otherwise be accepted. Being on the list grants a
 * blanket entity access result, so the rule has to hold here too.
 */
class DruxtConfigImportSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The configuration name this subscriber guards.
   */
  private const CONFIG_NAME = 'druxt.settings';

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed configuration manager.
   */
  public function __construct(
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::IMPORT_VALIDATE => ['onConfigImportValidate', 20]];
  }

  /**
   * Checks the resource list an import would store.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   The configuration import event.
   */
  public function onConfigImportValidate(ConfigImporterEvent $event): void {
    $importer = $event->getConfigImporter();
    $comparer = $importer->getStorageComparer();

    // Every collection, not just the default one. A language collection
    // holds configuration overrides, so druxt.settings in language.es would
    // otherwise reach a site without being checked.
    foreach ($comparer->getAllCollectionNames() as $collection) {
      foreach (['create', 'update'] as $op) {
        if (!in_array(self::CONFIG_NAME, $importer->getUnprocessedConfiguration($op, $collection), TRUE)) {
          continue;
        }

        $data = $comparer->getSourceStorage($collection)->read(self::CONFIG_NAME);
        if (!is_array($data)) {
          continue;
        }

        $violations = $this->typedConfigManager
          ->createFromNameAndData(self::CONFIG_NAME, $data)
          ->validate();

        // The default collection is named by the empty string, which reads
        // as a missing word in an error a deploy job prints.
        $label = $collection === StorageInterface::DEFAULT_COLLECTION
          ? $this->t('the default collection')
          : $collection;

        foreach ($violations as $violation) {
          $importer->logError((string) $this->t('@config in @collection: @message', [
            '@config' => self::CONFIG_NAME,
            '@collection' => $label,
            '@message' => strip_tags((string) $violation->getMessage()),
          ]));
        }
      }
    }
  }

}
