<?php

namespace Drupal\druxt_connect\Plugin\Condition;

use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Provides a 'Request Path' condition.
 */
class DruxtConnectRequestPath extends RequestPath {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Convert path to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $pages = mb_strtolower($this->configuration['pages']);
    if (!$pages) {
      return TRUE;
    }

    $request = $this->requestStack->getCurrentRequest();
    // Compare the lowercase path alias (if any) and internal path.
    $path = $this->currentPath->getPath($request);
    // Allow druxt-router-path header to set the path.
    if ($request->headers->get('druxt-request-path')) {
      $path = $request->headers->get('druxt-request-path');
      // Check if path is locale prefixed.
      $prefix = "/{$request->getLocale()}";
      if (strpos($path, $prefix) === 0 && strlen($prefix) > 1) {
        $path = substr($path, strlen($prefix));
      }
    }
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

    return $this->pathMatcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages));
  }

}
