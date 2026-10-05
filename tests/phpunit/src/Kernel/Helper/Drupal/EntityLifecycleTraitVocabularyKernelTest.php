<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Helper\Drupal;

use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistry;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversTrait;
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
  protected TestableRawContext $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();

    $this->context = new TestableRawContext();
    $this->context->setBackendRegistry($this->createBackendRegistry($this->createInProcessBackend()));
    $this->context->setHookDispatcher(new HookDispatcher(new HookRepository(new EnvironmentManager()), new CallCenter()));
  }

  public function testMachineNameResolvesToItself(): void {
    $this->assertSame('tags', $this->context->callResolveVocabularyMachineName('tags'));
  }

  public function testLabelResolvesToItsMachineName(): void {
    $this->assertSame('tags', $this->context->callResolveVocabularyMachineName('Tags'));
  }

  /**
   * Tests that an unknown identifier is returned for the backend to reject.
   */
  public function testAnUnknownIdentifierIsReturnedUnchanged(): void {
    $this->assertSame('Unknown', $this->context->callResolveVocabularyMachineName('Unknown'));
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

    /** @var \DrevOps\BehatSteps\Backend\BackendInterface&\DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([BackendInterface::class, ContentCapabilityInterface::class]);
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
   * Its classifier reports every value as a standard base field, so the
   * field parser passes the stub through unchanged.
   *
   * @return \DrevOps\BehatSteps\Backend\DrupalBackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The backend double.
   */
  protected function createInProcessBackend(): DrupalBackendInterface&MockObject {
    $classifier = $this->createMock(FieldClassifierInterface::class);
    $classifier->method('fieldIsBaseStandard')->willReturn(TRUE);

    $core = $this->createMock(CoreInterface::class);
    $core->method('getFieldClassifier')->willReturn($classifier);

    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->method('isBootstrapped')->willReturn(TRUE);
    $backend->method('getCore')->willReturn($core);

    return $backend;
  }

  /**
   * Builds a backend registry holding the given backend as the only one.
   *
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend the scenario resolves against.
   *
   * @return \DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface
   *   The backend registry.
   */
  protected function createBackendRegistry(BackendInterface $backend): BackendRegistryInterface {
    $backend_registry = new BackendRegistry(['test' => $backend]);
    $backend_registry->setScenarioBackends(['test' => 'test']);
    $backend_registry->setEnvironment($this->createMock(Environment::class));

    return $backend_registry;
  }

}
