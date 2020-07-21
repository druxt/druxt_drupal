<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;

/**
 * Tests the Druxt resources access permission.
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
   * Test that the permission gives access to all required resources.
   */
  public function testPermissions() {
    $consumer = $this->createUser(['access druxt resources']);
    $this->drupalLogin($consumer);

    $resources = [
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

    $router = $this->container->get('router');

    foreach ($resources as $resource) {
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
