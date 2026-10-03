<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

/**
 * Field handler for 'entity_reference' fields.
 */
class EntityReferenceHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $target = $this->getReferenceTarget();
    $resolved = [];

    foreach ($records as $record) {
      if (!array_key_exists($this->mainProperty, $record)) {
        throw new \RuntimeException(sprintf('Entity reference record is missing the main property "%s".', $this->mainProperty));
      }

      $lookup = $record[$this->mainProperty];

      // An integer id is already resolved; only a string lookup requires an
      // entity query.
      if (is_int($lookup)) {
        $resolved[] = $record;
        continue;
      }

      $record[$this->mainProperty] = $this->resolveTargetId($lookup, $target);
      $resolved[] = $record;
    }

    return $resolved;
  }

  /**
   * Reads the entity-type facts the field's lookups resolve against.
   *
   * @return \DrevOps\BehatSteps\Backend\Core\Field\ReferenceTarget
   *   The target facts.
   *
   * @throws \RuntimeException
   *   When the target entity type declares no id key.
   */
  protected function getReferenceTarget(): ReferenceTarget {
    $entity_type_id = $this->fieldInfo->getSetting('target_type');
    $definition = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $id_key = $definition->getKey('id');

    if (!is_string($id_key)) {
      throw new \RuntimeException(sprintf("Cannot resolve a reference to '%s' because it declares no id key.", $entity_type_id));
    }

    // User entities return FALSE for getKey('label'), so 'name' is used
    // directly.
    $label_key = $entity_type_id !== 'user' ? $definition->getKey('label') : 'name';
    $label_key = $label_key === FALSE ? NULL : $label_key;

    $bundles = $this->getTargetBundles();
    $bundle_key = $bundles ? $definition->getKey('bundle') : NULL;
    $bundle_key = $bundle_key === FALSE ? NULL : $bundle_key;

    return new ReferenceTarget($entity_type_id, $id_key, $label_key, $bundles, $bundle_key);
  }

  /**
   * Resolves a lookup value to the id of an entity the field may target.
   *
   * @param mixed $lookup
   *   An entity label, or an entity id Drupal serialised as a string.
   * @param \DrevOps\BehatSteps\Backend\Core\Field\ReferenceTarget $target
   *   The entity-type facts to resolve against.
   *
   * @return int|string
   *   The id of the first matching entity.
   *
   * @throws \RuntimeException
   *   When nothing matches the lookup.
   */
  protected function resolveTargetId(mixed $lookup, ReferenceTarget $target): int|string {
    $query = \Drupal::entityQuery($target->entityTypeId);
    $query->accessCheck(FALSE);

    if ($target->labelKey) {
      // A numeric-string lookup is ambiguous: an entity id Drupal serialised
      // as a string, or a label made of digits. An OR-group matches either,
      // and the first match is returned.
      $is_numeric_id = is_string($lookup) && ctype_digit($lookup);
      $or = $query->orConditionGroup();

      if ($is_numeric_id) {
        $or->condition($target->idKey, (int) $lookup);
      }

      $or->condition($target->labelKey, $lookup);
      $query->condition($or);
    }
    else {
      $query->condition($target->idKey, $lookup);
    }

    if ($target->bundles && $target->bundleKey) {
      $query->condition($target->bundleKey, $target->bundles, 'IN');
    }

    $entities = $query->execute();

    if (!$entities) {
      throw new \RuntimeException(sprintf("No entity '%s' of type '%s' exists.", $lookup, $target->entityTypeId));
    }

    return array_shift($entities);
  }

  /**
   * Returns bundle restrictions configured on the field, or NULL.
   *
   * @return array<int|string, string>|null
   *   Bundle names the field may target, or NULL when unrestricted.
   */
  protected function getTargetBundles(): ?array {
    $settings = $this->fieldConfig->getSettings();

    if (!empty($settings['handler_settings']['target_bundles'])) {
      return $settings['handler_settings']['target_bundles'];
    }

    return NULL;
  }

}
