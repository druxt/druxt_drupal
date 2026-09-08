# DruxtJS

[![Pipeline](https://git.drupalcode.org/project/druxt/badges/1.3.x/pipeline.svg)](https://git.drupalcode.org/project/druxt/-/pipelines)
[![Test](https://github.com/druxt/druxt_drupal/actions/workflows/test.yml/badge.svg?branch=1.3.x)](https://github.com/druxt/druxt_drupal/actions/workflows/test.yml?query=branch%3A1.3.x)
[![Coverage](https://codecov.io/gh/druxt/druxt_drupal/branch/1.3.x/graph/badge.svg)](https://codecov.io/gh/druxt/druxt_drupal/branch/1.3.x)

A bridge between frameworks, Drupal in the back, Nuxt.js in the front.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/druxt).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/druxt).


## Table of contents

- Requirements
- Installation
- Configuration
- Features
- Exposing additional resources
- Maintainers


## Requirements

This module requires the following modules:

- [Decoupled Router](https://www.drupal.org/project/decoupled_router)
- [JSON:API](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module)
- [JSON:API Menu Items](https://www.drupal.org/project/jsonapi_menu_items)
- [JSON:API Views](https://www.drupal.org/project/jsonapi_views)


## Installation

DruxtJS requires a Nuxt.js frontend and a Drupal JSON:API backend.

### Drupal

1. [Install Drupal](https://www.drupal.org/docs/installing-drupal).
2. Download the Drupal [DruxtJS module](https://www.drupal.org/project/druxt):
   ```sh
   composer require drupal/druxt
   ```
3. Install the DruxtJS module.
4. Add the "**access druxt resources**" permission to a user/role.

### Nuxt.js

1. [Install Nuxt.js](https://nuxtjs.org/guide/installation/).
2. Install the Nuxt.js [DruxtJS Site module](https://www.npmjs.com/package/druxt-site):
   ```sh
   npm i druxt-site
   ```
3. Add the module and configuration to `nuxt.config.js`:
   ```js
   module.exports = {
     modules: [
       'druxt-site'
     ],
     druxt: {
       baseUrl: 'https://cms.example.com'
     }
   }
   ```


## Configuration

Once installed, DruxtJS requires no additional configuration. The "**access
druxt resources**" permission provides read-only access to all JSON:API
resources required by the DruxtJS frontend.


## Features

- A single permission for read-only access to all JSON:API resources required by DruxtJS.
- Support for Views routes via the [JSON:API Views](https://www.drupal.org/project/jsonapi_views) and [Decoupled Router](https://www.drupal.org/project/decoupled_router) modules.
- Support for Contact form routes via the [Decoupled Router](https://www.drupal.org/project/decoupled_router) module.
- Improved support for Menu items via the [JSON:API Menu Items](https://www.drupal.org/project/jsonapi_menu_items) module.
- Condition plugin bypass for Block resources.
- Enables Cross-Origin Resource Sharing (CORS) support.
- Ensures EntityViewDisplay configuration available for [DruxtSchema](https://druxtjs.org/modules/schema) module.


## Exposing additional resources

Druxt exposes a fixed set of JSON:API resources to anyone holding the
"access druxt resources" permission. A site can change that set at
Configuration > Web services > Druxt, or at
`/admin/config/services/druxt`.

Editing the list needs the "administer druxt" permission, which is separate:
"access druxt resources" controls what a frontend may read at runtime and
does not grant access to the settings page.

Only configuration entities are offered. Everything on the list is readable
by everyone holding the permission, and there is no way to scope it to a
role or a consumer, so a content entity type would expose every entity of
that type including unpublished ones. Configuration carries no per-entity
access and no unpublished state, so a list-wide grant costs little.

`menu_link_content` is the exception. It is a content entity and it shipped
in the list this configuration replaced, so removing it would break existing
sites. It is accepted on impact, a disabled menu link being low risk, rather
than because the rule does not apply to it.

A worked example is `editor--editor`. A frontend that builds its editor
toolbar from the text format's own configuration has to read it, and no
permission short of "administer filters" grants that, which is not something
to give an author because it also lets them edit text formats. Checking
`editor--editor` lets an author's toolbar match what the site is configured
for, and a change to a text format reaches the frontend without a rebuild.
It is not enabled by default: a site that does not render Drupal's toolbars
has no reason to expose the configuration.

A module can add what it needs in code instead, so installing it is all a
site has to do:

```php
/**
 * Implements hook_druxt_resources_alter().
 */
function my_module_druxt_resources_alter(array &$resources): void {
  $resources[] = 'my_module_settings--my_module_settings';
}
```

See `druxt.api.php` for the full documentation. Unlike the settings form,
the hook can expose any resource, because a change there is reviewable code
rather than a checkbox.

## Maintainers

- Stuart Clark - [Deciphered](https://www.drupal.org/u/deciphered)
