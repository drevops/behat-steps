<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

/**
 * Field handler for 'supported_image' fields (supported_image contrib module).
 *
 * Adds the caption and attribution properties the field stores on top of an
 * image item.
 *
 * @see https://www.drupal.org/project/supported_image
 */
class SupportedImageHandler extends ImageHandler {

  /**
   * {@inheritdoc}
   */
  protected function getItemProperties(array $record): array {
    return parent::getItemProperties($record) + [
      'caption_value' => $record['caption_value'] ?? NULL,
      'caption_format' => $record['caption_format'] ?? NULL,
      'attribution_value' => $record['attribution_value'] ?? NULL,
      'attribution_format' => $record['attribution_format'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldLabel(): string {
    return 'Supported image';
  }

}
