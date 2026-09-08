<?php

declare(strict_types=1);

namespace Drupal\druxt\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Chooses which JSON:API resources Druxt exposes.
 */
class DruxtSettingsForm extends ConfigFormBase {

  /**
   * The JSON:API resource type repository.
   */
  protected ResourceTypeRepositoryInterface $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): static {
    $form = parent::create($container);
    $form->resourceTypeRepository = $container->get('jsonapi.resource_type.repository');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'druxt_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['druxt.settings'];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $configured = $this->storedResources();
    $options = $this->resourceOptions();

    $form['resources'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exposed resources'),
      '#options' => $options,
      '#default_value' => array_values(array_intersect($configured, array_keys($options))),
      '#description' => $this->t('A checked resource is readable by anyone with the "access druxt resources" permission, whatever the site would otherwise allow, and cannot be scoped to a role or a consumer. Unchecking one can stop a frontend rendering. Only configuration is listed here; a module can expose anything else with hook_druxt_resources_alter().'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $offered = array_keys($this->resourceOptions());
    $chosen = array_values(array_filter($form_state->getValue('resources')));

    // A resource configured for a module that is not installed here is not on
    // the form, so keep it rather than dropping it on save. Otherwise saving
    // on a site without Views would quietly remove view--view for every site
    // sharing the configuration.
    $absent = array_values(array_diff($this->storedResources(), $offered));

    $resources = array_values(array_unique(array_merge($chosen, $absent)));
    sort($resources);

    $this->config('druxt.settings')->set('resources', $resources)->save();

    // Druxt's own route translation and schema handling read some of these.
    // The module cannot know what a given frontend renders, so say what was
    // removed rather than refusing to remove it.
    $removed = array_intersect(array_diff(druxt_default_resources(), $resources), $offered);
    if ($removed !== []) {
      $this->messenger()->addWarning($this->t('Druxt no longer exposes @resources. A frontend that reads them will stop rendering that part of the site.', [
        '@resources' => implode(', ', $removed),
      ]));
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets the resource list as it is stored.
   *
   * Not druxt_resources(), which rechecks the list and applies
   * hook_druxt_resources_alter(). Saving that would persist what a module
   * added in code, and delete what a module removed.
   *
   * @return string[]
   *   Resource type ids, as configuration holds them.
   */
  protected function storedResources(): array {
    return $this->config('druxt.settings')->get('resources') ?? druxt_default_resources();
  }

  /**
   * Builds the offered resource options.
   *
   * @return array<string, string>
   *   Resource type ids, keyed and labelled.
   */
  protected function resourceOptions(): array {
    $options = [];
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $name = $resource_type->getTypeName();
      if ($resource_type->isInternal() || !druxt_resource_is_allowed($name)) {
        continue;
      }
      $options[$name] = $name;
    }
    ksort($options);
    return $options;
  }

}
