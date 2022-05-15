<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests ViewsPathTranslatorSubscriber for proper handling of
 * multi-language paths.
 *
 * @group druxt
 */
class ViewsPathTranslatorSubscriberTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['druxt', 'druxt_test', 'jsonapi_views'];

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
   * Tests that Views display paths resolve to the correct view_id / display_id
   * when a language is specified in the path.
   */
  public function testViewsPathTranslatorSubscriber() {

    // Assert that the English language code is handled properly.
    $res = $this->drupalGet('/en/recipes');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Deep mediterranean quiche');

    $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    $this->assertSession()->statusCodeEquals(200);
    $output = Json::decode($res);
    $this->assertNotEmpty($output['data']);
    $this->assertEquals('recipes', $output['data']['view_id']);
    $this->assertEquals('page_1', $output['data']['display_id']);
    // @todo What else to check for in data?

    // Assert that the Spanish language code is handled properly.
    $res = $this->drupalGet('/es/recipes');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Quiche mediterráneo profundo');

    $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    $this->assertSession()->statusCodeEquals(200);
    $output = Json::decode($res);
    $this->assertNotEmpty($output['data']);
    $this->assertEquals('recipes', $output['data']['view_id']);
    $this->assertEquals('page_1', $output['data']['display_id']);
    // @todo What else to check for in data?

  }

}
