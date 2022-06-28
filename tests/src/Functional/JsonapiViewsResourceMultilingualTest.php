<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\Tests\jsonapi_views\Functional\JsonapiViewsResourceTest;
use Drupal\views\Tests\ViewTestData;
use GuzzleHttp\RequestOptions;
use Drupal\Component\Utility\NestedArray;

/**
 * Tests for proper handling of multilingual View display paths
 * by the jsonapi views resource.
 *
 */
class JsonapiViewsResourceMultilingualTest extends JsonapiViewsResourceTest {

  /**
   * @group druxt
   *
   * Tests that Views display paths resolve to the correct view_id / display_id
   * when a language is specified in the path.
   *
   */
  public function testJsonApiViewsResourceDisplaysMultilingual() {
    $location = $this->drupalCreateNode(['type' => 'location']);
    $room = $this->drupalCreateNode(['type' => 'room']);

    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Page display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'page_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(2, $response_document['data']);
    $this->assertEqual(2, $response_document['meta']['count']);
    $this->assertCacheContext($headers, 'url.query_args:page');
    $this->assertCacheTags($headers, [
      'config:views.view.jsonapi_views_test_node_view',
      'http_response',
      'node:1',
      'node:2',
      'node_list',
    ]);

    // Block display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'block_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(1, $response_document['data']);
    $this->assertEqual(1, $response_document['meta']['count']);
    $this->assertSame($room->uuid(), $response_document['data'][0]['id']);
    $this->assertCacheContext($headers, 'url.query_args:page');

    // Attachment display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'attachment_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(1, $response_document['data']);
    $this->assertEqual(1, $response_document['meta']['count']);
    $this->assertSame($location->uuid(), $response_document['data'][0]['id']);
    $this->assertCacheContext($headers, 'url.query_args:page');

    // Un-exposed display.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $this->getJsonApiViewUrl('jsonapi_views_test_node_view', 'feed_1'), $request_options);
    $this->assertSame(403, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
  }

  /**
   * Get a JSON:API Views Url for a given view display.
   *
   * @param string $view_name
   *   The View name.
   * @param string $display_id
   *   The View display id.
   * @param string $query
   *   A query object to add to the request.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\core\Url
   *   The url for a JSON:API View.
   */
  protected function getJsonApiViewUrlMultilingual($view_name, $display_id, $query = [], $langcode='') {
    if($langcode) {
      $url = Url::fromUri("internal:/{$langcode}/jsonapi/views/{$view_name}/{$display_id}");
    } else {
      $url = Url::fromUri("internal:/jsonapi/views/{$view_name}/{$display_id}");
    }
    $url->setOption('query', $query);
    return $url;
  }

}




  //
  //  public function testUrl2View() {

  //    $url = 'https://domain.tld/en/'

  //    $res = $this->drupalGet(
  //      Url::fromRoute('decoupled_router.path_translation'),
  //      [
  //        'query' => [
  //          'path' => '/ca/jsonapi-views-test-node-view',
  //          '_format' => 'json',
  //        ],
  //      ]
  //    );

  //    $this->assertSession()->statusCodeEquals(200);

  // }


     // Example english JSON data to test for with:
     // {
     //   resolved: 'https://domain.tld/en/articles',
     //   view: {
     //     uuid: 'UUID',
     //     view_id: 'featured_articles',
     //     display_id: 'page_1',
     //     langcode: 'en,'
     //   },
     //   jsonapi: {
     //     individual: 'https://domain.tld/en/jsonapi/view/view/UUID',
     //     resourceName: 'view--view',
     //     pathPrefix: 'en/jsonapi',
     //     basePath: '/en/jsonapi',
     //     entryPoint: 'https://domain.tld/en/jsonapi'
     //   },
     //   jsonapi_views: 'https://domain.tld/en/jsonapi/views/featured_articles/page_1'
     // }

     // Example spanish JSON data to test for with:
     // {
     //   resolved: 'https://domain.tld/es/articles',
     //   view: {
     //     uuid: 'UUID',
     //     view_id: 'featured_articles',
     //     display_id: 'page_1',
     //     langcode: 'es,'
     //   },
     //   jsonapi: {
     //     individual: 'https://domain.tld/es/jsonapi/view/view/UUID',
     //     resourceName: 'view--view',
     //     pathPrefix: 'es/jsonapi',
     //     basePath: '/es/jsonapi',
     //     entryPoint: 'https://domain.tld/es/jsonapi'
     //   },
     //   jsonapi_views: 'https://domain.tld/es/jsonapi/views/featured_articles/page_1'
     // }


    // // Assert that the English language code is handled properly.
    // $res = $this->drupalGet('/en/recipes');
    // $this->assertSession()->responseContains('Deep mediterranean quiche');

    // $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    // $this->assertSession()->statusCodeEquals(200);
    // $output = Json::decode($res);
    // $this->assertNotEmpty($output['data']);
    // $this->assertEquals('recipes', $output['data']['view_id']);
    // $this->assertEquals('page_1', $output['data']['display_id']);
    // // @todo What else to check for in data?

    // // Assert that the Spanish language code is handled properly.
    // $res = $this->drupalGet('/es/recipes');
    // $this->assertSession()->statusCodeEquals(200);
    // $this->assertSession()->responseContains('Quiche mediterráneo profundo');

    // $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    // $this->assertSession()->statusCodeEquals(200);
    // $output = Json::decode($res);
    // $this->assertNotEmpty($output['data']);
    // $this->assertEquals('recipes', $output['data']['view_id']);
    // $this->assertEquals('page_1', $output['data']['display_id']);
    // @todo What else to check for in data?
