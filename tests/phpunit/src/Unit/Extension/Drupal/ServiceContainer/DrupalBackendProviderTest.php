<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Drupal\ServiceContainer;

use ConsumerProject\Backend\Alias\AuthorIdAlias;
use ConsumerProject\Backend\Alias\HeadlineAlias;
use ConsumerProject\Backend\Alias\LoginAlias;
use ConsumerProject\Backend\ConsumerCore;
use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DatetimeHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core99\Core as Core99Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Extension\Drupal\ServiceContainer\DrupalBackendProvider;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FakeVersionDrupalBackend;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FinalDrupalBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests the settings, defaults and services the Drupal backends contribute.
 */
#[CoversClass(DrupalBackendProvider::class)]
class DrupalBackendProviderTest extends UnitTestCase {

  /**
   * A Drupal root holding only the files the backend reads its version from.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../../../fixtures/backend/drupal-root';

  /**
   * Working directory to restore after a test that changed it.
   */
  protected string $originalCwd;

  /**
   * The 'COMPOSER_BIN_DIR' value to restore, or NULL when it was unset.
   */
  protected ?string $originalComposerBinDir = NULL;

  protected function setUp(): void {
    parent::setUp();

    $this->originalCwd = (string) getcwd();
    $composer_bin_dir = getenv('COMPOSER_BIN_DIR');
    $this->originalComposerBinDir = $composer_bin_dir === FALSE ? NULL : $composer_bin_dir;
  }

  protected function tearDown(): void {
    FakeVersionDrupalBackend::$nextVersion = '11.0.0';
    chdir($this->originalCwd);
    putenv($this->originalComposerBinDir === NULL ? 'COMPOSER_BIN_DIR' : 'COMPOSER_BIN_DIR=' . $this->originalComposerBinDir);

    parent::tearDown();
  }

  public function testConfigureAddsTheDrupalAndDrushNodes(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web'], 'drush' => ['alias' => '@self']]);

    $this->assertSame('web', $config['drupal']['drupal_root']);
    $this->assertSame('@self', $config['drush']['alias']);
    $this->assertSame('vendor/bin/drush', $config['drush']['binary']);
  }

  public function testConfigureRequiresTheDrupalRoot(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The child config "drupal_root" under "behat_steps.drupal" must be configured');

    $this->processConfig(['drupal' => []]);
  }

  public function testConfigureReadsTheCore(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web', 'core' => 'Acme\Core']]);

    $this->assertSame('Acme\Core', $config['drupal']['core']);
  }

  public function testConfigureLeavesTheCoreUnset(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web']]);

    $this->assertArrayNotHasKey('core', $config['drupal']);
  }

  public function testConfigureReadsTheFieldHandlers(): void {
    $handlers = ['rating' => 'Acme\RatingHandler', 'date_recur' => 'Acme\ScheduleHandler'];

    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web', 'field_handlers' => $handlers]]);

    $this->assertSame($handlers, $config['drupal']['field_handlers']);
  }

  public function testConfigureDefaultsToNoFieldHandlers(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web']]);

    $this->assertSame([], $config['drupal']['field_handlers']);
  }

  public function testConfigureRejectsAnEmptyFieldHandlerClass(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The path "behat_steps.drupal.field_handlers.rating" cannot contain an empty value');

    $this->processConfig(['drupal' => ['drupal_root' => 'web', 'field_handlers' => ['rating' => '']]]);
  }

  public function testConfigureReadsTheCreationAliases(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web', 'creation_aliases' => ['Acme\HeadlineAlias', 'Acme\AuthorIdAlias']], 'drush' => ['alias' => '@self', 'creation_aliases' => ['Acme\LoginAlias']]]);

    $this->assertSame(['Acme\HeadlineAlias', 'Acme\AuthorIdAlias'], $config['drupal']['creation_aliases']);
    $this->assertSame(['Acme\LoginAlias'], $config['drush']['creation_aliases']);
  }

  public function testConfigureDefaultsToNoCreationAliases(): void {
    $config = $this->processConfig(['drupal' => ['drupal_root' => 'web'], 'drush' => ['alias' => '@self']]);

    $this->assertSame([], $config['drupal']['creation_aliases']);
    $this->assertSame([], $config['drush']['creation_aliases']);
  }

