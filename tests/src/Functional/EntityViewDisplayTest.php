<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the EntityViewDisplay creation for content entities.
 *
 * @group druxt
 */
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
   * @var \Drupal\user\Entity\User
   */
  protected $consumer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create consumer.
    $this->consumer = $this->createUser(['access druxt resources']);
    $this->drupalLogin($this->consumer);
  }

  /**
   * Test that EntityViewDisplay configruation is created for bundle.
   */
  public function testTaxonomyEntityViewDisplay() {
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
