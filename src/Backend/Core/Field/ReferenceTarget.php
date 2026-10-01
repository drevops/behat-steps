<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

/**
 * Immutable entity-type facts a reference field resolves its lookups against.
 *
 * Read once per expansion so a field holding several deltas derives the entity
 * type definition a single time.
 */
final readonly class ReferenceTarget {

  /**
   * Constructs a ReferenceTarget.
   *
   * @param string $entityTypeId
   *   The entity type the field references.
   * @param string $idKey
   *   The key the entity type stores its id under.
   * @param string|null $labelKey
   *   The key holding the label, or NULL when the entity type declares none.
   * @param array<int|string, string>|null $bundles
   *   Bundles the field may target, or NULL when unrestricted.
   * @param string|null $bundleKey
   *   The key the entity type stores its bundle under, or NULL when the
   *   entity type declares none or the field restricts no bundles.
   */
  public function __construct(
    public string $entityTypeId,
    public string $idKey,
    public ?string $labelKey,
    public ?array $bundles,
    public ?string $bundleKey,
  ) {
  }

}
