<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Mink\Element\DocumentElement as UpstreamDocumentElement;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use DrevOps\BehatSteps\Behat\Listener\DriverListener;
use DrevOps\BehatSteps\Behat\Mink\Element\DocumentElement;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
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
    $this->aliasDocumentElement();

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
    $loader->load('services.yml');

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
    $this->processSuiteDrivers($container);
    $this->processClassGenerator($container);
    $this->processMinkAjaxTimeout($container);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    // @formatter:off
    // phpcs:disable
    $builder
      ->children()
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
          ->prototype('scalar')->end()
        ->end()
        ->arrayNode('text')
          ->info(
            'Text strings, such as Log out or the Username field can be altered in the Behat configuration if they vary from the default values.' . PHP_EOL
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
              ->info('Path the login steps submit the login form on.')
            ->end()
            ->scalarNode('logout_url')
              ->defaultValue('/user/logout')
              ->info('Path the logout steps request.')
            ->end()
            ->scalarNode('logout_confirm_url')
              ->defaultValue('/user/logout/confirm')
              ->info('Path of the logout confirmation form, submitted when the site asks to confirm.')
            ->end()
            ->scalarNode('log_in')
              ->defaultValue('Log in')
              ->info('Text of the login submit button.')
            ->end()
            ->scalarNode('log_out')
              ->defaultValue('Log out')
              ->info('Text of the logout link.')
            ->end()
            ->scalarNode('password_field')
              ->defaultValue('Password')
              ->info('Label of the password field on the login form.')
            ->end()
            ->scalarNode('username_field')
              ->defaultValue('Username')
              ->info('Label of the username field on the login form.')
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
          ->info('CSS selectors the steps resolve page structures against.')
          ->ignoreExtraKeys(FALSE)
          ->addDefaultsIfNotSet()
          ->children()
            ->arrayNode('messages')
              ->info('Selectors of the message regions asserted by the message steps, one per severity.')
              ->ignoreExtraKeys(FALSE)
              ->children()
                ->scalarNode('default')->info('Selector matching a message of any severity.')->end()
                ->scalarNode('error')->info('Selector matching an error message.')->end()
                ->scalarNode('success')->info('Selector matching a success message.')->end()
                ->scalarNode('warning')->info('Selector matching a warning message.')->end()
              ->end()
            ->end()
            ->scalarNode('login_form_selector')
              ->defaultValue('form#user-login,form#user-login-form')
              ->info('Selector of the login form, used to tell a login page from a page that merely holds a login block.')
            ->end()
            ->scalarNode('logged_in_selector')
              ->defaultValue('body.logged-in,body.user-logged-in')
              ->info('Selector present only while a user is authenticated, used to confirm a login took effect.')
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
        ->arrayNode('blackbox')
          ->info('Settings of the driver that drives the site through the browser only. It has no options, and it performs no backend operation, so it provides no capability a step can resolve.')
        ->end()
        ->arrayNode('drupal')
          ->info('Settings of the driver that bootstraps Drupal in-process.')
          ->children()
            ->scalarNode('drupal_root')
              ->isRequired()
              ->cannotBeEmpty()
              ->info('Path to the Drupal root the in-process driver bootstraps.')
            ->end()
          ->end()
        ->end()
        ->arrayNode('drush')
          ->info('Settings of the driver that reaches the site by running Drush.')
          ->children()
            ->scalarNode('alias')->info('Drush site alias to run every command against.')->end()
            ->scalarNode('binary')->defaultValue('vendor/bin/drush')->info('Path to the Drush binary.')->end()
            ->scalarNode('root')->info('Drupal root passed to Drush, for a site Drush cannot locate on its own.')->end()
            ->scalarNode('global_options')->info('Options appended to every Drush command, such as "--uri=http://example.com".')->end()
          ->end()
        ->end()
      ->end()
    ->end();
    // phpcs:enable
    // @formatter:on
  }

  /**
   * Puts this package's document element in place of Mink's own.
   *
   * The alias must be installed before Mink autoloads the class it replaces,
   * so the check reads declared classes only and an already-declared name is
   * left alone.
   *
   * A Behat run loads this extension while the container is built, before
   * any element is requested from Mink, so the replacement holds for the
   * session. A process that loaded Mink's class first, such as this package's
   * PHPUnit suite, keeps Mink's behaviour, which affects only page-text
   * extraction.
   */
  protected function aliasDocumentElement(): void {
    if (!class_exists(UpstreamDocumentElement::class, FALSE)) {
      // @codeCoverageIgnoreStart
      class_alias(DocumentElement::class, UpstreamDocumentElement::class, TRUE);
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * Loads test parameters.
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
    // even when the optional 'regions' key was omitted from the configuration.
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
   * Loads the blackbox driver.
   */
  protected function loadBlackbox(FileLoader $loader): void {
    // The blackbox driver is the fallback for scenarios that select no other,
    // so it is always registered.
    $loader->load('drivers/blackbox.yml');
  }

  /**
   * Loads the Drupal driver.
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
   * Loads the Drush driver.
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
   * Resolves a relative binary path to an absolute path.
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
   * Sets global Drush arguments.
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
   * Runs the driver pass.
   */
  protected function processDriverPass(ContainerBuilder $container): void {
    $driver_pass = new DriverPass();
    $driver_pass->process($container);
  }

  /**
   * Validates the ordered driver list each suite declares.
   *
   * A suite's 'drivers' setting is both the allow-list and the precedence
   * order. Checking it here rather than at scenario start means a typo in a
   * suite that rarely runs still fails the build.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a tag name is not tag-safe, or names a driver that is not
   *   registered.
   */
  protected function processSuiteDrivers(ContainerBuilder $container): void {
    if (!$container->hasParameter('suite.configurations')) {
      return;
    }

    $suites = $container->getParameter('suite.configurations');

    if (!is_array($suites)) {
      return;
    }

    $registered = DriverPass::registeredNames($container);

    foreach ($suites as $suite => $configuration) {
      $drivers = is_array($configuration) && is_array($configuration['settings'] ?? NULL) ? $configuration['settings'][DriverListener::DRIVERS_SETTING] ?? NULL : NULL;

      if (!is_array($drivers)) {
        continue;
      }

      $this->validateSuiteDrivers((string) $suite, $drivers, $registered);
    }
  }

  /**
   * Validates one suite's driver list.
   *
   * @param string $suite
   *   The suite name, for the error message.
   * @param array<array-key, mixed> $drivers
   *   The suite's 'drivers' setting, as configured.
   * @param array<int, string> $registered
   *   The names the extension registers drivers under.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a tag name is not tag-safe, or names a driver that is not
   *   registered.
   */
  protected function validateSuiteDrivers(string $suite, array $drivers, array $registered): void {
    foreach ($drivers as $tag => $name) {
      if (!is_string($name) || $name === '') {
        throw new InvalidConfigurationException(sprintf('The "%s" suite lists a driver that is not a name under "drivers:". Write each entry as a driver name, or as "tag: driver name".', $suite));
      }

      $tag = is_int($tag) ? $name : $tag;

      // A tag name is typed into a feature file after '@driver:', so it cannot
      // carry whitespace or a second colon.
      if (preg_match('/^[a-z0-9_-]+$/', strtolower($tag)) !== 1) {
        throw new InvalidConfigurationException(sprintf('The "%s" suite names a driver "%s" under "drivers:". A driver name may hold only letters, digits, "_" and "-", so that "@driver:%s" is a valid tag.', $suite, $tag, $tag));
      }

      if (!in_array(strtolower($name), $registered, TRUE)) {
        throw new InvalidConfigurationException(sprintf('The "%s" suite lists the driver "%s" under "drivers:", which is not registered. Registered drivers: %s.', $suite, $name, $registered === [] ? 'none' : implode(', ', $registered)));
      }
    }
  }

  /**
   * Applies an 'ajax_timeout' the Mink configuration tree supplied.
   *
   * Runs as a process pass rather than during 'load()' because the two
   * extensions load in whichever order the suite lists them.
   */
  protected function processMinkAjaxTimeout(ContainerBuilder $container): void {
    if (!$container->hasParameter(MinkExtension::DEPRECATED_AJAX_TIMEOUT_PARAMETER)) {
      return;
    }

    $parameters = $container->getParameter('behat_steps.parameters');

    if (!is_array($parameters)) {
      return;
    }

    $parameters['ajax_timeout'] = $container->getParameter(MinkExtension::DEPRECATED_AJAX_TIMEOUT_PARAMETER);
    $container->setParameter('behat_steps.parameters', $parameters);
  }

  /**
   * Switches to the custom class generator.
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
