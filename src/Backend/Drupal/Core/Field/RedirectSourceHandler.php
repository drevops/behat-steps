<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

/**
 * Field handler for 'redirect_source' fields (redirect contrib module).
 *
 * A record holds the source 'path' and its 'query' map. The path is stored
 * without its leading slash, as 'Redirect::setSource()' stores it, and a
 * query given as a string is parsed into the map.
 *
 * @see https://www.drupal.org/project/redirect
 */
class RedirectSourceHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $sources = [];

    foreach ($records as $record) {
      $query = $record['query'] ?? [];

      if (is_string($query)) {
        parse_str(ltrim($query, '?'), $query);
      }

      $sources[] = [
        'path' => ltrim((string) $record['path'], '/'),
        'query' => $query,
      ];
    }

    return $sources;
  }

}
