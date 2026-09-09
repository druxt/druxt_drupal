# Changelog

All notable changes to the [Druxt module](https://www.drupal.org/project/druxt)
are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions are the project's git tags. Some are tag only and have no
[release on Drupal.org](https://www.drupal.org/project/druxt/releases):
0.4.0, 1.0.0-beta1, 1.0.0-beta2, 1.0.0, 1.0.1, 1.1.0 and 1.2.0.

## [Unreleased]

### Added

- Configurable list of exposed JSON:API resources, at
  `/admin/config/services/druxt`, with `hook_druxt_resources_alter()` for
  modules that know what they need
  ([#3309969](https://www.drupal.org/i/3309969)).

### Fixed

- Preflighted cross-origin requests are no longer refused. Druxt enabled CORS
  without setting `allowedMethods`, so the preflight allowed no method and the
  browser dropped the request. Anonymous reads worked, and every JSON:API write
  and every authenticated read failed. The default is now `['*']`, matching the
  existing `allowedHeaders` default. A site that has set `cors.config.enabled`
  to `TRUE` itself is unaffected, before and after. Setting it to `FALSE` does
  not exempt a site: Druxt has always turned CORS on where it was off.

## [1.2.2] - 2026-09-05

### Added

- Drupal 12 compatibility ([#3597394](https://www.drupal.org/i/3597394)). Drupal 12
  cannot be installed yet, because Decoupled Router and JSON:API Menu Items are both
  still capped at Drupal 11.
- Default CORS `allowedHeaders` ([#3541756](https://www.drupal.org/i/3541756)).
- Test coverage for the path translator subscribers.

### Changed

- Applied the drupal_extension_scaffold development tooling.

### Removed

- Drupal 8 and 9 support ([#3597394](https://www.drupal.org/i/3597394)). Both were
  already unreachable, because Decoupled Router 2.0 requires Drupal 10.1 and JSON:API
  Menu Items 1.2 requires Drupal 10.

### Fixed

- Restored Decoupled Router 2.0.7 compatibility
  ([#3618675](https://www.drupal.org/i/3618675)). Without this, `drush cr` aborts and
  the site keeps serving from the old container with the path translator subscribers
  absent.
- Guarded the JSON:API Views route lookup in the Views path translator, so a view
  whose display has no JSON:API Views route no longer returns a 500
  ([#3618677](https://www.drupal.org/i/3618677)).
- Passed the route name to Url::fromRoute as a string in the Views path
  translator ([#3558380](https://www.drupal.org/i/3558380)).

## [1.2.1] - 2025-08-05

### Added

- Wildcard route translator service to catch all available routes
  ([#3315030](https://www.drupal.org/i/3315030)).
- GitLab CI scaffolding ([#3539681](https://www.drupal.org/i/3539681)).

### Changed

- Applied automated Drupal 11 compatibility fixes
  ([#3429976](https://www.drupal.org/i/3429976)).

### Fixed

- Undefined array warning ([#3467742](https://www.drupal.org/i/3467742)).
- Error calling isInternal() on null in druxt.install
  ([#3249293](https://www.drupal.org/i/3249293)).

## [1.2.0] - 2023-02-07

### Added

- Drupal 10 compatibility.

### Fixed

- Cannot load the `view` entity with a NULL ID
  ([#3315035](https://www.drupal.org/i/3315035)).

## [1.1.1] - 2021-10-25

### Fixed

- Decoupled Views routes returning a `null` label on Drupal 9.2
  ([#3245544](https://www.drupal.org/i/3245544)).

## [1.1.0] - 2021-10-23

### Added

- Access to the `jsonapi_resource_config--jsonapi_resource_config` endpoint
  ([#3244770](https://www.drupal.org/i/3244770)).
- Status report for required JSON:API resources
  ([#3232542](https://www.drupal.org/i/3232542)).
- The `configurable_language` endpoint
  ([#3206487](https://www.drupal.org/i/3206487)).

## [1.0.2] - 2021-06-07

### Fixed

- Configuration synchronisation issue
  ([#3216865](https://www.drupal.org/i/3216865)).

## [1.0.1] - 2021-06-02

### Fixed

- Install error with EntityDisplay and non-fieldable entity types
  ([#3212953](https://www.drupal.org/i/3212953)).

## [1.0.0] - 2021-05-01

### Added

- Missing EntityViewDisplays ([#3211751](https://www.drupal.org/i/3211751)).
- Decoupled Router path translator for the Contact form.

## [1.0.0-beta2] - 2021-03-07

### Added

- Exposed `menu--menu` resource attributes
  ([#3202067](https://www.drupal.org/i/3202067)).

## [1.0.0-beta1] - 2021-02-08

### Added

- Dependency on the JSON:API Views module.

## [0.4.1] - 2020-10-15

### Changed

- Updated the Decoupled Router integration, documentation and CI.

## [0.4.0] - 2020-09-08

### Added

- Dependency on the JSON:API Menu Items module.

## [0.3.0] - 2020-08-24

### Added

- Default CORS support.

## [0.2.0] - 2020-08-12

### Added

- Drupal 9 support.

## [0.1.0] - 2020-07-25

### Added

- Initial release: a single permission for read-only access to the JSON:API
  resources Druxt needs, Views route support via JSON:API Views and Decoupled
  Router, and Condition plugin bypass for Block resources.

[Unreleased]: https://git.drupalcode.org/project/druxt/-/compare/1.2.2...1.2.x
[1.2.2]: https://git.drupalcode.org/project/druxt/-/compare/1.2.1...1.2.2
[1.2.1]: https://git.drupalcode.org/project/druxt/-/compare/1.2.0...1.2.1
[1.2.0]: https://git.drupalcode.org/project/druxt/-/compare/1.1.1...1.2.0
[1.1.1]: https://git.drupalcode.org/project/druxt/-/compare/1.1.0...1.1.1
[1.1.0]: https://git.drupalcode.org/project/druxt/-/compare/1.0.2...1.1.0
[1.0.2]: https://git.drupalcode.org/project/druxt/-/compare/1.0.1...1.0.2
[1.0.1]: https://git.drupalcode.org/project/druxt/-/compare/1.0.0...1.0.1
[1.0.0]: https://git.drupalcode.org/project/druxt/-/compare/1.0.0-beta2...1.0.0
[1.0.0-beta2]: https://git.drupalcode.org/project/druxt/-/compare/1.0.0-beta1...1.0.0-beta2
[1.0.0-beta1]: https://git.drupalcode.org/project/druxt/-/compare/0.4.1...1.0.0-beta1
[0.4.1]: https://git.drupalcode.org/project/druxt/-/compare/0.4.0...0.4.1
[0.4.0]: https://git.drupalcode.org/project/druxt/-/compare/0.3.0...0.4.0
[0.3.0]: https://git.drupalcode.org/project/druxt/-/compare/0.2.0...0.3.0
[0.2.0]: https://git.drupalcode.org/project/druxt/-/compare/0.1.0...0.2.0
[0.1.0]: https://git.drupalcode.org/project/druxt/-/tags/0.1.0
