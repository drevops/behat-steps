<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use ConsumerProject\Backend\ConsumerCommands;
use ConsumerProject\Backend\ConsumerCommands14;
use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands;
use DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface;
use DrevOps\BehatSteps\Backend\Drupal\Drush14\Commands as Drush14Commands;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistry;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AbstractCommands;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\NonPublicConstructorCommands;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingDrushBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which commands 'DrushBackend' builds and which commands it accepts.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendCommandsSelectionTest extends UnitTestCase {

  /**
   * Message for commands that extend this package's commands for another major.
   */
  protected const string EXTENDS_ANOTHER_MAJOR = 'Commands class "%s" extends "%s", which is not the commands class for Drush %d. Extend "%s" instead.';

  /**
   * Message for this package's commands handed to a backend of another major.
   */
  protected const string IS_ANOTHER_MAJOR = 'Commands class "%s" is not the commands class for Drush %d. Use "%s" instead.';

  /**
   * Tests that the backend builds the commands this package ships for a major.
   *
   * @param string $version
   *   The version 'drush version' prints.
   * @param string $expected
   *   The class of the commands the backend is expected to build.
   */
  #[DataProvider('dataProviderGetCommandsBuildsTheVersionCommands')]
  public function testGetCommandsBuildsTheVersionCommands(string $version, string $expected): void {
    $backend = $this->createBackend($version);

    $this->assertSame($expected, $backend->getCommands()::class);
  }

  public static function dataProviderGetCommandsBuildsTheVersionCommands(): \Iterator {
    yield 'the lowest major, without commands of its own' => ['13.8.0', Commands::class];
    yield 'the major of version commands' => ['14.0.0-dev', Drush14Commands::class];
    yield 'a major newer than the newest version commands' => ['15.1.0', Drush14Commands::class];
  }

  /**
   * Tests that the backend builds the commands class it is given.
   *
   * @param string $version
   *   The version 'drush version' prints.
   * @param string $class
   *   The commands class given to the backend.
   */
  #[DataProvider('dataProviderGetCommandsBuildsTheGivenCommands')]
  public function testGetCommandsBuildsTheGivenCommands(string $version, string $class): void {
    $backend = $this->createBackend($version, $class);

    $this->assertSame($class, $backend->getCommands()::class);
  }

  public static function dataProviderGetCommandsBuildsTheGivenCommands(): \Iterator {
    yield 'a subclass of the base commands on the lowest major' => ['13.8.0', ConsumerCommands::class];
    yield 'a subclass of the version commands on their major' => ['14.0.0', ConsumerCommands14::class];
  }

  public function testGetCommandsBuildsTheCommandsOnce(): void {
    $backend = $this->createBackend('13.8.0');

    $this->assertSame($backend->getCommands(), $backend->getCommands());
    $this->assertSame(['version'], array_column($backend->invocations, 'command'));
  }

  public function testConstructorRunsNoDrushCommand(): void {
    $backend = $this->createBackend('13.8.0');

    $this->assertSame([], $backend->invocations);
  }

  public function testGetCommandsHandsTheRandomGeneratorToTheCommands(): void {
    $random = $this->createStub(RandomGeneratorInterface::class);
    $random->method('name')->willReturn('Generated');
    $backend = new RecordingDrushBackend('alias', NULL, 'drush', $random);

    $role = $backend->createRole([]);

    $this->assertSame($random, $backend->getRandom());
    $this->assertSame('generated', $role->getValue('id'));
  }

  /**
   * Tests that a commands class the backend cannot build fails the build.
   *
   * @param string $class
   *   The commands class given to the backend.
   * @param string $expected_message
   *   The message the build is expected to fail with.
   */
  #[DataProvider('dataProviderGetCommandsRejectsCommandsClass')]
  public function testGetCommandsRejectsCommandsClass(string $class, string $expected_message): void {
    $backend = $this->createBackend('13.8.0', $class);

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($expected_message);

    $backend->getCommands();
  }

  public static function dataProviderGetCommandsRejectsCommandsClass(): \Iterator {
    $not_commands = sprintf('Commands class "ArrayObject" must implement "%s".', CommandsInterface::class);
    $abstract = sprintf('Commands class "%s" must be instantiable.', AbstractCommands::class);
    $non_public = sprintf('Commands class "%s" must be instantiable.', NonPublicConstructorCommands::class);

    yield 'a class that does not exist' => ['Acme\MissingCommands', 'Commands class "Acme\MissingCommands" does not exist.'];
    yield 'a class that is not commands' => [\ArrayObject::class, $not_commands];
    yield 'abstract commands' => [AbstractCommands::class, $abstract];
    yield 'commands with a non-public constructor' => [NonPublicConstructorCommands::class, $non_public];
  }

  /**
   * Tests that commands built on the commands of another major are rejected.
   *
   * @param string $version
   *   The version 'drush version' prints.
   * @param class-string<\DrevOps\BehatSteps\Backend\Drupal\Drush\Commands> $class
   *   The class of the commands handed to the backend.
   * @param string $expected_message
   *   The message the backend is expected to reject the commands with.
   */
  #[DataProvider('dataProviderSetCommandsRejectsCommandsOfAnotherMajor')]
  public function testSetCommandsRejectsCommandsOfAnotherMajor(string $version, string $class, string $expected_message): void {
    $backend = $this->createBackend($version);
    $commands = new $class($backend, $backend->getRandom());

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($expected_message);

    $backend->setCommands($commands);
  }

  public static function dataProviderSetCommandsRejectsCommandsOfAnotherMajor(): \Iterator {
    $base = sprintf(static::IS_ANOTHER_MAJOR, Commands::class, 14, Drush14Commands::class);
    $subclass = sprintf(static::EXTENDS_ANOTHER_MAJOR, ConsumerCommands::class, Commands::class, 14, Drush14Commands::class);
    $version = sprintf(static::IS_ANOTHER_MAJOR, Drush14Commands::class, 13, Commands::class);
    $version_subclass = sprintf(static::EXTENDS_ANOTHER_MAJOR, ConsumerCommands14::class, Drush14Commands::class, 13, Commands::class);

    yield 'the base commands on a major with version commands' => ['14.0.0', Commands::class, $base];
    yield 'a subclass of the base commands on a major with version commands' => ['14.0.0', ConsumerCommands::class, $subclass];
    yield 'version commands on a major they are not for' => ['13.8.0', Drush14Commands::class, $version];
    yield 'a subclass of version commands on a major they are not for' => ['13.8.0', ConsumerCommands14::class, $version_subclass];
  }

  public function testSetCommandsAcceptsCommandsBuiltOnNoPackageCommands(): void {
    $backend = new RecordingDrushBackend('alias');
    $backend->fakeVersion = NULL;
    $commands = $this->createStub(CommandsInterface::class);

    $backend->setCommands($commands);

    $this->assertSame($commands, $backend->getCommands());
    $this->assertSame([], $backend->invocations, 'Commands built on none of this package need no version lookup.');
  }

  /**
   * Tests that a Drush major below the lowest supported one is rejected.
   */
  public function testGetDrushVersionRejectsMajorBelowTheLowest(): void {
    $backend = $this->createBackend('12.5.3');

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage('Unsupported Drush version 12.5.3. Drush 13 or higher is required.');

    $backend->getDrushVersion();
  }

  public function testGetDrushVersionReadsTheMajorOnce(): void {
    $backend = $this->createBackend('14.0.0-dev');

    $this->assertSame([14, 14], [$backend->getDrushVersion(), $backend->getDrushVersion()]);
    $this->assertSame(['version'], array_column($backend->invocations, 'command'));
  }

  public function testSetExperimentalRegistryReachesCommandsBuiltLater(): void {
    $backend = $this->createBackend('14.0.0');
    $registry = new ExperimentalRegistry();

    $backend->setExperimentalRegistry($registry);

    $commands = $backend->getCommands();
    $this->assertInstanceOf(Drush14Commands::class, $commands);
    $this->assertSame($registry, $commands->getExperimentalRegistry());
  }

  public function testSetExperimentalRegistryReachesCommandsAlreadyBuilt(): void {
    $backend = $this->createBackend('14.0.0');
    $commands = $backend->getCommands();
    $this->assertInstanceOf(Drush14Commands::class, $commands);
    $registry = new ExperimentalRegistry();

    $backend->setExperimentalRegistry($registry);

    $this->assertSame($registry, $commands->getExperimentalRegistry());
  }

  /**
   * Creates a backend whose Drush prints the given version.
   *
   * @param string $version
   *   The version 'drush version' prints.
   * @param string|null $commands_class
   *   The commands class given to the backend.
   */
  protected function createBackend(string $version, ?string $commands_class = NULL): RecordingDrushBackend {
    $backend = new RecordingDrushBackend('alias', NULL, 'drush', NULL, $commands_class);
    $backend->fakeVersion = NULL;
    $backend->drushResponses = ['version' => sprintf('{"drush-version":"%s"}', $version)];

    return $backend;
  }

}
