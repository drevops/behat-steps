<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;

/**
 * Reads Drupal state a step asserts on without going through a driver.
 *
 * Both members run in the site's own process, so a step calling one resolves
 * 'CoreCapabilityInterface' first.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait QueryTrait {

  /**
   * Load the ids of the nodes of a content type matching the conditions.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  public function queryNodeIds(string $content_type, array $conditions = []): array {
    $this->driverFor(CoreCapabilityInterface::class);

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $content_type);

    foreach ($conditions as $field => $value) {
      $and = $query->andConditionGroup();
      $and->condition($field, $value);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Assert that a module backing a set of steps is enabled.
   *
   * Without the check, a step against a missing module fails with a fatal on
   * an unresolvable class or a raw database error, not a message naming the
   * module.
   *
   * @param string $module
   *   The module machine name.
   * @param string $package
   *   Optional Composer package to name in the message. Pass an empty string
   *   for a module that ships with Drupal core.
   *
   * @throws \RuntimeException
   *   When the module is not enabled.
   */
  public function queryAssertModuleEnabled(string $module, string $package = ''): void {
    $this->driverFor(CoreCapabilityInterface::class);

    // @codeCoverageIgnoreStart
    if (\Drupal::moduleHandler()->moduleExists($module)) {
      return;
    }

    $remedy = $package === ''
      ? 'Enable it as part of the site setup; it ships with Drupal core.'
      : sprintf('Add "%s" to the consumer project\'s composer.json and enable the module as part of the site setup.', $package);

    throw new \RuntimeException(sprintf('The "%s" module is not enabled. %s', $module, $remedy));
    // @codeCoverageIgnoreEnd
  }

}
