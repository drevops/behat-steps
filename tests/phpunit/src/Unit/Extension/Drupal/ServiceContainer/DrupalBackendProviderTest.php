<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Drupal\ServiceContainer;

use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Extension\Drupal\ServiceContainer\DrupalBackendProvider;
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
   * Working directory to restore after a test that changed it.
   */
  protected string $originalCwd;

  protected function setUp(): void {
    parent::setUp();

    $this->originalCwd = (string) getcwd();
  }

  protected function tearDown(): void {
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
    $this->assertTrue($container->hasDefinition('behat_steps.backend.core'));
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drupal.drupal_root'));
  }

  public function testLoadRegistersTheDrushBackendWithItsRoot(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertTrue($container->hasDefinition(DrupalBackendProvider::DRUSH_SERVICE));
    $this->assertSame('web', $container->getParameter('behat_steps.backend.drush.root'));
    $this->assertNull($container->getParameter('behat_steps.backend.drush.alias'));
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

  public function testProcessInjectsTheTaggedCoreIntoTheDrupalBackend(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));
    $container->setDefinition('behat_steps.backend.core', (new Definition(Core::class))->addTag(DrupalBackendProvider::CORE_TAG));

    (new DrupalBackendProvider())->process($container);

    $this->assertEquals([['setCore', [new Reference('behat_steps.backend.core')]]], $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls());
  }

  public function testProcessLeavesTheDrupalBackendAloneWhenNoCoreIsTagged(): void {
    $container = new ContainerBuilder();
    $container->setDefinition(DrupalBackendProvider::DRUPAL_SERVICE, (new Definition(DrupalBackend::class))->addTag('behat_steps.backend', ['alias' => 'drupal']));

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition(DrupalBackendProvider::DRUPAL_SERVICE)->getMethodCalls());
  }

  public function testProcessLeavesAnotherBackendAlone(): void {
    $container = new ContainerBuilder();
    $container->setDefinition('behat_steps.backend.blackbox', (new Definition(BlackboxBackend::class))->addTag('behat_steps.backend', ['alias' => 'blackbox']));
    $container->setDefinition('behat_steps.backend.core', (new Definition(Core::class))->addTag(DrupalBackendProvider::CORE_TAG));

    (new DrupalBackendProvider())->process($container);

    $this->assertSame([], $container->getDefinition('behat_steps.backend.blackbox')->getMethodCalls());
    $this->assertFalse($container->hasDefinition(DrupalBackendProvider::DRUPAL_SERVICE));
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
