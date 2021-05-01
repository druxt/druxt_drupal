# DruxtJS

[![CircleCI](https://circleci.com/gh/druxt/druxt_drupal.svg?style=svg)](https://circleci.com/gh/druxt/druxt_drupal)

> A bridge between frameworks, Drupal in the back, Nuxt.js in the front.

## Features

- A single permission for read-only access to all JSON:API resources required by DruxtJS.
- Support for Views routes via the [JSON:API Views](https://www.drupal.org/project/jsonapi_views) and [Decoupled Router](https://www.drupal.org/project/decoupled_router) modules.
- Support for Contact form routes via the [Decoupled Router](https://www.drupal.org/project/decoupled_router) module.
- Improved support for Menu items via the [JSON:API Menu Items](https://www.drupal.org/project/jsonapi_menu_items) module.
- Condition plugin bypass for Block resources.
- Enables Cross-Origin Resource Sharing (CORS) support.
- Ensures EntityViewDisplay configuration available for [DruxtSchema](https://schema.druxtjs.org) module.


## Installation

DruxtJS requires a Nuxt.js frontend and a Drupal JSON:API backend:

### Drupal

1. [Install Drupal](https://www.drupal.org/docs/installing-drupal)

2. Download the Drupal [DruxtJS module](https://www.drupal.org/project/druxt):

    ```sh
    composer require drupal/druxt
    ```

3. Install the DruxtJS module.

4. Add the "**access druxt resources**" permission to a user/role.


### Nuxt.js


1. [Install Nuxt.js](https://nuxtjs.org/guide/installation/)

2. Install the Nuxt.js [DruxtJS Site module](http://npmjs.com/package/druxt-site):

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
        baseUrl: 'https://demo-api.druxtjs.org'
      }
    }
    ```
