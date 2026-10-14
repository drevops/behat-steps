<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Helper\Drupal;

use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\TermCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures\RegistryExposingContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Kernel test for resolving a vocabulary label to its machine name.
 */
#[CoversTrait(EntityLifecycleTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class EntityLifecycleTraitVocabularyKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['taxonomy', 'text', 'user', 'field', 'system'];

  /**
   * The context under test.
   */
  protected RegistryExposingContext $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Vocabulary::create(['vid' => 'topics', 'name' => 'Topic areas'])->save();

    $this->context = new RegistryExposingContext();
    $this->context->setBackendRegistry($this->createBackendRegistry($this->createInProcessBackend()));
    $this->context->setHookDispatcher(new HookDispatcher(new HookRepository(new EnvironmentManager()), new CallCenter()));
  }

  /**
   * Tests how a vocabulary identifier resolves to a machine name.
   *
   * An unknown identifier is returned unchanged for the backend to reject.
   *
   * @param string $identifier
   *   The machine name or label naming the vocabulary.
   * @param string $expected
   *   The machine name it resolves to.
   */
  #[DataProvider('dataProviderResolveVocabularyMachineName')]
  public function testResolveVocabularyMachineName(string $identifier, string $expected): void {
    $this->assertSame($expected, $this->context->callResolveVocabularyMachineName($identifier));
  }

  public static function dataProviderResolveVocabularyMachineName(): array {
    return [
      'a machine name resolves to itself' => ['tags', 'tags'],
      'a label resolves to its machine name' => ['Tags', 'tags'],
      'a label of another vocabulary resolves to its machine name' => ['Topic areas', 'topics'],
      'an unknown identifier is returned unchanged' => ['Unknown', 'Unknown'],
    ];
  }

  public function testTermCreationResolvesTheVocabularyLabel(): void {
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'vocabulary_machine_name' => 'Tags']);

    $backend = $this->createInProcessBackend();
    $backend->expects($this->once())->method('createTerm')->willReturnCallback(function (EntityStub $received) use ($stub): EntityStub {
      $this->assertSame('tags', $received->getValue('vocabulary_machine_name'));

      return $stub;
    });

    $this->context->setBackendRegistry($this->createBackendRegistry($backend));

    $this->context->entityLifecycleCreateTerm($stub);

    $this->assertSame('tags', $stub->getValue('vocabulary_machine_name'));
  }

  public function testTermCreationLeavesLabelForNonDrupalBackend(): void {
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'vocabulary_machine_name' => 'Tags']);

    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Drupal\Capability\TermCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, TermCapabilityInterface::class]);
    $backend->expects($this->once())->method('createTerm')->willReturnCallback(function (EntityStub $received) use ($stub): EntityStub {
      $this->assertSame('Tags', $received->getValue('vocabulary_machine_name'));

      return $stub;
    });

    $this->context->setBackendRegistry($this->createBackendRegistry($backend));

    $this->context->entityLifecycleCreateTerm($stub);

    $this->assertSame('Tags', $stub->getValue('vocabulary_machine_name'));
  }

  /**
   * Builds a bootstrapped in-process backend double.
   *
   * Its field parsing leaves the stub as it is. Its config and lookup methods
   * read the booted kernel.
   *
   * @return \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createInProcessBackend(): DrupalBackendInterface&MockObject {
    $kernel_core = new Core($this->root);

    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->method('isBootstrapped')->willReturn(TRUE);
    $backend->method('configExists')->willReturnCallback($kernel_core->configExists(...));
    $backend->method('findEntityIds')->willReturnCallback($kernel_core->findEntityIds(...));

    return $backend;
  }

  /**
   * Builds a backend registry holding the given backend as the only one.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the scenario resolves against.
   *
   * @return \DrevOps\BehatSteps\Extension\Registry\BackendRegistryInterface
   *   The backend registry.
   */
  protected function createBackendRegistry(BackendInterface $backend): BackendRegistryInterface {
    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);
    $backend_registry->setEnvironment($this->createMock(Environment::class));

    return $backend_registry;
  }

}
