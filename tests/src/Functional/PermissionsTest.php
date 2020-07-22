<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;

/**
 * Tests the 'access druxt resources' permission.
 *
 * @group druxt
 */
class PermissionsTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'druxt',
    'block',
    'menu_link_content',
    'node',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * JSON API resources.
   *
   * @var array
   */
  protected $resources = [
    'block--block',
    'entity_form_display--entity_form_display',
    'entity_form_mode--entity_form_mode',
    'entity_view_display--entity_view_display',
    'entity_view_mode--entity_view_mode',
    'field_config--field_config',
    'field_storage_config--field_storage_config',
    'menu_link_content--menu_link_content',
    'view--view',
  ];

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

    $this->consumer = $this->createUser(['access druxt resources']);
  }

  /**
   * Test that the permission gives access to all required resources.
   */
  public function testPermissions() {
    $this->drupalLogin($this->consumer);

    $router = $this->container->get('router');

    foreach ($this->resources as $resource) {
      // Test GET requests are allowed.
      $res = $this->drupalGet(Url::fromRoute("jsonapi.${resource}.collection"));
      $this->assertSession()->statusCodeEquals(200);
      $output = Json::decode($res);
      $this->assertArrayNotHasKey('meta', $output);

      if (!$router->getRouteCollection()->get("jsonapi.${resource}.collection.post")) {
        continue;
      }

      // Test POST requests are not allowed.
      $url = Url::fromRoute("jsonapi.${resource}.collection.post");
      $res = $this->request('POST', $url, []);
      $this->assertSame(405, $res->getStatusCode());
    }
  }

}
