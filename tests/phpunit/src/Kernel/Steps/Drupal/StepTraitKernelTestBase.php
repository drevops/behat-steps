<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistry;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for kernel tests calling the helpers of the Drupal step traits.
 *
 * A helper resolves 'CoreCapabilityInterface' before it reads Drupal, so the
 * context holds a driver double that reports itself bootstrapped. The helper
 * then reads the kernel this test booted.
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

    $driver = $this->createStub(DrupalDriverInterface::class);
    $driver->method('isBootstrapped')->willReturn(TRUE);

    $driver_registry = new DriverRegistry(['test' => $driver]);
    $driver_registry->setScenarioDrivers(['test' => 'test']);

    $this->context = new DrupalContext();
    $this->context->setDriverRegistry($driver_registry);
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
    $expected_ids = array_map(static fn(EntityInterface $entity): int => (int) $entity->id(), $expected);
    sort($expected_ids);

    $actual_ids = array_keys($actual);
    sort($actual_ids);

    $this->assertSame($expected_ids, $actual_ids, 'The set holds only the matching entities.');

    foreach ($actual as $id => $entity) {
      $this->assertInstanceOf($interface, $entity);
      $this->assertSame($id, (int) $entity->id(), 'Each entity is keyed by its own ID.');
    }
  }

}
