<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\druxt\Form\DruxtSettingsForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form logic in process.
 *
 * The functional test drives the same form through a browser, which proves
 * the routing and the permission. It runs the site in the webserver process,
 * so it exercises none of this code where a coverage run can see it, and the
 * two paths below have no browser equivalent at all.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtSettingsFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'field',
    'file',
    'text',
    'filter',
    'path_alias',
    'menu_link_content',
    'link',
    'decoupled_router',
    'jsonapi',
    'views',
    'editor',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['druxt']);
  }

  /**
   * Builds the form and returns the resources element.
   *
   * @return array
   *   The built checkboxes element.
   */
  private function resourcesElement(): array {
    $form = \Drupal::formBuilder()->getForm(DruxtSettingsForm::class);
    return $form['resources'];
  }

  /**
   * Submits the form with the given checkbox values.
   *
   * @param string[] $checked
   *   The resource ids to tick.
   */
  private function submit(array $checked): void {
    // A browser omits a checkbox that is not ticked, and a programmed
    // submission is validated against the options, so an explicit 0 is
    // refused here.
    $form_state = new FormState();
    $form_state->setValues(['resources' => array_combine($checked, $checked)]);
    \Drupal::formBuilder()->submitForm(DruxtSettingsForm::class, $form_state);

    // A form that failed validation silently saves nothing, which would make
    // every assertion below pass or fail for the wrong reason.
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Returns the stored resource list.
   *
   * @return string[]
   *   The resource ids configuration holds.
   */
  private function stored(): array {
    return $this->config('druxt.settings')->get('resources');
  }

  /**
   * Tests that the form offers no content entity resource.
   */
  public function testFormOffersNoContentEntityResource(): void {
    $options = $this->resourcesElement()['#options'];

    $this->assertArrayHasKey('view--view', $options);
    $this->assertArrayHasKey('editor--editor', $options);
    $this->assertArrayNotHasKey('user--user', $options);

    // The one content resource that shipped in the hardcoded list stays,
    // because removing it would break sites on upgrade.
    $this->assertArrayHasKey('menu_link_content--menu_link_content', $options);
  }

  /**
   * Tests that a resource shipped unchecked is offered unchecked.
   */
  public function testShippedDefaultsAreTicked(): void {
    $element = $this->resourcesElement();

    $this->assertContains('view--view', $element['#default_value']);
    $this->assertNotContains('editor--editor', $element['#default_value']);
  }

  /**
   * Tests that the form shows configuration, not the altered list.
   *
   * The alter hook runs on read. If the form showed its result, saving
   * would write what a module added into configuration, and delete what a
   * module removed.
   */
  public function testFormIgnoresTheAlterHook(): void {
    \Drupal::service('module_installer')->install(['druxt_resources_test']);

    $element = $this->resourcesElement();

    // The hook removes this one, but configuration still holds it.
    $this->assertContains('view--view', $element['#default_value']);

    // The hook adds this one, which the form must never offer or tick.
    $this->assertArrayNotHasKey('user--user', $element['#options']);
    $this->assertNotContains('user--user', $element['#default_value']);
  }

  /**
   * Tests that saving stores what was ticked.
   */
  public function testSubmitStoresTheTickedResources(): void {
    $this->submit(['view--view', 'editor--editor']);

    $this->assertContains('editor--editor', $this->stored());
    $this->assertContains('view--view', $this->stored());
    $this->assertNotContains('menu--menu', $this->stored());
  }

  /**
   * Tests that saving keeps a resource the form could not offer.
   */
  public function testSubmitKeepsResourcesTheFormCannotOffer(): void {
    $this->config('druxt.settings')
      ->set('resources', array_merge(druxt_default_resources(), ['comment_type--comment_type']))
      ->save();

    // The comment module is absent, so the form has no checkbox for it.
    $this->assertArrayNotHasKey('comment_type--comment_type', $this->resourcesElement()['#options']);

    $this->submit(['view--view']);

    $this->assertContains('comment_type--comment_type', $this->stored());
  }

  /**
   * Tests that saving does not persist what the alter hook added.
   */
  public function testSubmitDoesNotPersistTheAlterHooksAddition(): void {
    \Drupal::service('module_installer')->install(['druxt_resources_test']);

    $this->submit(['view--view']);

    $this->assertNotContains('user--user', $this->stored());
  }

  /**
   * Tests that removing a shipped default warns about the consequence.
   */
  public function testSubmitWarnsWhenDefaultsAreRemoved(): void {
    $this->submit(['editor--editor']);

    $warnings = \Drupal::messenger()->messagesByType('warning');
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('view--view', (string) reset($warnings));
  }

  /**
   * Tests that keeping every default warns about nothing.
   */
  public function testSubmitDoesNotWarnWhenNothingIsRemoved(): void {
    $offered = array_keys($this->resourcesElement()['#options']);
    $this->submit(array_intersect(druxt_default_resources(), $offered));

    $this->assertSame([], \Drupal::messenger()->messagesByType('warning'));
  }

}
