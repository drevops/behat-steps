<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Behat\Context;

use Behat\Testwork\Call\CallCenter;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Environment\EnvironmentManager;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\HookRepository;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableRawContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for resolving a vocabulary label to its machine name.
 *
 * @group behat
 */
#[CoversClass(RawContext::class)]
#[Group('behat')]
class RawContextVocabularyKernelTest extends KernelTestBase {

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

    $driver = $this->createMockForIntersectionOfInterfaces([DriverInterface::class, ContentCapabilityInterface::class]);
    $driver->expects($this->once())->method('termCreate')->willReturnCallback(function (EntityStub $received) use ($stub): EntityStub {
      $this->assertSame('tags', $received->getValue('vocabulary_machine_name'));

      return $stub;
    });

    $environment = $this->createMock(Environment::class);

    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->method('getDriver')->willReturn($driver);
    $driver_manager->method('getEnvironment')->willReturn($environment);

    $this->context->setDriverManager($driver_manager);
    $this->context->setDispatcher(new HookDispatcher(new HookRepository(new EnvironmentManager()), new CallCenter()));

    $this->context->termCreate($stub);

    $this->assertSame('tags', $stub->getValue('vocabulary_machine_name'));
  }

}