  /**
   * Tests that an empty alias class fails the build, naming its path.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   * @param string $expected_message
   *   The message the build is expected to fail with.
   */
  #[DataProvider('dataProviderConfigureRejectsAnEmptyCreationAliasClass')]
  public function testConfigureRejectsAnEmptyCreationAliasClass(array $config, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $this->processConfig($config);
  }

  public static function dataProviderConfigureRejectsAnEmptyCreationAliasClass(): \Iterator {
    yield 'the in-process backend' => [['drupal' => ['drupal_root' => 'web', 'creation_aliases' => ['']]], 'The path "behat_steps.drupal.creation_aliases.0" cannot contain an empty value'];
    yield 'the Drush backend' => [['drush' => ['alias' => '@self', 'creation_aliases' => ['']]], 'The path "behat_steps.drush.creation_aliases.0" cannot contain an empty value'];
  }

  public function testParameterDefaultsNameTheDrupalLoginForm(): void {
    $defaults = (new DrupalBackendProvider())->getParameterDefaults();

    $this->assertSame(['login_url', 'logout_url', 'logout_confirm_url', 'login', 'logout', 'password_field', 'username_field'], array_keys($defaults['text']));
    $this->assertSame('/user', $defaults['text']['login_url']);
    $this->assertSame(['login_form_selector', 'logged_in_selector'], array_keys($defaults['selectors']));
    $this->assertSame('form#user-login,form#user-login-form', $defaults['selectors']['login_form_selector']);
  }

  public function testLoadRegistersNothingWithoutSettings(): void {
    $container = $this->load([]);

    $this->assertFalse($container->hasDefinition(DrupalBackendProvider::DRUPAL_SERVICE));
    $this->assertFalse($container->hasDefinition(DrupalBackendProvider::DRUSH_SERVICE));
  }

