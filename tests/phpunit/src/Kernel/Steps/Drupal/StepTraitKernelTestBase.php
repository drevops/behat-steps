<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Tests\Kernel\Fixtures\BootedDrupalBackend;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for kernel tests calling the helpers of the Drupal step traits.
 *
 * A helper resolves a capability before it acts, so the context holds a
 * Drupal backend whose 'Core' reads and writes the kernel this test booted.
 * A helper that asserts its prerequisites therefore matches the modules the
 * test enabled.
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

    $this->context = $this->createContext();
  }

  /**
   * Creates a context whose backend reads and writes the booted kernel.
   *
   * @param array<string, array<string, mixed>> $config
   *   The trait options, keyed by trait group.
   */
  protected function createContext(array $config = []): DrupalContext {
    $backend = new BootedDrupalBackend($this->root, 'default');

    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);

    $context = new DrupalContext($config);
    $context->setBackendRegistry($backend_registry);

    return $context;
  }

  /**
   * Asserts that a set holds a stub for exactly the expected entities.
   *
   * @param array<int, \Drupal\Core\Entity\EntityInterface> $expected
   *   The entities the set must hold a stub for, in any order.
   * @param array<mixed> $actual
   *   The set a helper returned.
   * @param string $id_key
   *   The key each stub carries its id under.
   */
  protected function assertStubSet(array $expected, array $actual, string $id_key): void {
    // IDs compare as strings, so a config entity keyed by machine name and a
    // content entity keyed by integer are handled alike.
    $expected_ids = array_map(static fn(EntityInterface $entity): string => (string) $entity->id(), $expected);
    sort($expected_ids);

    $actual_ids = array_map(strval(...), array_keys($actual));
    sort($actual_ids);

    $this->assertSame($expected_ids, $actual_ids, 'The set holds only the matching entities.');

    $entity_types = array_unique(array_map(static fn(EntityInterface $entity): string => $entity->getEntityTypeId(), $expected));

    foreach ($actual as $id => $stub) {
      $this->assertInstanceOf(EntityStubInterface::class, $stub);
      $this->assertSame($entity_types, [$stub->getEntityType()], 'Each stub names the entity type of the set.');
      $this->assertSame((string) $id, (string) $stub->getValue($id_key), 'Each stub is keyed by the id it carries.');
      $this->assertFalse($stub->isSaved(), 'A stub from a lookup carries no loaded entity.');
    }
  }

}
