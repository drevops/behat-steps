<?php

/**
 * @file
 * Field handler for testing Behat-steps.
 *
 * Only the 'autoload' paths of 'behat.php' load this directory, so the
 * 'drupal.field_handlers' entry naming this class covers a handler Composer
 * cannot load.
 */

declare(strict_types=1);

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Expands a comment status name into the status a comment field stores.
 */
final readonly class CommentStatusHandler implements FieldHandlerInterface {

  /**
   * The stored comment status, keyed by its name.
   */
  protected const array STATUSES = ['hidden' => 0, 'closed' => 1, 'open' => 2];

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityStubInterface $stub, string $entity_type, string $field_name) {}

  /**
   * {@inheritdoc}
   */
  public function expand(mixed $values): array {
    $deltas = [];

    foreach ((array) $values as $value) {
      $name = strtolower(trim((string) (is_array($value) ? ($value['status'] ?? '') : $value)));

      if (!isset(self::STATUSES[$name])) {
        throw new \RuntimeException(sprintf('The comment status "%s" is not one of "%s".', $name, implode('", "', array_keys(self::STATUSES))));
      }

      $deltas[] = ['status' => self::STATUSES[$name]];
    }

    return $deltas;
  }

}
