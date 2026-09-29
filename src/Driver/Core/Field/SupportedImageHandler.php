<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Core\Field;

/**
 * Field handler for 'supported_image' fields (supported_image contrib module).
 *
 * Uploads each path the same way a file field does, then records the caption
 * and attribution properties the field adds on top.
 *
 * @see https://www.drupal.org/project/supported_image
 */
class SupportedImageHandler extends FileHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $images = [];

    foreach ($records as $record) {
      $file = $this->uploadAndSave($record[$this->mainProperty]);

      $images[] = [
        $this->mainProperty => $this->fileId($file),
        'alt' => $record['alt'] ?? NULL,
        'title' => $record['title'] ?? NULL,
        'caption_value' => $record['caption_value'] ?? NULL,
        'caption_format' => $record['caption_format'] ?? NULL,
        'attribution_value' => $record['attribution_value'] ?? NULL,
        'attribution_format' => $record['attribution_format'] ?? NULL,
      ];
    }

    return $images;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldLabel(): string {
    return 'Supported image';
  }

}
