<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Helper;

use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Helper\DrupalApiTrait;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;
use DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Kernel test for resolving a vocabulary label to its machine name.
 *
 * @group behat
 */
#[CoversTrait(DrupalApiTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class DrupalApiTraitVocabularyKernelTest extends KernelTestBase {

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
    $this->context->setDriverManager($this->createDriverManager($this->createInProcessDriver()));
    $this->context->setDispatcher(new HookDispatcher(new HookRepository(new EnvironmentManager()), new CallCenter()));
  }

  /**
   * Tests that a machine name is returned untouched.
   */
  public function testMachineNameResolvesToItself(): void {
    $this->assertSame('tags', $this->context->callResolveVocabularyMachineName('tags'));
  }

  /**
   * Tests that a human label resolves to the vocabulary's machine name.
   */
  public function testLabelResolvesToItsMachineName(): void {
    $this->assertSame('tags', $this->context->callResolveVocabularyMachineName('Tags'));
  }

  /**
   * Tests that an unknown identifier is handed back for the driver to reject.
   */
  public function testAnUnknownIdentifierIsReturnedUnchanged(): void {
    $this->assertSame('Unknown', $this->context->callResolveVocabularyMachineName('Unknown'));
  }

  /**
   * Tests that term creation resolves the label before calling the driver.
   */
  public function testTermCreationResolvesTheVocabularyLabel(): void {
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'vocabulary_machine_name' => 'Tags']);

    $driver = $this->createInProcessDriver();
    $driver->expects($this->once())->method('termCreate')->willReturnCallback(function (EntityStub $received) use ($stub): EntityStub {
      $this->assertSame('tags', $received->getValue('vocabulary_machine_name'));

      return $stub;
    });

    $this->context->setDriverManager($this->createDriverManager($driver));

    $this->context->termCreate($stub);

    $this->assertSame('tags', $stub->getValue('vocabulary_machine_name'));
  }

  /**
   * Tests that a driver without Drupal receives the identifier as given.
   */
  public function testTermCreationLeavesLabelForNonDrupalDriver(): void {
    $stub = new EntityStub('taxonomy_term', 'tags', ['name' => 'A term', 'vocabulary_machine_name' => 'Tags']);

    /** @var \DrevOps\BehatSteps\Driver\DriverInterface&\DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface&\PHPUnit\Framework\MockObject\MockObject $driver */
    $driver = $this->createMockForIntersectionOfInterfaces([DriverInterface::class, ContentCapabilityInterface::class]);
    $driver->expects($this->once())->method('termCreate')->willReturnCallback(function (EntityStub $received) use ($stub): EntityStub {
      $this->assertSame('Tags', $received->getValue('vocabulary_machine_name'));

      return $stub;
    });

    $this->context->setDriverManager($this->createDriverManager($driver));

    $this->context->termCreate($stub);

    $this->assertSame('Tags', $stub->getValue('vocabulary_machine_name'));
  }

  /**
   * Builds a bootstrapped in-process driver double.
   *
   * Its classifier reports every value as a standard base field, so the
   * field parser passes the stub through unchanged.
   *
   * @return \DrevOps\BehatSteps\Driver\DrupalDriverInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The driver double.
   */
  protected function createInProcessDriver(): DrupalDriverInterface&MockObject {
    $classifier = $this->createMock(FieldClassifierInterface::class);
    $classifier->method('fieldIsBaseStandard')->willReturn(TRUE);

    $core = $this->createMock(CoreInterface::class);
    $core->method('getFieldClassifier')->willReturn($classifier);

    $driver = $this->createMock(DrupalDriverInterface::class);
    $driver->method('isBootstrapped')->willReturn(TRUE);
    $driver->method('getCore')->willReturn($core);

    return $driver;
  }

  /**
   * Builds a driver manager holding the given driver as the only one.
   *
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver the scenario resolves against.
   *
   * @return \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface
   *   The driver manager.
   */
  protected function createDriverManager(DriverInterface $driver): DriverManagerInterface {
    $driver_manager = new DriverManager(['test' => $driver]);
    $driver_manager->setScenarioDrivers(['test' => 'test']);
    $driver_manager->setEnvironment($this->createMock(Environment::class));

    return $driver_manager;
  }

}
