<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

/**
 * Field handler for 'image' fields.
 */
class ImageHandler extends FileHandler {

  /**
   * {@inheritdoc}
   */
  protected function getItemProperties(array $record): array {
    return ['alt' => $record['alt'] ?? NULL, 'title' => $record['title'] ?? NULL];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldLabel(): string {
    return 'Image';
  }

}
