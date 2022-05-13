<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\views\Tests\ViewTestData;
use Drupal\Tests\views\Functional\ViewTestBase;
 use GuzzleHttp\RequestOptions;

/**
 * Tests JSON:API Views routes.S
 *
 * @group jsonapi_views
 */
class DruxtViewsResourceTest extends ViewTestBase {

  use JsonApiRequestTestTrait;

  /**
   * The account to use for authentication.
   *
   * @var null|\Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['druxt', 'views', 'druxt_views_test'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['druxt_views_test_node_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['jsonapi_views_test']);
    $this->enableViewsTestModule();

    // Ensure the anonymous user role has no permissions at all.
    $user_role = Role::load(RoleInterface::ANONYMOUS_ID);
    foreach ($user_role->getPermissions() as $permission) {
      $user_role->revokePermission($permission);
    }
    $user_role->save();
    assert([] === $user_role->getPermissions(), 'The anonymous user role has no permissions at all.');

    // Ensure the authenticated user role has no permissions at all.
    $user_role = Role::load(RoleInterface::AUTHENTICATED_ID);
    foreach ($user_role->getPermissions() as $permission) {
      $user_role->revokePermission($permission);
    }
    $user_role->save();
    assert([] === $user_role->getPermissions(), 'The authenticated user role has no permissions at all.');

    $this->container->get('router.builder')->rebuildIfNeeded();

    // Create an account, which tests will use. Also ensure the @current_user
    // service this account, to ensure certain access check logic in tests works
    // as expected.
    $this->account = $this->createUser();
    $this->container->get('current_user')->setAccount($this->account);
  }

  /**
   * Asserts whether an expected cache context was present in the last response.
   *
   * @param array $headers
   *   An array of HTTP headers.
   * @param string $expected_cache_context
   *   The expected cache context.
   */
  protected function assertCacheContext(array $headers, $expected_cache_context) {
    $cache_contexts = explode(' ', $headers['X-Drupal-Cache-Contexts'][0]);
    $this
      ->assertTrue(in_array($expected_cache_context, $cache_contexts), "'" . $expected_cache_context . "' is present in the X-Drupal-Cache-Contexts header.");
  }

  /**
   * Asserts whether expected cache tags were present in the last response.
   *
   * @param array $headers
   *   An array of HTTP headers.
   * @param array $expected_cache_tags
   *   The expected cache tags.
   */
  protected function assertCacheTags(array $headers, array $expected_cache_tags) {
    $cache_tags = explode(' ', $headers['X-Drupal-Cache-Tags'][0]);
    ksort($expected_cache_tags);
    ksort($cache_tags);
    $this->assertEquals($expected_cache_tags, $cache_tags, "Expected cache tags are present in the X-Drupal-Cache-Tags header.");
  }

