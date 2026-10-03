<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * Field handler for 'entity_reference_revisions' fields (Paragraphs et al).
 *
 * The handler resolves the target as 'EntityReferenceHandler' does, then
 * records the target's revision id beside the id.
 */
class EntityReferenceRevisionsHandler extends EntityReferenceHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $target_facts = $this->getReferenceTarget();
    $storage = \Drupal::entityTypeManager()->getStorage($target_facts->entityTypeId);
    $resolved = [];

    foreach ($records as $record) {
      if (!array_key_exists($this->mainProperty, $record)) {
        throw new \RuntimeException(sprintf('Entity reference revisions record is missing the main property "%s".', $this->mainProperty));
      }

      $lookup = $record[$this->mainProperty];
      $resolved_id = is_int($lookup) ? $lookup : $this->resolveTargetId($lookup, $target_facts);

      $target = $storage->load($resolved_id);

      if ($target === NULL) {
        throw new \RuntimeException(sprintf('Entity "%s" of type "%s" no longer exists.', $resolved_id, $target_facts->entityTypeId));
      }

      // 'resolveTargetId()' filters by bundle, but an integer lookup bypasses
      // it and loads directly, so the loaded target is checked here.
      if ($target_facts->bundles && $target instanceof EntityInterface && !in_array($target->bundle(), $target_facts->bundles, TRUE)) {
        throw new \RuntimeException(sprintf('Entity "%s" of type "%s" is of bundle "%s", which the field does not accept. Allowed: %s.', $resolved_id, $target_facts->entityTypeId, $target->bundle(), implode(', ', $target_facts->bundles)));
      }

      $record[$this->mainProperty] = $resolved_id;

      if (!array_key_exists('target_revision_id', $record)) {
        $record['target_revision_id'] = $target instanceof RevisionableInterface ? $target->getRevisionId() : NULL;
      }

      $resolved[] = $record;
    }

    return $resolved;
  }

}
