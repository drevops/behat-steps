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
   * @Given managed file:
   */
  public function fileCreateManaged(TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $node = (object) $nodeHash;

      if (empty($node->path)) {
        throw new \RuntimeException('"path" property is required');
      }
      $path = ltrim($node->path, '/');

      // Limited support for remote files: all remote files are considered
      // oembed objects and therefore only oembed'able objects will be saved.
      if (parse_url($node->path, PHP_URL_SCHEME) !== NULL) {
        $provider = media_internet_get_provider($path);
        $file = $provider->save();
      }
      // Local file.
      else {
        // Get fixture file path.
        if ($this->getMinkParameter('files_path')) {
          $full_path = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
          if (is_file($full_path)) {
            $path = $full_path;
          }
        }

        if (!is_readable($path)) {
          throw new \RuntimeException('Unable to find file ' . $path);
        }

        $destination = 'public://' . basename($path);
        $file = file_save_data(file_get_contents($path), $destination, FILE_EXISTS_REPLACE);
      }

      if (!$file) {
        throw new \RuntimeException('Unable to save managed file ' . $path);
      }
    }
  }

}