  /**
   * Tests that the test view has been enabled.
   */
  public function testNodeViewExists() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $this->drupalGet('druxt-views-test-node-view');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('JSON:API Views Test Node View');
  }

  /**
   * Tests the JSON:API Views resource displays.
   */
  public function testJsonApiViewsResourceDisplays() {
    $location = $this->drupalCreateNode(['type' => 'location']);
    $room = $this->drupalCreateNode(['type' => 'room']);

    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Page display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(2, $response_document['data']);
    $this->assertEqual(2, $response_document['meta']['count']);
    $this->assertCacheContext($headers, 'url.query_args:page');
    $this->assertCacheTags($headers, [
      'config:views.view.druxt_views_test_node_view',
      'http_response',
      'node:1',
      'node:2',
      'node_list',
    ]);

    // Block display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'block_1')
    );

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);
    $this->assertCount(1, $response_document['data']);
    $this->assertEqual(1, $response_document['meta']['count']);
    $this->assertSame($room->uuid(), $response_document['data'][0]['id']);
    $this->assertCacheContext($headers, 'url.query_args:page');

    // Attachment display.
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'attachment_1')
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

    $response = $this->request('GET', $this->getJsonApiViewUrl('druxt_views_test_node_view', 'feed_1'), $request_options);
    $this->assertSame(403, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
  }

  /**
   * Tests the JSON:API Views resource Exposed Filters feature.
   */
  public function testJsonApiViewsResourceExposedFilters() {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'bypass node access',
    ]));

    $nodes = [
      'published' => [],
      'unpublished' => [],
      'promoted' => [],
      'unpromoted' => [],
    ];

    for ($i = 0; $i < 9; $i++) {
      $promoted = ($i % 2 === 0);
      $published = ($i % 3 === 0);
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => $published ? 1 : 0,
        'promote' => $promoted ? 1 : 0,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
      $nodes[$published ? 'published' : 'unpublished'][$node->uuid()] = $node;
      $nodes[$promoted ? 'promoted' : 'unpromoted'][$node->uuid()] = $node;
    }

    // Get published nodes.
    $query = ['views-filter[status]' => '1'];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(3, $response_document['data']);
    $this->assertEquals(3, $response_document['meta']['count']);
    $this->assertArrayNotHasKey('next', $response_document['links']);
    $this->assertSame(array_keys($nodes['published']), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-filter');

    // Get unpublished nodes.
    $query = ['views-filter[status]' => '0'];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEquals(6, $response_document['meta']['count']);
    $this->assertArrayHasKey('next', $response_document['links']);
    $this->assertSame(array_slice(array_keys($nodes['unpublished']), 0, 5), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-filter');

    // Get all nodes.
    $query = [];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEqual(9, $response_document['meta']['count']);
    $this->assertArrayHasKey('next', $response_document['links']);
    $this->assertSame(array_slice(array_keys($nodes['all']), 0, 5), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-filter');
  }

  /**
   * Tests the JSON:API Views resource Exposed Sort feature.
   */
  public function testJsonApiViewsResourceExposedSort() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $nodes = [];
    for ($i = 0; $i < 5; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => 1,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
    }

    // Test that the view is ordered by Node ID in asscending direction.
    $query = ['views-sort[sort_by]' => 'nid', 'views-sort[sort_order]' => 'ASC'];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertSame(array_keys($nodes['all']), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-sort');

    // Test that the view is ordered by Node ID in descending direction.
    $query = [
      'views-sort[sort_by]' => 'nid',
      'views-sort[sort_order]' => 'DESC',
    ];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertSame(array_reverse(array_keys($nodes['all'])), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-sort');
  }

  /**
   * Tests the JSON:API Views resource View Arguments feature.
   */
  public function testJsonApiViewsResourceViewArguments() {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'bypass node access',
    ]));

    $nodes = [];
    $created_dates = [
      '2021-02-24',
      '2021-01-25',
      '2021-01-22',
      '2021-01-20',
      '2021-01-15',
      '2021-01-10',
      '2020-12-24',
      '2020-12-23',
      '2020-12-22',
    ];

    for ($i = 0; $i < 9; $i++) {
      $created_date_parts = explode('-', $created_dates[$i]);
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => 1,
        'created' => strtotime($created_dates[$i]),
      ]);
      $node->save();

      $nodes[$created_date_parts[0]][$node->uuid()] = $node;
      $nodes[$created_date_parts[0] . '-' . $created_date_parts[1]][$node->uuid()] = $node;
      $nodes['all'][$node->uuid()] = $node;
    }

    // Get nodes from 2020.
    $query = ['views-argument[]' => '2020'];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(3, $response_document['data']);
    $this->assertEquals(3, $response_document['meta']['count']);
    $this->assertArrayNotHasKey('next', $response_document['links']);
    $this->assertSame(array_keys($nodes['2020']), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-argument');

    // Get nodes from 2021-01.
    $query = ['views-argument[0]' => '2021', 'views-argument[1]' => '01'];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    
    $this->assertCount(5, $response_document['data']);
    $this->assertEquals(5, $response_document['meta']['count']);
    $this->assertArrayNotHasKey('next', $response_document['links']);
    $this->assertSame(array_keys($nodes['2021-01']), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-argument');

    // Get all nodes.
    $query = [];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'page_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEqual(9, $response_document['meta']['count']);
    $this->assertArrayHasKey('next', $response_document['links']);
    $this->assertSame(array_slice(array_keys($nodes['all']), 0, 5), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertCacheContext($headers, 'url.query_args:views-argument');
  }

  /**
   * Tests the JSON:API Views resource Pager feature.
   */
  public function testJsonApiViewsResourcePager() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $nodes = [];

    for ($i = 0; $i < 12; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'room',
        'status' => 1,
      ]);
      $node->save();

      $nodes['all'][$node->uuid()] = $node;
    }
    $nodes['paged'] = array_chunk($nodes['all'], 5, TRUE);

    // Test that views showing a specified number of items do not include
    // pager links. The block view is configured to show 5 items with no pager.
    $query = [];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'block_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEqual(5, $response_document['meta']['count']);
    $this->assertSame(array_keys($nodes['paged'][0]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertArrayNotHasKey('prev', $response_document['links']);
    $this->assertCacheContext($headers, 'url.query_args:page');

    // Test that views showing a paged items include the correct links
    // The embed view is configured to show a 5 item mini pager.
    $query = [];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', $query)
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEqual(12, $response_document['meta']['count']);
    $this->assertSame(array_keys($nodes['paged'][0]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertArrayNotHasKey('prev', $response_document['links']);
    $this->assertSame(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', ['page' => 1])->setAbsolute()->toString(),
      $response_document['links']['next']['href']
    );
    $this->assertCacheContext($headers, 'url.query_args:page');

    [$response_document, $headers] = $this->getJsonApiViewResponse(
      URL::fromUri($response_document['links']['next']['href'])
    );

    $this->assertCount(5, $response_document['data']);
    $this->assertEqual(12, $response_document['meta']['count']);
    $this->assertSame(array_keys($nodes['paged'][1]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertSame(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', ['page' => 0])->setAbsolute()->toString(),
      $response_document['links']['prev']['href']
    );
    $this->assertSame(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', ['page' => 2])->setAbsolute()->toString(),
      $response_document['links']['next']['href']
    );
    $this->assertCacheContext($headers, 'url.query_args:page');

    [$response_document, $headers] = $this->getJsonApiViewResponse(
      URL::fromUri($response_document['links']['next']['href'])
    );

    $this->assertCount(2, $response_document['data']);
    $this->assertEqual(12, $response_document['meta']['count']);
    $this->assertSame(array_keys($nodes['paged'][2]), array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']));
    $this->assertSame(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', ['page' => 1])->setAbsolute()->toString(),
      $response_document['links']['prev']['href']
    );
    $this->assertArrayNotHasKey('next', $response_document['links']);
    $this->assertCacheContext($headers, 'url.query_args:page');

    $query = [
      'page' => 10,
    ];
    [$response_document, $headers] = $this->getJsonApiViewResponse(
      $this->getJsonApiViewUrl('druxt_views_test_node_view', 'embed_1', $query)
    );

    $this->assertCount(0, $response_document['data']);
    $this->assertEqual(12, $response_document['meta']['count']);
    $this->assertArrayNotHasKey('next', $response_document['links']);
    $this->assertCacheContext($headers, 'url.query_args:page');
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions() {
    return [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->account->name->value . ':' . $this->account->passRaw),
      ],
    ];
  }

  /**
   * Get a JSON:API Views resource response document.
   *
   * @param \Drupal\core\Url $url
   *   The url for a JSON:API View.
   *
   * @return array
   *   The response document.
   */
  protected function getJsonApiViewResponse(Url $url) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));

    $response_document = Json::decode((string) $response->getBody());

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);

    return [$response_document, $response->getHeaders()];
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
   *
   * @return \Drupal\core\Url
   *   The url for a JSON:API View.
   */
  protected function getJsonApiViewUrl($view_name, $display_id, $query = []) {
    $url = Url::fromUri("internal:/jsonapi/views/{$view_name}/{$display_id}");
    $url->setOption('query', $query);

    return $url;
  }

}
