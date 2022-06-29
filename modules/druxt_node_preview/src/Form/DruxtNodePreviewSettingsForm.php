<?php

namespace Drupal\druxt_node_preview\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Druxt: Node Preview settings for this site.
 *
 * @internal
 */
class DruxtNodePreviewSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'druxt_node_preview_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['druxt_node_preview.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('druxt_node_preview.settings');

    $form['frontends'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Preview frontends'),
      '#default_value' => $config->get('frontends'),
      '#description' =>
        $this->t('The available preview frontends. Enter one value per line, in the format label|url') . '<br />' .
        $this->t('The label is optional: if a line contains a single string, it will be used as url and label.') . '<br />' .
        $this->t('The following tokens can be used in the url: [bundle], [mode], [preview]'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('druxt_node_preview.settings')
      ->set('frontends', $form_state->getValue('frontends'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
