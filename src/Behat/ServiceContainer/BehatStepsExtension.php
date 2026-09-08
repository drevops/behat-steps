<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Behat extension wiring the driver layer, managers and hooks into a suite.
 */
class BehatStepsExtension implements ExtensionInterface {

  /**
   * Key this extension's settings live under in the Behat configuration.
   */
  public const CONFIG_KEY = 'behat_steps';

  /**
   * {@inheritdoc}
   */
  public function getConfigKey(): string {
    return self::CONFIG_KEY;
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(ExtensionManager $extensionManager): void {
  }

  /**
   * {@inheritdoc}
   */
  public function load(ContainerBuilder $container, array $config): void {
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
    $loader->load('services.yml');
    $container->setParameter('behat_steps.default_driver', $config['default_driver']);

    $this->loadParameters($container, $config);

    $this->loadBlackbox($loader);
    $this->loadDrupal($loader, $container, $config);
    $this->loadDrush($loader, $container, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->processDriverPass($container);
    $this->processClassGenerator($container);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    // @formatter:off
    // phpcs:disable
    $builder
      ->children()
        ->scalarNode('default_driver')
          ->defaultValue('blackbox')
          ->info('Use "blackbox" to test remote site. See "api_driver" for easier integration.')
        ->end()
        ->scalarNode('api_driver')
          ->defaultValue('drush')
          ->info('Bootstraps drupal through "drupal" or "drush".')
        ->end()
        ->scalarNode('drush_driver')
          ->defaultValue('drush')
        ->end()
        ->scalarNode('login_field')
          ->defaultValue('name')
          ->info('User entity property submitted as the login value. Defaults to "name". Set to "mail" for sites that authenticate by email, or any other user property.')
        ->end()
        ->arrayNode('regions')
          ->info("Map of named regions to CSS selectors. Region steps such as 'I press :button in the :region region' resolve against this map." . PHP_EOL
            . '  My region: "#css-selector"' . PHP_EOL
            . '  Content: "#main .region-content"' . PHP_EOL
            . '  Right sidebar: "#sidebar-second"' . PHP_EOL)
          ->useAttributeAsKey('key')
          ->prototype('variable')->end()
        ->end()
        ->arrayNode('text')
          ->info(
            'Text strings, such as Log out or the Username field can be altered via behat.yml if they vary from the default values.' . PHP_EOL
            . '  login_url: "/user"' . PHP_EOL
            . '  logout_url: "/user/logout"' . PHP_EOL
            . '  logout_confirm_url: "/user/logout/confirm"' . PHP_EOL
            . '  log_out: "Sign out"' . PHP_EOL
            . '  log_in: "Sign in"' . PHP_EOL
            . '  password_field: "Enter your password"' . PHP_EOL
            . '  username_field: "Nickname"'
          )
          ->ignoreExtraKeys(FALSE)
          ->addDefaultsIfNotSet()
          ->children()
            ->scalarNode('login_url')
              ->defaultValue('/user')
            ->end()
            ->scalarNode('logout_url')
              ->defaultValue('/user/logout')
            ->end()
            ->scalarNode('logout_confirm_url')
              ->defaultValue('/user/logout/confirm')
            ->end()
            ->scalarNode('log_in')
              ->defaultValue('Log in')
            ->end()
            ->scalarNode('log_out')
              ->defaultValue('Log out')
            ->end()
            ->scalarNode('password_field')
              ->defaultValue('Password')
            ->end()
            ->scalarNode('username_field')
              ->defaultValue('Username')
            ->end()
          ->end()
        ->end()
        ->integerNode('login_wait')
          ->min(0)
          ->defaultValue(0)
          ->info('Maximum seconds to wait for post-login DOM signals (URL change, body render, logged-in selector, logout link). Set to 0 to disable waiting.')
        ->end()
        ->integerNode('ajax_timeout')
          ->min(0)
          ->defaultValue(5)
          ->info('Maximum time (in seconds) to wait for AJAX calls to complete.')
        ->end()
        ->arrayNode('selectors')
          ->ignoreExtraKeys(FALSE)
          ->addDefaultsIfNotSet()
          ->children()
            ->arrayNode('messages')
              ->ignoreExtraKeys(FALSE)
              ->children()
                ->scalarNode('default')->end()
                ->scalarNode('error')->end()
                ->scalarNode('success')->end()
                ->scalarNode('warning')->end()
              ->end()
            ->end()
            ->scalarNode('login_form_selector')
              ->defaultValue('form#user-login,form#user-login-form')
            ->end()
            ->scalarNode('logged_in_selector')
              ->defaultValue('body.logged-in,body.user-logged-in')
            ->end()
          ->end()
        ->end()
        ->arrayNode('mappings')
          ->info('Named value mappings grouped for organisation. A "{{ Key }}" token in any step argument or table cell is replaced with the mapped value before the step runs; whitespace inside the braces is ignored, so "{{ Key }}" and "{{Key}}" are equivalent. Group names are organisational only - a key must be unique across all groups.' . PHP_EOL
            . '  paths:' . PHP_EOL
            . '    User Registration: "/user/register"' . PHP_EOL
            . '    User Login: "/user/login"' . PHP_EOL)
          ->useAttributeAsKey('group')
          ->prototype('array')
            ->useAttributeAsKey('key')
            ->prototype('scalar')->end()
          ->end()
        ->end()
        // Drupal drivers.
        ->arrayNode('blackbox')->end()
        ->arrayNode('drupal')
          ->children()
            ->scalarNode('drupal_root')->end()
          ->end()
        ->end()
        ->arrayNode('drush')
          ->children()
            ->scalarNode('alias')->end()
            ->scalarNode('binary')->defaultValue('vendor/bin/drush')->end()
            ->scalarNode('root')->end()
            ->scalarNode('global_options')->end()
          ->end()
        ->end()
      ->end()
    ->end();
    // phpcs:enable
    // @formatter:on
  }

  /**
   * Load test parameters.
   *
   * Exposes the configured region map under the 'behat_steps.regions' container
   * parameter and surfaces it through the 'region' Mink selector.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   */
  protected function loadParameters(ContainerBuilder $container, array $config): void {
    $regions = $config['regions'] ?? [];

    // Mirror the map into the config so the 'behat_steps.parameters' and
    // 'behat_steps.regions' container parameters always expose the same value,
    // even when the optional 'regions' key was omitted from behat.yml.
    $config['regions'] = $regions;

    // Flatten the grouped mappings to a single key => value map that
    // contexts resolve '{{ Key }}' tokens against. Groups are only a way
    // to organise the configuration, so a key must be unique across them.
    $config['mappings'] = $this->flattenMappings($config['mappings'] ?? []);

    $container->setParameter('behat_steps.parameters', $config);
    $container->setParameter('behat_steps.regions', $regions);
  }

  /**
   * Flattens grouped mappings into a single key => value map.
   *
   * @param array<string, array<string, string>> $grouped
   *   Mappings as configured: group name => (key => value).
   *
   * @return array<string, string>
   *   The flattened key => value map.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the same key appears in more than one group, which would make the
   *   bare-key '{{ Key }}' token ambiguous.
   */
  protected function flattenMappings(array $grouped): array {
    $flat = [];
    $groups = [];

    foreach ($grouped as $group => $entries) {
      foreach ($entries as $key => $value) {
        if (isset($groups[$key])) {
          throw new InvalidConfigurationException(sprintf('Duplicate mapping key "%s" found in groups "%s" and "%s" under "%s: mappings:". Mapping keys must be unique across all groups.', $key, $groups[$key], $group, self::CONFIG_KEY));
        }

        $groups[$key] = $group;
        $flat[$key] = (string) $value;
      }
    }

    return $flat;
  }

  /**
   * Load the blackbox driver.
   */
  protected function loadBlackbox(FileLoader $loader): void {
    // The blackbox driver is the fallback for scenarios that select no other,
    // so it is always registered.
    $loader->load('drivers/blackbox.yml');
  }

  /**
   * Load the Drupal driver.
   *
   * @param \Symfony\Component\DependencyInjection\Loader\FileLoader $loader
   *   The file loader.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   */
  protected function loadDrupal(FileLoader $loader, ContainerBuilder $container, array $config): void {
    if (isset($config['drupal'])) {
      $loader->load('drivers/drupal.yml');
      $container->setParameter('behat_steps.driver.drupal.drupal_root', $config['drupal']['drupal_root']);
    }
  }

  /**
   * Load the Drush driver.
   *
   * @param \Symfony\Component\DependencyInjection\Loader\FileLoader $loader
   *   The file loader.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   *
   * @throws \RuntimeException
   *   When neither a Drush alias nor a Drupal root is configured.
   */
  protected function loadDrush(FileLoader $loader, ContainerBuilder $container, array $config): void {
    if (isset($config['drush'])) {
      $loader->load('drivers/drush.yml');
      if (!isset($config['drush']['alias']) && !isset($config['drush']['root'])) {
        throw new \RuntimeException('Drush `alias` or `root` path is required for the Drush driver.');
      }
      $config['drush']['alias'] ??= FALSE;
      $container->setParameter('behat_steps.driver.drush.alias', $config['drush']['alias']);

      $config['drush']['binary'] ??= 'vendor/bin/drush';
      $config['drush']['binary'] = self::resolveBinaryPath($config['drush']['binary']);
      $container->setParameter('behat_steps.driver.drush.binary', $config['drush']['binary']);

      $config['drush']['root'] ??= FALSE;
      $container->setParameter('behat_steps.driver.drush.root', $config['drush']['root']);

      $this->setDrushOptions($container, $config);
    }
  }

  /**
   * Resolve a relative binary path to an absolute path.
   *
   * Probes the current working directory and its parent to locate the binary.
   * This ensures the path remains valid after the Drupal API driver changes
   * the working directory to DRUPAL_ROOT via chdir().
   *
   * Absolute paths and binaries without a directory separator (bare commands
   * like 'drush' that resolve via $PATH) are returned as-is.
   */
  public static function resolveBinaryPath(string $binary): string {
    if (str_starts_with($binary, '/')) {
      return $binary;
    }

    // Bare command names (no directory separator) resolve via $PATH.
    if (!str_contains($binary, '/')) {
      return $binary;
    }

    $cwd = (string) getcwd();

    $candidate = $cwd . '/' . $binary;
    if (file_exists($candidate)) {
      return $candidate;
    }

    // Probe the parent directory, which covers a working directory one level
    // deep such as a Drupal root inside a project.
    $candidate = dirname($cwd) . '/' . $binary;
    if (file_exists($candidate)) {
      return $candidate;
    }

    return $binary;
  }

  /**
   * Set global drush arguments.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   */
  protected function setDrushOptions(ContainerBuilder $container, array $config): void {
    if (isset($config['drush']['global_options'])) {
      $definition = $container->getDefinition('behat_steps.driver.drush');
      $definition->addMethodCall('setArguments', [$config['drush']['global_options']]);
    }
  }

  /**
   * Process the driver pass.
   */
  protected function processDriverPass(ContainerBuilder $container): void {
    $driver_pass = new DriverPass();
    $driver_pass->process($container);
  }

  /**
   * Switch to custom class generator.
   *
   * Behat collects generators by tag before an activated extension's
   * 'process()' runs, and it collects them as references to a service id, so
   * replacing the definition behind that id swaps the class in place.
   */
  protected function processClassGenerator(ContainerBuilder $container): void {
    $definition = new Definition(ClassGenerator::class);
    $container->setDefinition(ContextExtension::CLASS_GENERATOR_TAG . '.simple', $definition);
  }

}