  public function testLoadRegistersTheDrupalBackendWithItsRoot(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web']]);

    $this->assertTrue($container->hasDefinition(DrupalBackendProvider::DRUPAL_SERVICE));
    $this->assertSame(getcwd() . '/web', $container->getParameter('behat_steps.backend.drupal.drupal_root'));
    $this->assertNull($container->getParameter(DrupalBackendProvider::CORE_PARAMETER));
    $this->assertSame([], $container->getParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER));
    $this->assertSame([], $container->getParameter(DrupalBackendProvider::DRUPAL_CREATION_ALIASES_PARAMETER));
  }

  public function testLoadKeepsTheCore(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web', 'core' => 'Acme\Core']]);

    $this->assertSame('Acme\Core', $container->getParameter(DrupalBackendProvider::CORE_PARAMETER));
  }

  public function testLoadReadsNullCoreAsNotGiven(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web', 'core' => NULL]]);

    $this->assertNull($container->getParameter(DrupalBackendProvider::CORE_PARAMETER));
  }

  public function testLoadRejectsAnEmptyCore(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Drupal "core" is empty. Set a value, or leave it out.');

    $this->load(['drupal' => ['drupal_root' => 'web', 'core' => '']]);
  }

  public function testLoadKeepsTheFieldHandlers(): void {
    $handlers = ['rating' => 'Acme\RatingHandler'];

    $container = $this->load(['drupal' => ['drupal_root' => 'web', 'field_handlers' => $handlers]]);

    $this->assertSame($handlers, $container->getParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER));
  }

  public function testLoadKeepsTheCreationAliases(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web', 'creation_aliases' => ['Acme\HeadlineAlias']], 'drush' => ['alias' => '@self', 'creation_aliases' => ['Acme\LoginAlias']]]);

    $this->assertSame(['Acme\HeadlineAlias'], $container->getParameter(DrupalBackendProvider::DRUPAL_CREATION_ALIASES_PARAMETER));
    $this->assertSame(['Acme\LoginAlias'], $container->getParameter(DrupalBackendProvider::DRUSH_CREATION_ALIASES_PARAMETER));
  }

  public function testLoadRegistersTheDrushBackendWithItsRoot(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertTrue($container->hasDefinition(DrupalBackendProvider::DRUSH_SERVICE));
    $this->assertSame(getcwd() . '/web', $container->getParameter('behat_steps.backend.drush.root'));
    $this->assertNull($container->getParameter('behat_steps.backend.drush.alias'));
    $this->assertSame([], $container->getParameter(DrupalBackendProvider::DRUSH_CREATION_ALIASES_PARAMETER));
  }

  public function testLoadAcceptsAnAliasInsteadOfRoot(): void {
    $container = $this->load(['drush' => ['alias' => '@self']]);

    $this->assertSame('@self', $container->getParameter('behat_steps.backend.drush.alias'));
    $this->assertNull($container->getParameter('behat_steps.backend.drush.root'));
  }

  public function testLoadReadsNullAliasAsNotGiven(): void {
    $container = $this->load(['drush' => ['alias' => NULL, 'root' => 'web']]);

    $this->assertNull($container->getParameter('behat_steps.backend.drush.alias'));
    $this->assertSame(getcwd() . '/web', $container->getParameter('behat_steps.backend.drush.root'));
  }

  public function testRelativeDrushRootResolvesAgainstTheDirectoryBehatRunsIn(): void {
    $project = $this->createDrushProject();
    chdir($project);
    $container = $this->load(['drush' => ['root' => 'web']]);
    (new DrupalBackendProvider())->process($container);
    $backend = $container->get(DrupalBackendProvider::DRUSH_SERVICE);
    $this->assertInstanceOf(DrushBackend::class, $backend);

    // The in-process backend's bootstrap moves into the Drupal root before the
    // lazy Drush backend is first used.
    chdir($project . '/web');
    $backend->getCreationAliases('user');

    $this->assertSame(realpath($project . '/web'), (new \ReflectionProperty(DrushBackend::class, 'root'))->getValue($backend));
  }

  public function testLoadResolvesTheDrushBinary(): void {
    chdir(static::$tmp);

    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertSame('vendor/bin/drush', $container->getParameter('behat_steps.backend.drush.binary'));
  }

  /**
   * Tests that an empty alias or root fails the build, naming the setting.
   *
   * @param array<string, mixed> $drush
   *   The Drush settings, before schema normalization.
   * @param string $expected_message
   *   The message the build is expected to fail with.
   */
  #[DataProvider('dataProviderLoadRejectsEmptySetting')]
  public function testLoadRejectsEmptySetting(array $drush, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    $this->load(['drush' => $drush]);
  }

  public static function dataProviderLoadRejectsEmptySetting(): \Iterator {
    yield 'an empty alias beside a root' => [['alias' => '', 'root' => 'web'], 'Drush "alias" is empty. Set a value, or leave it out.'];
    yield 'an empty alias alone' => [['alias' => ''], 'Drush "alias" is empty. Set a value, or leave it out.'];
    yield 'an empty root beside an alias' => [['alias' => '@self', 'root' => ''], 'Drush "root" is empty. Set a value, or leave it out.'];
    yield 'an empty root alone' => [['root' => ''], 'Drush "root" is empty. Set a value, or leave it out.'];
    yield 'both empty' => [['alias' => '', 'root' => ''], 'Drush "alias" is empty. Set a value, or leave it out.'];
  }

  /**
   * Tests that the Drush backend fails the build without an alias or a root.
   *
   * @param array<string, mixed> $drush
   *   The Drush settings, before schema normalization.
   */
  #[DataProvider('dataProviderLoadRequiresAliasOrRoot')]
  public function testLoadRequiresAliasOrRoot(array $drush): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Drush "alias" or "root" path is required for the Drush backend.');

    $this->load(['drush' => $drush]);
  }

  public static function dataProviderLoadRequiresAliasOrRoot(): \Iterator {
    yield 'neither set' => [[]];
    yield 'both left out with NULL' => [['alias' => NULL, 'root' => NULL]];
  }

  public function testGlobalOptionsReachTheDrushBackend(): void {
    $container = $this->load(['drush' => ['root' => 'web', 'global_options' => '--yes']]);

    $this->assertSame([['setArguments', ['--yes']]], $container->getDefinition(DrupalBackendProvider::DRUSH_SERVICE)->getMethodCalls());
  }

  public function testGlobalOptionsAreOptional(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUSH_SERVICE)->getMethodCalls());
  }

  public function testProcessLeavesAnotherBackendAlone(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));
    $container->setParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER, ['rating' => 'Acme\RatingHandler']);

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.blackbox')->getMethodCalls());
    $this->assertFalse($container->hasDefinition(DrupalBackendProvider::DRUPAL_SERVICE));
  }

  public function testProcessRegistersTheFieldHandlersOnTheDrupalBackend(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $handlers = ['rating' => 'Acme\RatingHandler', 'datetime' => 'Acme\DateHandler'];
    $container->setParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER, $handlers);

    (new DrupalBackendProvider())->process($container);

    $calls = $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls();
    $this->assertCount(2, $calls);
    $this->assertSame(['registerFieldHandler', ['rating', 'Acme\RatingHandler']], $calls[0]);
    $this->assertSame(['registerFieldHandler', ['datetime', 'Acme\DateHandler']], $calls[1]);
  }

  public function testProcessRegistersNoFieldHandlerWithoutTheSetting(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls());
  }

  public function testFieldHandlersReplaceAndExtendTheShippedOnes(): void {
    $field_handlers = ['address' => DefaultHandler::class, 'rating' => DefaultHandler::class];

    $backend = $this->buildBackend(['field_handlers' => $field_handlers]);

    $handlers = $this->readFieldHandlers($backend);
    $this->assertSame(DefaultHandler::class, $handlers['address']);
    $this->assertSame(DefaultHandler::class, $handlers['rating']);
    $this->assertSame(DatetimeHandler::class, $handlers['datetime']);
  }

  public function testMissingFieldHandlerClassFailsWhenTheBackendIsFirstUsed(): void {
    $backend = $this->buildBackend(['field_handlers' => ['rating' => 'Acme\MissingHandler']]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Handler class "Acme\MissingHandler" does not exist.');

    $backend->getCore();
  }

  /**
   * Tests that the backend services are lazy.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   * @param string $service
   *   The ID of the backend service.
   */
  #[DataProvider('dataProviderTheBackendServicesAreLazy')]
  public function testTheBackendServicesAreLazy(array $config, string $service): void {
    $this->assertTrue($this->load($config)->getDefinition($service)->isLazy());
  }

  public static function dataProviderTheBackendServicesAreLazy(): \Iterator {
    yield 'drupal' => [['drupal' => ['drupal_root' => 'web']], DrupalBackendProvider::DRUPAL_SERVICE];
    yield 'drush' => [['drush' => ['alias' => '@self']], DrupalBackendProvider::DRUSH_SERVICE];
  }

  /**
   * Tests which backend classes the container builds on first use.
   *
   * @param string $class
   *   The class the 'drupal' backend service is built from.
   * @param bool $expected
   *   Whether the service is expected to stay lazy.
   */
  #[DataProvider('dataProviderProcessKeepsTheBackendLazyWhenItCan')]
  public function testProcessKeepsTheBackendLazyWhenItCan(string $class, bool $expected): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web']]);
    $container->setParameter('behat_steps.backend.drupal.class', $class);

    (new DrupalBackendProvider())->process($container);

    $this->assertSame($expected, $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->isLazy());
  }

  public static function dataProviderProcessKeepsTheBackendLazyWhenItCan(): \Iterator {
    yield 'the shipped class' => [DrupalBackend::class, TRUE];
    yield 'a final class' => [FinalDrupalBackend::class, FALSE];
    yield 'a class that cannot be loaded' => ['Acme\MissingBackend', FALSE];
  }

  public function testTheBackendIsConstructedOnFirstUse(): void {
    $container = $this->buildContainer(['drupal' => ['drupal_root' => static::DRUPAL_ROOT]]);
    FakeVersionDrupalBackend::$nextVersion = '11.0.0';
    $backend = $container->get(DrupalBackendProvider::DRUPAL_SERVICE);
    $this->assertInstanceOf(DrupalBackend::class, $backend);

    FakeVersionDrupalBackend::$nextVersion = '99.0.0';

    $this->assertSame(99, $backend->getDrupalVersion());
  }

  public function testMissingDrushCreationAliasClassFailsWhenTheBackendIsFirstUsed(): void {
    $backend = $this->buildDrushBackend(['Acme\MissingAlias']);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drush.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.');

    $backend->getCreationAliases('user');
  }

  public function testClassesLoadableOnlyAfterTheBackendIsHandedOutAreRegistered(): void {
    $namespace = 'ConsumerProject\LateLoaded';
    $core = $namespace . '\LateCore';
    $handler = $namespace . '\RatingHandler';
    $alias = $namespace . '\RatingAlias';
    $backend = $this->buildBackend(['core' => $core, 'field_handlers' => ['rating' => $handler], 'creation_aliases' => [$alias]]);
    $this->assertFalse(class_exists($handler));

    $directory = $this->writeLateLoadedClasses($namespace);
    $autoloader = static function (string $class) use ($namespace, $directory): void {
      if (str_starts_with($class, $namespace . '\\')) {
        require $directory . '/' . substr($class, strlen($namespace) + 1) . '.php';
      }
    };
    spl_autoload_register($autoloader);

    try {
      $this->assertSame($core, $backend->getCore()::class);
    }
    finally {
      spl_autoload_unregister($autoloader);
    }

    $this->assertSame($handler, $this->readFieldHandlers($backend)['rating']);
    $this->assertSame($alias, $backend->getCreationAliases('node')['rating']::class);
  }

  public function testTheBackendBuildsTheVersionCoreForItsMajor(): void {
    $backend = $this->buildBackend([], '99.0.0');

    $this->assertSame(Core99Core::class, $backend->getCore()::class);
  }

  public function testTheCoreOptionReplacesTheVersionCore(): void {
    $backend = $this->buildBackend(['core' => ConsumerCore::class]);

    $this->assertSame(ConsumerCore::class, $backend->getCore()::class);
  }

  public function testTheDrupalAndDrushBackendsShareTheRandomGenerator(): void {
    $drupal = ['drupal_root' => static::DRUPAL_ROOT, 'core' => ConsumerCore::class];
    $container = $this->buildContainer(['drupal' => $drupal, 'drush' => ['alias' => '@self']]);
    FakeVersionDrupalBackend::$nextVersion = '11.0.0';

    $drupal_backend = $container->get(DrupalBackendProvider::DRUPAL_SERVICE);
    $drush_backend = $container->get(DrupalBackendProvider::DRUSH_SERVICE);
    $this->assertInstanceOf(DrupalBackend::class, $drupal_backend);
    $this->assertInstanceOf(DrushBackend::class, $drush_backend);
    $this->assertInstanceOf(ConsumerCore::class, $drupal_backend->getCore());

    $random = $container->get('behat_steps.backend.random');
    $this->assertSame($random, $drupal_backend->getRandom());
    $this->assertSame($random, $drush_backend->getRandom());
  }

  public function testFieldHandlersApplyToTheCoreTheOptionNames(): void {
    $drupal = ['core' => ConsumerCore::class, 'field_handlers' => ['rating' => DefaultHandler::class]];

    $backend = $this->buildBackend($drupal);

    $this->assertInstanceOf(ConsumerCore::class, $backend->getCore());
    $this->assertSame(DefaultHandler::class, $this->readFieldHandlers($backend)['rating']);
  }

  public function testCoreBuiltOnTheCoreOfAnotherMajorFailsTheFirstUse(): void {
    $template = 'Core class "%s" extends "%s", which is not the Core for Drupal 99. Extend "%s" instead.';
    $message = sprintf($template, ConsumerCore::class, Core::class, Core99Core::class);
    $backend = $this->buildBackend(['core' => ConsumerCore::class], '99.0.0');

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($message);

    $backend->getCore();
  }

  public function testProcessRegistersTheCreationAliasesOnTheDrupalBackend(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER, ['rating' => 'Acme\RatingHandler']);
    $container->setParameter(DrupalBackendProvider::DRUPAL_CREATION_ALIASES_PARAMETER, ['Acme\HeadlineAlias', 'Acme\AuthorIdAlias']);

    (new DrupalBackendProvider())->process($container);

    $calls = $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls();
    $this->assertSame(['registerFieldHandler', 'registerCreationAlias', 'registerCreationAlias'], array_column($calls, 0));
    $this->assertCreationAliasDefinition($calls[1][1][0], 'drupal.creation_aliases', 'Acme\HeadlineAlias');
    $this->assertCreationAliasDefinition($calls[2][1][0], 'drupal.creation_aliases', 'Acme\AuthorIdAlias');
  }

  public function testProcessRegistersTheCreationAliasesOnTheDrushBackend(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUSH_SERVICE, (new Definition(DrushBackend::class))->addTag('behat_steps.backend', ['alias' => 'drush']));
    $container->setParameter(DrupalBackendProvider::DRUSH_CREATION_ALIASES_PARAMETER, ['Acme\LoginAlias']);

    (new DrupalBackendProvider())->process($container);

    $calls = $container->getDefinition(DrupalBackendProvider::DRUSH_SERVICE)->getMethodCalls();
    $this->assertSame(['registerCreationAlias'], array_column($calls, 0));
    $this->assertCreationAliasDefinition($calls[0][1][0], 'drush.creation_aliases', 'Acme\LoginAlias');
  }

  public function testProcessRegistersNoCreationAliasWithoutTheSetting(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setDefinition(DrupalBackendProvider::DRUSH_SERVICE, (new Definition(DrushBackend::class))->addTag('behat_steps.backend', ['alias' => 'drush']));

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls());
    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUSH_SERVICE)->getMethodCalls());
  }

  public function testCreationAliasesReplaceAndExtendTheShippedOnes(): void {
    $backend = $this->buildBackend(['creation_aliases' => [HeadlineAlias::class, AuthorIdAlias::class]]);

    $node_aliases = $backend->getCreationAliases('node');
    $this->assertSame(['author', 'headline'], array_keys($node_aliases));
    $this->assertInstanceOf(AuthorIdAlias::class, $node_aliases['author']);
    $this->assertInstanceOf(HeadlineAlias::class, $node_aliases['headline']);
    $this->assertSame(['vocabulary_machine_name', 'parent'], array_keys($backend->getCreationAliases('taxonomy_term')));
    $this->assertInstanceOf(RolesAlias::class, $backend->getCreationAliases('user')['roles']);
  }

  public function testDrushCreationAliasesExtendTheShippedOnes(): void {
    $backend = $this->buildDrushBackend([LoginAlias::class]);

    $aliases = $backend->getCreationAliases('user');
    $this->assertSame(['roles', 'login'], array_keys($aliases));
    $this->assertInstanceOf(LoginAlias::class, $aliases['login']);
  }

  public function testMissingCreationAliasClassFailsWhenTheBackendIsFirstUsed(): void {
    $backend = $this->buildBackend(['creation_aliases' => ['Acme\MissingAlias']]);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drupal.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.');

    $backend->getCreationAliases('node');
  }

  public function testBuildCreationAliasBuildsTheClass(): void {
    $this->assertInstanceOf(HeadlineAlias::class, DrupalBackendProvider::buildCreationAlias('drupal.creation_aliases', HeadlineAlias::class));
  }

  /**
   * Tests that a class the factory cannot build fails, naming the option.
   *
   * @param string $class
   *   The class the option names.
   * @param string $expected_message
   *   The message the build is expected to fail with.
   */
  #[DataProvider('dataProviderBuildCreationAliasRejectsClass')]
  public function testBuildCreationAliasRejectsClass(string $class, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    DrupalBackendProvider::buildCreationAlias('drush.creation_aliases', $class);
  }

  public static function dataProviderBuildCreationAliasRejectsClass(): \Iterator {
    yield 'a missing class' => ['Acme\MissingAlias', 'The "drush.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.'];
    yield 'a class that is no alias' => [EntityStub::class, sprintf('The "drush.creation_aliases" option names the class "%s", which does not implement "%s".', EntityStub::class, CreationAliasInterface::class)];
    yield 'an interface' => [PreCreateAliasInterface::class, sprintf('The "drush.creation_aliases" option names the class "%s", which cannot be instantiated.', PreCreateAliasInterface::class)];
    yield 'a class with constructor arguments' => [RolesAlias::class, sprintf('The "drush.creation_aliases" option names the class "%s", whose constructor requires arguments. Build it in a context and register it through CreationAliasCapabilityInterface::registerCreationAlias().', RolesAlias::class)];
  }

  public function testAbsoluteBinaryPathIsReturnedAsIs(): void {
    $this->assertSame('/usr/local/bin/drush', DrupalBackendProvider::resolveBinaryPath('/usr/local/bin/drush'));
  }

  public function testBareBinaryCommandIsReturnedAsIs(): void {
    chdir(static::$tmp);
    putenv('COMPOSER_BIN_DIR');

    $this->assertSame('drush', DrupalBackendProvider::resolveBinaryPath('drush'));
    $this->assertSame('ls', DrupalBackendProvider::resolveBinaryPath('ls'));
  }

  public function testBareDrushResolvesToTheProjectDrush(): void {
    $project = $this->createDrushProject();
    chdir($project);
    putenv('COMPOSER_BIN_DIR');

    $this->assertSame(getcwd() . '/vendor/bin/drush', DrupalBackendProvider::resolveBinaryPath('drush'));
  }

  public function testBareDrushResolvesFromTheComposerBinDirectory(): void {
    $project = $this->createDrushProject();
    chdir(static::$tmp);
    putenv('COMPOSER_BIN_DIR=' . $project . '/vendor/bin');

    $this->assertSame($project . '/vendor/bin/drush', DrupalBackendProvider::resolveBinaryPath('drush'));
  }

  public function testBareDrushResolvesFromRelativeComposerBinDirectory(): void {
    $project = $this->createDrushProject();
    chdir($project . '/web');
    putenv('COMPOSER_BIN_DIR=../vendor/bin');

    $this->assertSame(getcwd() . '/../vendor/bin/drush', DrupalBackendProvider::resolveBinaryPath('drush'));
  }

  /**
   * Tests that a directory resolves against the working directory.
   *
   * @param string $path
   *   The configured directory.
   * @param string $expected
   *   The expected directory, with '{cwd}' for the working directory.
   */
  #[DataProvider('dataProviderResolveDirectoryPath')]
  public function testResolveDirectoryPath(string $path, string $expected): void {
    chdir(static::$tmp);

    $this->assertSame(str_replace('{cwd}', (string) getcwd(), $expected), DrupalBackendProvider::resolveDirectoryPath($path));
  }

  public static function dataProviderResolveDirectoryPath(): \Iterator {
    yield 'an absolute directory' => ['/var/www/web', '/var/www/web'];
    yield 'a relative directory' => ['web', '{cwd}/web'];
    yield 'a nested relative directory' => ['docroot/web', '{cwd}/docroot/web'];
  }

  public function testBinaryPathResolvesFromWorkingDirectory(): void {
    $project = $this->createDrushProject();
    chdir($project);

    $this->assertSame($project . '/vendor/bin/drush', DrupalBackendProvider::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testBinaryPathResolvesFromParentDirectory(): void {
    $project = $this->createDrushProject();
    chdir($project . '/web');

    $this->assertSame($project . '/vendor/bin/drush', DrupalBackendProvider::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testUnresolvableBinaryPathIsReturnedAsIs(): void {
    chdir(static::$tmp);

    $this->assertSame('some/nonexistent/binary', DrupalBackendProvider::resolveBinaryPath('some/nonexistent/binary'));
  }

  /**
   * Runs a raw configuration array through the provider's settings nodes.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   *
   * @return array<string, mixed>
   *   The processed configuration.
   */
  protected function processConfig(array $config): array {
    $builder = new ArrayNodeDefinition('behat_steps');
    (new DrupalBackendProvider())->configure($builder->children());
    $tree = $builder->getNode(TRUE);

    $processed = $tree->finalize($tree->normalize($config));
    $this->assertIsArray($processed);

    return $processed;
  }

  /**
   * Loads the provider's backends from a raw configuration array.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   */
  protected function load(array $config): ContainerBuilder {
    $container = new ContainerBuilder();
    (new DrupalBackendProvider())->load($container, $this->processConfig($config));

    return $container;
  }

  /**
   * Builds the container the provider defines, with its backends.
   *
   * 'FakeVersionDrupalBackend' reports a version without reading Drupal, so
   * the fixture root stands in for a Drupal installation.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalization.
   */
  protected function buildContainer(array $config): ContainerBuilder {
    $container = $this->load($config);
    $container->setParameter('behat_steps.backend.drupal.class', FakeVersionDrupalBackend::class);
    $container->setParameter('mink.base_url', 'http://example.com');

    (new DrupalBackendProvider())->process($container);

    return $container;
  }

  /**
   * Builds the in-process backend the container defines, with its Core.
   *
   * @param array<string, mixed> $drupal
   *   The 'drupal' settings apart from the Drupal root.
   * @param string $version
   *   The Drupal version the backend detects.
   */
  protected function buildBackend(array $drupal, string $version = '11.0.0'): DrupalBackend {
    $container = $this->buildContainer(['drupal' => ['drupal_root' => static::DRUPAL_ROOT] + $drupal]);
    FakeVersionDrupalBackend::$nextVersion = $version;

    $backend = $container->get(DrupalBackendProvider::DRUPAL_SERVICE);
    $this->assertInstanceOf(DrupalBackend::class, $backend);

    return $backend;
  }

  /**
   * Builds the Drush backend the container defines.
   *
   * @param array<int, string> $creation_aliases
   *   The 'drush.creation_aliases' setting.
   */
  protected function buildDrushBackend(array $creation_aliases): DrushBackend {
    $container = $this->load(['drush' => ['alias' => '@self', 'creation_aliases' => $creation_aliases]]);

    (new DrupalBackendProvider())->process($container);

    $backend = $container->get(DrupalBackendProvider::DRUSH_SERVICE);
    $this->assertInstanceOf(DrushBackend::class, $backend);

    return $backend;
  }

  /**
   * Asserts that a method call argument builds the alias an option names.
   *
   * @param mixed $argument
   *   The argument of the 'registerCreationAlias()' call.
   * @param string $option
   *   The option expected to name the class.
   * @param string $class
   *   The class expected to be built.
   */
  protected function assertCreationAliasDefinition(mixed $argument, string $option, string $class): void {
    $this->assertInstanceOf(Definition::class, $argument);
    $this->assertSame(CreationAliasInterface::class, $argument->getClass());
    $this->assertSame([DrupalBackendProvider::class, 'buildCreationAlias'], $argument->getFactory());
    $this->assertSame([$option, $class], $argument->getArguments());
  }

  /**
   * Reads the field handlers registered on the Core of a backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackend $backend
   *   The backend whose Core holds the handlers.
   *
   * @return array<string, string>
   *   The handler classes, keyed by field type.
   */
  protected function readFieldHandlers(DrupalBackend $backend): array {
    $handlers = (new \ReflectionProperty(Core::class, 'fieldHandlers'))->getValue($backend->getCore());
    $this->assertIsArray($handlers);

    return $handlers;
  }

  /**
   * Writes a Core, a field handler and an alias no autoloader loads yet.
   *
   * @param string $namespace
   *   The namespace of the 3 classes.
   *
   * @return string
   *   The directory holding 1 file per class, named for its short name.
   */
  protected function writeLateLoadedClasses(string $namespace): string {
    $classes = [
      'LateCore' => sprintf('class LateCore extends \%s {}', Core::class),
      'RatingHandler' => sprintf('final class RatingHandler extends \%s {}', DefaultHandler::class),
      'RatingAlias' => sprintf('final class RatingAlias implements \%s { public function getName(): string { return "rating"; } public function getEntityType(): string { return "node"; } public function getDescription(): string { return ""; } public function applyToStub(\%s $stub): void {} }', PreCreateAliasInterface::class, EntityStubInterface::class),
    ];

    foreach ($classes as $name => $body) {
      $this->writeFixture('late/' . $name . '.php', sprintf("<?php\n\nnamespace %s;\n\n%s\n", $namespace, $body));
    }

    return static::$tmp . '/late';
  }

  /**
   * Writes a project with a Drush binary and a web root below it.
   *
   * @return string
   *   The project directory.
   */
  protected function createDrushProject(): string {
    $this->writeFixture('project/vendor/bin/drush', '');
    mkdir(static::$tmp . '/project/web');

    return static::$tmp . '/project';
  }

}
