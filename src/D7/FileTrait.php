<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait FileTrait.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait FileTrait {

  /**
   * Create managed file from existing local file.
   *
   * @Given managed file:
   */
  public function fileCreateManaged(TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;

      if (empty($node->path)) {
        throw new \RuntimeException('"path" property is required');
      }

      // Limited support for remote files: all remote files are considered
      // oembed objects and therefore only oembed'able objects will be saved.
      if (parse_url($node->path, PHP_URL_SCHEME) !== NULL) {
        $provider = media_internet_get_provider($node->path);
        $file = $provider->save();
      }
      // Local file.
      else {
        $file_path = dirname(dirname(__FILE__)) . '/' . ltrim($node->path, '/');
        $destination = 'public://' . basename($file_path);
        if (!is_readable($file_path)) {
          throw new \RuntimeException('Unable to find file ' . $file_path);
        }
        $file = file_save_data(file_get_contents($file_path), $destination, FILE_EXISTS_REPLACE);
      }

      if (!$file) {
        throw new \RuntimeException('Unable to save managed file ' . $file_path);
      }
    }
  }

}
