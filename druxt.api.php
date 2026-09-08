<?php

/**
 * @file
 * Hooks provided by the Druxt module.
 */

declare(strict_types=1);

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters the JSON:API resources Druxt exposes.
 *
 * A resource on this list is readable by anyone holding the "access druxt
 * resources" permission, whatever the site would otherwise allow, because
 * druxt_entity_access() returns an allowed result and
 * druxt_jsonapi_entity_filter_access() lifts the filtering restriction for
 * the request. Add a resource only when a decoupled frontend needs it to
 * render what Drupal intended.
 *
 * Use this hook when a module knows which resource it needs, so a site
 * builder does not have to find the setting and tick a box. The settings
 * form offers configuration entities only, because the list is granted to
 * everyone holding the permission with no way to scope it to a role or a
 * consumer. This hook has no such limit, since a change here is reviewable
 * code rather than a checkbox: adding a content entity type exposes every
 * entity of that type to anyone holding the permission, which is commonly
 * granted to anonymous.
 *
 * @param string[] $resources
 *   Resource type ids, as they appear in a JSON:API route, in the form
 *   "entity_type--bundle".
 */
function hook_druxt_resources_alter(array &$resources): void {
  // A module that a frontend reads configuration from adds its own resource,
  // so installing the module is all a site has to do.
  $resources[] = 'my_module_settings--my_module_settings';

  // A distribution may remove one it does not want exposed.
  $resources = array_values(array_diff($resources, ['menu_link_content--menu_link_content']));
}

/**
 * @} End of "addtogroup hooks".
 */
