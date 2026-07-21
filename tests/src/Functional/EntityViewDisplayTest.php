<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the EntityViewDisplay creation for content entities.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class EntityViewDisplayTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['druxt', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Consumer user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $consumer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create consumer.
    $this->consumer = $this->createUser(['access druxt resources']);
    $this->drupalLogin($this->consumer);
  }

  /**
   * Test that EntityViewDisplay configuration is created for bundle.
   */
  public function testTaxonomyEntityViewDisplay(): void {
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
    ]);
    $vocabulary->save();

    $res = $this->drupalGet(Url::fromRoute('jsonapi.entity_view_display--entity_view_display.collection'), [
      'query' => ['filter' => ['drupal_internal__id' => 'taxonomy_term.tags.default']],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $output = Json::decode($res);
    $this->assertNotEmpty($output['data']);
  }

}
