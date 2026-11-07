<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

/**
 * Field handler for 'color_field_type' fields.
 *
 * @see https://www.drupal.org/project/color_field
 */
class ColorFieldTypeHandler extends AbstractHandler {

  public const string PROPERTY_COLOR = 'color';

  public const string PROPERTY_OPACITY = 'opacity';

  /**
   * {@inheritdoc}
   */
  protected function normalize(mixed $values): array {
    if ($this->isEmptyValue($values)) {
      return [];
    }

    if (!is_array($values) || !array_is_list($values)) {
      return [$this->normalizeDelta($values)];
    }

    $records = [];

    foreach ($values as $value) {
      if ($this->isEmptyValue($value)) {
        continue;
      }

      $records[] = $this->normalizeDelta($value);
    }

    return $records;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $opacity_enabled = (bool) ($this->fieldConfig->getSettings()[self::PROPERTY_OPACITY] ?? TRUE);
    $expanded = [];

    foreach ($records as $record) {
      $color = $record[self::PROPERTY_COLOR] ?? NULL;
      $opacity = $record[self::PROPERTY_OPACITY] ?? NULL;

      if ($color === '') {
        $color = NULL;
      }

      if ($opacity === '') {
        $opacity = NULL;
      }

      if ($color === NULL && $opacity === NULL) {
        continue;
      }

      if ($color === NULL) {
        throw new \RuntimeException(sprintf('The color field record sets the opacity "%s" but no color.', $opacity));
      }

      if ($opacity !== NULL && !$opacity_enabled) {
        throw new \RuntimeException(sprintf('Cannot set the opacity "%s" because opacity is not recorded on this field.', $opacity));
      }

      $expanded[] = [
        self::PROPERTY_COLOR => $color,
        self::PROPERTY_OPACITY => $opacity,
      ];
    }

    return $expanded;
  }

  /**
   * Folds 1 color value into a keyed record.
   *
   * @param mixed $value
   *   A hex color string or a record keyed by 'color' and 'opacity'.
   *
   * @return array<string, mixed>
   *   A record keyed by 'color' and 'opacity'.
   */
  protected function normalizeDelta(mixed $value): array {
    if (is_string($value)) {
      return [self::PROPERTY_COLOR => $value];
    }

    if (!is_array($value)) {
      throw new \RuntimeException(sprintf('Color field value must be a string or an array. Got %s.', get_debug_type($value)));
    }

    foreach (array_keys($value) as $key) {
      if ($key !== self::PROPERTY_COLOR && $key !== self::PROPERTY_OPACITY) {
        throw new \RuntimeException(sprintf('Invalid color sub-field key "%s". Use "color" and "opacity".', $key));
      }
    }

    return $value;
  }

  /**
   * Whether a value leaves the field or the delta unset.
   */
  protected function isEmptyValue(mixed $value): bool {
    return $value === NULL || $value === '' || $value === [];
  }

}
