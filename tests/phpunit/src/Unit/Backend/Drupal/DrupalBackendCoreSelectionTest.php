<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use ConsumerProject\Backend\ConsumerCore;
use ConsumerProject\Backend\ConsumerCore99;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core99\Core as Core99Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AbstractCore;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FakeVersionDrupalBackend;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\NonPublicConstructorCore;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which Core 'DrupalBackend' builds and which Core it accepts.
 *
 * The 'Core99\Core' fixture stands in for a version core, so the lookup runs
 * against a Drupal major this package ships a Core for.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
class DrupalBackendCoreSelectionTest extends UnitTestCase {

  /**
   * A directory carrying both entry files the version detection requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../../fixtures/backend/drupal-root';

  /**
   * Message for a Core that extends a Core of this package for another major.
   */
  protected const string EXTENDS_ANOTHER_MAJOR = 'Core class "%s" extends "%s", which is not the Core for Drupal %d. Extend "%s" instead.';

  /**
   * Message for a Core of this package handed to a backend of another major.
   */
  protected const string IS_ANOTHER_MAJOR = 'Core class "%s" is not the Core for Drupal %d. Use "%s" instead.';

  protected function tearDown(): void {
    FakeVersionDrupalBackend::$nextVersion = '11.0.0';

    parent::tearDown();
  }

  /**
   * Tests that the backend builds the Core this package ships for a major.
   *
   * @param string $version
   *   The Drupal version the backend detects.
   * @param string $expected
   *   The class of the Core the backend is expected to build.
   */
  #[DataProvider('dataProviderConstructorBuildsTheVersionCore')]
  public function testConstructorBuildsTheVersionCore(string $version, string $expected): void {
    $backend = $this->createBackend($version);

    $this->assertSame($expected, $backend->getCore()::class);
  }

  public static function dataProviderConstructorBuildsTheVersionCore(): \Iterator {
    yield 'a major without a version core' => ['11.2.0', Core::class];
    yield 'the major of a version core' => ['99.0.0', Core99Core::class];
    yield 'a major newer than the newest version core' => ['100.1.0', Core99Core::class];
    yield 'a major older than every version core' => ['98.0.0', Core::class];
  }

  /**
   * Tests that the backend builds the Core class it is given.
   *
   * @param string $version
   *   The Drupal version the backend detects.
   * @param string $class
   *   The Core class given to the backend.
   */
  #[DataProvider('dataProviderConstructorBuildsTheGivenCore')]
  public function testConstructorBuildsTheGivenCore(string $version, string $class): void {
    $backend = $this->createBackend($version, $class);

    $this->assertSame($class, $backend->getCore()::class);
  }

  public static function dataProviderConstructorBuildsTheGivenCore(): \Iterator {
    yield 'a subclass of the base Core on a major without a version core' => ['11.2.0', ConsumerCore::class];
    yield 'a subclass of the version core on its major' => ['99.0.0', ConsumerCore99::class];
  }

  public function testConstructorHandsTheRandomGeneratorToTheCore(): void {
    $random = new RandomGenerator();

    $backend = $this->createBackend('11.2.0', NULL, $random);

    $this->assertSame($random, $backend->getRandom());
  }

  /**
   * Tests that a Core class the backend cannot build fails the construction.
   *
   * @param string $class
   *   The Core class given to the backend.
   * @param string $expected_message
   *   The message the construction is expected to fail with.
   */
  #[DataProvider('dataProviderConstructorRejectsCoreClass')]
  public function testConstructorRejectsCoreClass(string $class, string $expected_message): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createBackend('11.2.0', $class);
  }

  public static function dataProviderConstructorRejectsCoreClass(): \Iterator {
    $not_a_core = sprintf('Core class "ArrayObject" must implement "%s".', CoreInterface::class);
    $abstract = sprintf('Core class "%s" must be instantiable.', AbstractCore::class);
    $non_public = sprintf('Core class "%s" must be instantiable.', NonPublicConstructorCore::class);

    yield 'a class that does not exist' => ['Acme\MissingCore', 'Core class "Acme\MissingCore" does not exist.'];
    yield 'a class that is not a Core' => [\ArrayObject::class, $not_a_core];
    yield 'an abstract Core' => [AbstractCore::class, $abstract];
    yield 'a Core with a non-public constructor' => [NonPublicConstructorCore::class, $non_public];
  }

  public function testConstructorRejectsGivenCoreOfAnotherMajor(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage(sprintf(static::EXTENDS_ANOTHER_MAJOR, ConsumerCore::class, Core::class, 99, Core99Core::class));

    $this->createBackend('99.0.0', ConsumerCore::class);
  }

  /**
   * Tests that a Core built on the Core of another major is rejected.
   *
   * @param string $version
   *   The Drupal version the backend detects.
   * @param class-string<\DrevOps\BehatSteps\Backend\Drupal\Core\Core> $class
   *   The class of the Core handed to the backend.
   * @param string $expected_message
   *   The message the backend is expected to reject the Core with.
   */
  #[DataProvider('dataProviderSetCoreRejectsCoreOfAnotherMajor')]
  public function testSetCoreRejectsCoreOfAnotherMajor(string $version, string $class, string $expected_message): void {
    $backend = $this->createBackend($version);
    $core = new $class(static::DRUPAL_ROOT);

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($expected_message);

    $backend->setCore($core);
  }

  public static function dataProviderSetCoreRejectsCoreOfAnotherMajor(): \Iterator {
    $base = sprintf(static::IS_ANOTHER_MAJOR, Core::class, 99, Core99Core::class);
    $subclass = sprintf(static::EXTENDS_ANOTHER_MAJOR, ConsumerCore::class, Core::class, 99, Core99Core::class);
    $version = sprintf(static::IS_ANOTHER_MAJOR, Core99Core::class, 50, Core::class);

    yield 'the base Core on a major with a version core' => ['99.0.0', Core::class, $base];
    yield 'a subclass of the base Core on a major with a version core' => ['99.0.0', ConsumerCore::class, $subclass];
    yield 'a version core on a major it is not for' => ['50.0.0', Core99Core::class, $version];
  }

  public function testSetCoreAcceptsSubclassOfTheVersionCore(): void {
    $backend = $this->createBackend('99.0.0');
    $core = new class(static::DRUPAL_ROOT) extends Core99Core {};

    $backend->setCore($core);

    $this->assertSame($core, $backend->getCore());
  }

  public function testSetCoreAcceptsCoreBuiltOnNoPackageCore(): void {
    $backend = $this->createBackend('99.0.0');
    $core = $this->createStub(CoreInterface::class);

    $backend->setCore($core);

    $this->assertSame($core, $backend->getCore());
  }

  public function testSetCoreSkipsTheVersionLookupForCoreBuiltOnNoPackageCore(): void {
    $backend = (new \ReflectionClass(DrupalBackend::class))->newInstanceWithoutConstructor();
    $core = $this->createStub(CoreInterface::class);

    $backend->setCore($core);

    $this->assertSame($core, $backend->getCore());
  }

  /**
   * Creates a backend that detects the given Drupal version.
   *
   * @param string $version
   *   The Drupal version the backend detects.
   * @param string|null $core_class
   *   The Core class given to the backend.
   * @param \DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface|null $random
   *   The random generator given to the backend.
   */
  protected function createBackend(string $version, ?string $core_class = NULL, ?RandomGeneratorInterface $random = NULL): FakeVersionDrupalBackend {
    FakeVersionDrupalBackend::$nextVersion = $version;

    return new FakeVersionDrupalBackend(static::DRUPAL_ROOT, 'default', $core_class, $random);
  }

}
