<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: place, configure and find blocks, and create content blocks.
 *
 * 2 entities sit behind the blocks a scenario sets up:
 *
 *  - A placement, the 'block' config entity, puts a block plugin in a region
 *    of a theme, with its own label and visibility conditions.
 *  - A content block, the 'block_content' content entity, is the reusable
 *    block body that would normally be authored through the block library
 *    UI.
 *
 * Block type ('block_content_type') creation is intentionally out of scope.
 * Like node types and taxonomy vocabularies, it is schema-level setup that
 * belongs in the test site's configuration, not in a scenario step.
 */
interface BlockCapabilityInterface {

  /**
   * Places a block in the default theme.
   *
   * The placement takes the machine name the plugin suggests in the theme,
   * with a numeric suffix when that name is taken.
   *
   * @param string $plugin_id
   *   The block plugin id, such as 'system_powered_by_block' or
   *   'block_content:<uuid>'.
   * @param string $label
   *   The label of the placement.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   A 'block' stub carrying the 'id' of the placement, flagged as saved with
   *   the placement attached.
   */
  public function placeBlock(string $plugin_id, string $label): EntityStubInterface;

  /**
   * Removes a placed block.
   *
   * Does nothing when the placement does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'placeBlock()', or one that carries an 'id'
   *   value resolving to an existing block placement.
   */
  public function deleteBlock(EntityStubInterface $stub): void;

  /**
   * Changes the label, the label display, the region or the status of a block.
   *
   * The block is loaded without overrides, so a value a module or
   * 'settings.php' overrides is not written into the stored configuration.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'placeBlock()', or one that carries an 'id'
   *   value resolving to an existing block placement.
   * @param array<string, mixed> $configuration
   *   The values to change: 'label' (a string), 'label_display' (a boolean),
   *   'region' (a string) and 'status' (a boolean). A value the array leaves
   *   out keeps what the block holds, and any other key is ignored.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no block.
   */
  public function configureBlock(EntityStubInterface $stub, array $configuration): void;

  /**
   * Sets a visibility condition on a block, replacing the one it holds.
   *
   * The block is loaded without overrides, as on configure.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'placeBlock()', or one that carries an 'id'
   *   value resolving to an existing block placement.
   * @param string $condition
   *   The condition plugin id, such as 'request_path'.
   * @param array<string, mixed> $configuration
   *   The condition configuration. An empty configuration leaves the
   *   condition with its defaults.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no block.
   */
  public function setBlockVisibility(EntityStubInterface $stub, string $condition, array $configuration): void;

  /**
   * Removes a visibility condition from a block.
   *
   * The block is loaded without overrides, as on configure, and a block that
   * does not hold the condition is left unchanged.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'placeBlock()', or one that carries an 'id'
   *   value resolving to an existing block placement.
   * @param string $condition
   *   The condition plugin id, such as 'request_path'.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no block.
   */
  public function unsetBlockVisibility(EntityStubInterface $stub, string $condition): void;

  /**
   * Returns the region a block is placed in.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'placeBlock()', or one that carries an 'id'
   *   value resolving to an existing block placement.
   *
   * @return string
   *   The region, or an empty string for a block placed in none.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no block.
   */
  public function getBlockRegion(EntityStubInterface $stub): string;

  /**
   * Finds the ids of the blocks in the default theme that carry a label.
   *
   * @param string $label
   *   The visible label of the blocks, with configuration overrides applied.
   *
   * @return array<int, string>
   *   The ids of the blocks in natural order, so a machine name with a higher
   *   suffix comes later. Empty when no block carries the label.
   */
  public function findBlockIdsByLabel(string $label): array;

  /**
   * Creates a content block.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Content block stub. The 'bundle' property selects the block-content
   *   bundle; values map to fields on that bundle ('info', 'body', custom
   *   fields, etc.).
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the saved 'block_content'
   *   entity attached.
   */
  public function createBlockContent(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a content block.
   *
   * Does nothing when the content block does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned by 'createBlockContent()', or one that carries
   *   the entity type's id key resolving to an existing content block.
   */
  public function deleteBlockContent(EntityStubInterface $stub): void;

  /**
   * Finds the id of the block plugin with an admin label.
   *
   * @param string $admin_label
   *   The admin label of the block plugin.
   *
   * @return string|null
   *   The plugin id, or NULL when no block plugin has the admin label.
   */
  public function findBlockPluginId(string $admin_label): ?string;

  /**
   * Whether a condition plugin exists.
   *
   * @param string $id
   *   The condition plugin id, such as 'request_path'.
   */
  public function hasConditionPlugin(string $id): bool;

}
