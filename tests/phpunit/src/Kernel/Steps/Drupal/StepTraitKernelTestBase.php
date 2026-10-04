<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for kernel tests calling the helpers of the Drupal step traits.
 *
 * A helper resolves 'CoreCapabilityInterface' before it reads Drupal, so the
 * context holds a backend double that reports itself bootstrapped. The helper
 * then reads the kernel this test booted.
 *
 * The double reports module state from that kernel, so a helper that asserts
 * its prerequisites matches the modules the test enabled.
 */
#[RunTestsInSeparateProcesses]
abstract class StepTraitKernelTestBase extends KernelTestBase {

  /**
   * The context composing every Drupal step trait.
   */
  protected DrupalContext $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('isBootstrapped')->willReturn(TRUE);
    $backend->method('moduleIsEnabled')->willReturnCallback(static fn(string $module_name): bool => \Drupal::moduleHandler()->moduleExists($module_name));

    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);

    $this->context = new DrupalContext();
    $this->context->setBackendRegistry($backend_registry);
  }

  /**
   * Asserts that a loaded set holds exactly the expected entities.
   *
   * @param array<int, \Drupal\Core\Entity\EntityInterface> $expected
   *   The entities the set must hold, in any order.
   * @param array<mixed> $actual
   *   The set a helper returned.
   * @param class-string<\Drupal\Core\Entity\EntityInterface> $interface
   *   The interface every entity in the set implements.
   */
  protected function assertLoadedSet(array $expected, array $actual, string $interface): void {
    // IDs compare as strings, so a config entity keyed by its machine name
    // reads the same way as a content entity keyed by an integer.
    $expected_ids = array_map(static fn(EntityInterface $entity): string => (string) $entity->id(), $expected);
    sort($expected_ids);

    $actual_ids = array_map(strval(...), array_keys($actual));
    sort($actual_ids);

    $this->assertSame($expected_ids, $actual_ids, 'The set holds only the matching entities.');

    foreach ($actual as $id => $entity) {
      $this->assertInstanceOf($interface, $entity);
      $this->assertSame((string) $id, (string) $entity->id(), 'Each entity is keyed by its own ID.');
    }
  }

}
