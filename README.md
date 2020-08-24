# Druxt

[![CircleCI](https://circleci.com/gh/druxt/druxt_drupal.svg?style=svg)](https://circleci.com/gh/druxt/druxt_drupal)

> Provides an easy connection from Drupal to a [Druxt.js](https://druxtjs.org) Nuxt frontend using Drupal's JSON API.

## Features

- A single permission for read-only access to all JSON API resources required for Druxt.js.
- Support for Views routes via the [JSON:API Views](https://www.drupal.org/project/jsonapi_views) and [Decoupled Router](https://www.drupal.org/project/decoupled_router) modules.
- Condition plugin bypass for Block resources.
- Enables Cross-Origin Resource Sharing (CORS) support.


## Installation

Druxt.js requires a Nuxt.js frontend and a Drupal JSON:API backend:

### Drupal

1. [Install Drupal](https://www.drupal.org/docs/installing-drupal)

2. Download the Drupal [Druxt module](https://www.drupal.org/project/druxt):

    ```sh
    composer require drupal/druxt
    ```

3. Install the Druxt.js module.

4. Add the "**access druxt resources**" permission to a user/role.


### Nuxt.js


1. [Install Nuxt.js](https://nuxtjs.org/guide/installation/)

2. Install the Nuxt.js [Druxt module](http://npmjs.com/package/druxt):

    ```sh
    npm i druxt
    ```

3. Add the module and configuration to `nuxt.config.js`:

    ```js
    module.exports = {
      modules: [
        'druxt'
      ],

      druxt: {
        baseUrl: 'https://example.com'
      }
    }
    ```
