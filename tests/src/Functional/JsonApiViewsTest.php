<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the JSON API Views / Decoupled Router integration.
 *
 * @group druxt
 */
class JsonApiViewsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['druxt', 'jsonapi_views', 'views'];

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
    $this->consumer = $this->createUser(['access content', 'access druxt resources']);
  }

  /**
   * Test a Views page route.
   */
  public function testViewsPageRoute() {
    $this->drupalLogin($this->consumer);

    $res = $this->drupalGet(
      Url::fromRoute('decoupled_router.path_translation'),
      ['query' => [
        'path' => '/admin/people',
        '_format' => 'json',
      ]]
    );
    $output = Json::decode($res);

    $this->assertArrayHasKey('resolved', $output);
    $this->assertArrayHasKey('view', $output);
  }

}
