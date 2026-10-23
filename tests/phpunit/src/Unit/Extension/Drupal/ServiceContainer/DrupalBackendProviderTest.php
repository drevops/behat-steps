<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Drupal\ServiceContainer;

use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use ConsumerProject\Backend\Alias\AuthorIdAlias;
use ConsumerProject\Backend\Alias\HeadlineAlias;
use ConsumerProject\Backend\Alias\LoginAlias;
use ConsumerProject\Backend\ConsumerCore;
use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DatetimeHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core99\Core as Core99Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Extension\Drupal\Listener\ConfiguredClassListener;
use DrevOps\BehatSteps\Extension\Drupal\ServiceContainer\DrupalBackendProvider;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FakeVersionDrupalBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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

  protected function setUp(): void {
    parent::setUp();

    $this->originalCwd = (string) getcwd();
  }

  protected function tearDown(): void {
    FakeVersionDrupalBackend::$nextVersion = '11.0.0';
    chdir($this->originalCwd);

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
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drupal.drupal_root'));
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
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drush.root'));
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
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drush.root'));
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

  public function testProcessLeavesTheDefinitionsAlone(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setParameter(DrupalBackendProvider::FIELD_HANDLERS_PARAMETER, ['rating' => 'Acme\RatingHandler']);

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.blackbox')->getMethodCalls());
    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls());
  }

  public function testLoadDefinesNoListenerWithoutSettings(): void {
    $container = $this->load([]);

    $this->assertFalse($container->hasDefinition(DrupalBackendProvider::CONFIGURED_CLASS_LISTENER_SERVICE));
  }

  public function testLoadHandsTheDrupalOptionsToTheListener(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web']]);

    $definition = $container->getDefinition(DrupalBackendProvider::CONFIGURED_CLASS_LISTENER_SERVICE);
    $this->assertSame(ConfiguredClassListener::class, $definition->getClass());
    $this->assertSame([['priority' => 0]], $definition->getTag(EventDispatcherExtension::SUBSCRIBER_TAG));

    $drupal = new Reference(DrupalBackendProvider::DRUPAL_SERVICE);
    $creation_aliases = ['drupal.creation_aliases' => ['backend' => $drupal, 'classes' => '%' . DrupalBackendProvider::DRUPAL_CREATION_ALIASES_PARAMETER . '%']];
    $this->assertEquals([$drupal, '%' . DrupalBackendProvider::FIELD_HANDLERS_PARAMETER . '%', $creation_aliases], $definition->getArguments());
  }

  public function testLoadHandsTheDrushOptionsToTheListener(): void {
    $container = $this->load(['drush' => ['alias' => '@self']]);

    $creation_aliases = ['drush.creation_aliases' => ['backend' => new Reference(DrupalBackendProvider::DRUSH_SERVICE), 'classes' => '%' . DrupalBackendProvider::DRUSH_CREATION_ALIASES_PARAMETER . '%']];
    $this->assertEquals([NULL, [], $creation_aliases], $container->getDefinition(DrupalBackendProvider::CONFIGURED_CLASS_LISTENER_SERVICE)->getArguments());
  }

  public function testTheBackendIsBuiltWithoutItsCore(): void {
    $container = $this->buildContainer(['drupal' => ['drupal_root' => static::DRUPAL_ROOT, 'core' => 'Acme\MissingCore', 'field_handlers' => ['rating' => 'Acme\MissingHandler']]]);

    $backend = $container->get(DrupalBackendProvider::DRUPAL_SERVICE);

    $this->assertInstanceOf(DrupalBackend::class, $backend);
    $this->assertNull((new \ReflectionProperty(DrupalBackend::class, 'core'))->getValue($backend));
  }

  public function testClassesLoadableOnlyOnceTheExerciseStartsAreRegistered(): void {
    $namespace = 'ConsumerProject\LateLoaded';
    $handler = $namespace . '\RatingHandler';
    $alias = $namespace . '\RatingAlias';
    $core = $namespace . '\LateCore';
    $container = $this->buildContainer(['drupal' => ['drupal_root' => static::DRUPAL_ROOT, 'core' => $core, 'field_handlers' => ['rating' => $handler], 'creation_aliases' => [$alias]]]);
    $backend = $container->get(DrupalBackendProvider::DRUPAL_SERVICE);
    $this->assertInstanceOf(DrupalBackend::class, $backend);
    $this->assertFalse(class_exists($handler));

    $directory = $this->writeLateLoadedClasses($namespace);
    $autoloader = static function (string $class) use ($namespace, $directory): void {
      if (str_starts_with($class, $namespace . '\\')) {
        require $directory . '/' . substr($class, strlen($namespace) + 1) . '.php';
      }
    };
    spl_autoload_register($autoloader);

    try {
      $this->runExerciseStart($container);
    }
    finally {
      spl_autoload_unregister($autoloader);
    }

    $this->assertSame($core, $backend->getCore()::class);
    $this->assertSame($handler, $this->readFieldHandlers($backend)['rating']);
    $this->assertSame($alias, $backend->getCreationAliases('node')['rating']::class);
  }

  public function testFieldHandlersReplaceAndExtendTheShippedOnes(): void {
    $field_handlers = ['address' => DefaultHandler::class, 'rating' => DefaultHandler::class];

    $backend = $this->buildBackend(['field_handlers' => $field_handlers]);

    $handlers = $this->readFieldHandlers($backend);
    $this->assertSame(DefaultHandler::class, $handlers['address']);
    $this->assertSame(DefaultHandler::class, $handlers['rating']);
    $this->assertSame(DatetimeHandler::class, $handlers['datetime']);
  }

  public function testMissingFieldHandlerClassFailsWhenTheExerciseStarts(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drupal.field_handlers" option maps the field type "rating" to a class the backend rejects. Handler class "Acme\MissingHandler" does not exist.');

    $this->buildBackend(['field_handlers' => ['rating' => 'Acme\MissingHandler']]);
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

  public function testCoreBuiltOnTheCoreOfAnotherMajorFailsTheBuild(): void {
    $template = 'Core class "%s" extends "%s", which is not the Core for Drupal 99. Extend "%s" instead.';
    $message = sprintf($template, ConsumerCore::class, Core::class, Core99Core::class);

    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessage($message);

    $this->buildBackend(['core' => ConsumerCore::class], '99.0.0');
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

  public function testMissingCreationAliasClassFailsWhenTheExerciseStarts(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drupal.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.');

    $this->buildBackend(['creation_aliases' => ['Acme\MissingAlias']]);
  }

  public function testMissingDrushCreationAliasClassFailsWhenTheExerciseStarts(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "drush.creation_aliases" option names the class "Acme\MissingAlias", which does not exist.');

    $this->buildDrushBackend(['Acme\MissingAlias']);
  }

  public function testAbsoluteBinaryPathIsReturnedAsIs(): void {
    $this->assertSame('/usr/local/bin/drush', DrupalBackendProvider::resolveBinaryPath('/usr/local/bin/drush'));
  }

  public function testBareBinaryCommandIsReturnedAsIs(): void {
    $this->assertSame('drush', DrupalBackendProvider::resolveBinaryPath('drush'));
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
   * The listener runs as it does at the start of the exercise, so the
   * backend carries the configured handlers and aliases.
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

    $this->runExerciseStart($container);

    return $backend;
  }

  /**
   * Builds the Drush backend the container defines, with its aliases.
   *
   * @param array<int, string> $creation_aliases
   *   The 'drush.creation_aliases' setting.
   */
  protected function buildDrushBackend(array $creation_aliases): DrushBackend {
    $container = $this->load(['drush' => ['alias' => '@self', 'creation_aliases' => $creation_aliases]]);

    (new DrupalBackendProvider())->process($container);

    $backend = $container->get(DrupalBackendProvider::DRUSH_SERVICE);
    $this->assertInstanceOf(DrushBackend::class, $backend);

    $this->runExerciseStart($container);

    return $backend;
  }

  /**
   * Runs the listener the container defines, as the exercise start does.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container holding the listener.
   */
  protected function runExerciseStart(ContainerBuilder $container): void {
    $listener = $container->get(DrupalBackendProvider::CONFIGURED_CLASS_LISTENER_SERVICE);
    $this->assertInstanceOf(ConfiguredClassListener::class, $listener);

    $listener->registerConfiguredClasses();
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
