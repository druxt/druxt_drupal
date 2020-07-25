<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\block\Entity\Block;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Block Conditon bypass for Druxt resources.
 *
 * @group druxt
 */
class ConditionBypassTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['druxt', 'block'];

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

    // Create test block with request_path condition.
    $block = Block::create([
      'plugin' => 'test_block',
      'region' => 'header',
      'id' => 'test',
      'theme' => 'stark',
    ]);
    $block->setVisibilityConfig('request_path', [
      'id' => 'request_path',
      'pages' => '<front>',
      'negate' => FALSE,
      'context_mapping' => [],
    ]);
    $block->save();
  }

  /**
   * Test that the block is inaccessible due to condition plugin.
   */
  public function testRequestPathOmitted() {
    $res = $this->drupalGet(Url::fromRoute('jsonapi.block--block.collection'));
    $this->assertSession()->statusCodeEquals(200);
    $output = Json::decode($res);
    $this->assertArrayHasKey('meta', $output);
  }

  /**
   * Test that the block condition plugin is bypassed with permission.
   */
  public function testRequestPathBypass() {
    $this->drupalLogin($this->consumer);

    $res = $this->drupalGet(Url::fromRoute('jsonapi.block--block.collection'));
    $this->assertSession()->statusCodeEquals(200);
    $output = Json::decode($res);

    $this->assertArrayNotHasKey('meta', $output);
  }

}
