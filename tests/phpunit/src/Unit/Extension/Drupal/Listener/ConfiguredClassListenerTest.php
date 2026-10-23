<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Drupal\Listener;

use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use ConsumerProject\Backend\Alias\HeadlineAlias;
use ConsumerProject\Backend\Alias\LoginAlias;
use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Extension\Drupal\Listener\ConfiguredClassListener;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests the registration of the classes the Drupal backend options name.
 */
#[CoversClass(ConfiguredClassListener::class)]
class ConfiguredClassListenerTest extends UnitTestCase {

  public function testItSubscribesToTheExerciseStart(): void {
    $this->assertSame([ExerciseCompleted::BEFORE => 'registerConfiguredClasses'], ConfiguredClassListener::getSubscribedEvents());
  }

  public function testItRegistersNothingWithoutBackends(): void {
    $this->expectNotToPerformAssertions();

    (new ConfiguredClassListener())->registerConfiguredClasses();
  }

  public function testItBuildsTheCoreBeforeRegisteringTheFieldHandlers(): void {
    $calls = [];
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->once())->method('getCore')->willReturnCallback(function () use (&$calls): CoreInterface {
      $calls[] = 'getCore';

      return $this->createStub(CoreInterface::class);
    });
    $drupal->expects($this->exactly(2))->method('registerFieldHandler')->willReturnCallback(static function (string $field_type, string $class) use (&$calls): void {
      $calls[] = $field_type . ':' . $class;
    });

    (new ConfiguredClassListener($drupal, ['rating' => 'Acme\RatingHandler', 'datetime' => 'Acme\DateHandler']))->registerConfiguredClasses();

    $this->assertSame(['getCore', 'rating:Acme\RatingHandler', 'datetime:Acme\DateHandler'], $calls);
  }

  public function testItBuildsTheCoreWithoutFieldHandlers(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->once())->method('getCore');
    $drupal->expects($this->never())->method('registerFieldHandler');

    (new ConfiguredClassListener($drupal))->registerConfiguredClasses();
  }

  public function testCoreFailureStopsTheRegistration(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->method('getCore')->willThrowException(new BootstrapException('Core class "Acme\MissingCore" does not exist.'));
    $drupal->expects($this->never())->method('registerFieldHandler');

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage('Core class "Acme\MissingCore" does not exist.');

    (new ConfiguredClassListener($drupal, ['rating' => 'Acme\RatingHandler']))->registerConfiguredClasses();
  }

  public function testRejectedFieldHandlerNamesTheOption(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('registerFieldHandler')->willThrowException(new \RuntimeException('Handler class "Acme\MissingHandler" does not exist.'));

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drupal.field_handlers" option maps the field type "rating" to a class the backend rejects. Handler class "Acme\MissingHandler" does not exist.');

    (new ConfiguredClassListener($drupal, ['rating' => 'Acme\MissingHandler']))->registerConfiguredClasses();
  }

  public function testItRegistersEachAliasOnTheBackendOfItsOption(): void {
    $drupal = $this->createMock(CreationAliasCapabilityInterface::class);
    $drupal->expects($this->once())->method('registerCreationAlias')->with($this->isInstanceOf(HeadlineAlias::class));
    $drush = $this->createMock(CreationAliasCapabilityInterface::class);
    $drush->expects($this->once())->method('registerCreationAlias')->with($this->isInstanceOf(LoginAlias::class));

    $creation_aliases = [
      'drupal.creation_aliases' => ['backend' => $drupal, 'classes' => [HeadlineAlias::class]],
      'drush.creation_aliases' => ['backend' => $drush, 'classes' => [LoginAlias::class]],
    ];

    (new ConfiguredClassListener(NULL, [], $creation_aliases))->registerConfiguredClasses();
  }

  /**
   * Tests that an alias class the listener cannot build fails, naming the option.
   *
   * @param string $class
   *   The class the option names.
   * @param string $expected_message
   *   The message the registration is expected to fail with.
   */
  #[DataProvider('dataProviderRejectedAliasClassNamesTheOption')]
  public function testRejectedAliasClassNamesTheOption(string $class, string $expected_message): void {
    $drush = $this->createMock(CreationAliasCapabilityInterface::class);
    $drush->expects($this->never())->method('registerCreationAlias');

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    (new ConfiguredClassListener(NULL, [], ['drush.creation_aliases' => ['backend' => $drush, 'classes' => [$class]]]))->registerConfiguredClasses();
  }

  public static function dataProviderRejectedAliasClassNamesTheOption(): \Iterator {
    yield 'a missing class' => ['Acme\MissingAlias', 'The "drush.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.'];
    yield 'a class that is no alias' => [EntityStub::class, sprintf('The "drush.creation_aliases" option names the class "%s", which does not implement "%s".', EntityStub::class, CreationAliasInterface::class)];
    yield 'an interface' => [PreCreateAliasInterface::class, sprintf('The "drush.creation_aliases" option names the class "%s", which cannot be instantiated.', PreCreateAliasInterface::class)];
    yield 'a class with constructor arguments' => [RolesAlias::class, sprintf('The "drush.creation_aliases" option names the class "%s", whose constructor requires arguments. Build it in a context and register it through CreationAliasCapabilityInterface::registerCreationAlias().', RolesAlias::class)];
  }

}
