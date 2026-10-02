<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Mink\Element\DocumentElement as UpstreamDocumentElement;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Mink\Element\DocumentElement;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Behat extension wiring the backend layer, services and hooks into a suite.
 */
class BehatStepsExtension implements ExtensionInterface {

  /**
   * Key this extension's settings live under in the Behat configuration.
   */
  public const CONFIG_KEY = 'behat_steps';

  /**
   * Container parameter holding the configured ordered backend list.
   */
  public const BACKENDS_PARAMETER = 'behat_steps.backends';

  /**
   * Service ID of the transport the detached and bare browsers send through.
   */
  public const TRANSPORT_SERVICE = 'behat_steps.http_client';

  /**
   * The factory registered with Mink, NULL when the suite registers no Mink.
   */
  protected ?BrowserKitFactory $browserKitFactory = NULL;

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
    $this->initializeBrowserKitFactory($extensionManager);
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
    $this->processBackendPass($container);
    $this->processBackends($container);
    $this->processClassGenerator($container);
    $this->processHttpClient($container);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    // @formatter:off
    // phpcs:disable
    $builder
      ->beforeNormalization()
        ->ifArray()
        ->then(static fn(array $config): array => static::rejectRenamedKeys($config))
      ->end()
      ->children()
        ->arrayNode('backends')
          ->info('Ordered list of the backends a scenario may resolve, most preferred first. It is both the allow-list and the precedence order: a step names the capability it needs and the first backend here providing it answers. A bare entry names a registered backend; a "tag: backend" entry gives it a name of its own, so the same feature file runs against a different backend in another profile. Omit it to get every registered backend, in registration order.' . PHP_EOL
            . '  - drupal' . PHP_EOL
            . '  - api: acme-jsonapi' . PHP_EOL
            . '  - blackbox' . PHP_EOL)
          ->normalizeKeys(FALSE)
          ->prototype('scalar')->end()
        ->end()
        ->scalarNode('login_field')
          ->defaultValue('name')
          ->info('User entity property submitted as the login value. Defaults to "name". Set to "mail" for sites that authenticate by email, or any other user property.')
        ->end()
        ->arrayNode('regions')
          ->info("Map of named regions to CSS selectors. Region steps such as 'I press the button :button in the region :region' resolve against this map." . PHP_EOL
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
        ->arrayNode('steps')
          ->info('Default values of the options the step traits declare, keyed by trait group and then by option name. Each group is named after the trait that declares it, so "JavascriptTrait" reads "javascript" and "BigPipeTrait" reads "big_pipe". A group naming a trait none of the registered contexts composes is ignored, so one profile can carry the defaults of every suite.' . PHP_EOL
            . '  javascript:' . PHP_EOL
            . '    enabled: true' . PHP_EOL
            . '    fail_on_errors: false' . PHP_EOL
            . '  wait:' . PHP_EOL
            . '    ajax_timeout: 10' . PHP_EOL)
          ->useAttributeAsKey('group')
          ->prototype('variable')->end()
        ->end()
        ->arrayNode('selectors')
          ->info('CSS selectors the steps resolve page structures against.')
          ->ignoreExtraKeys(FALSE)
          ->addDefaultsIfNotSet()
          ->children()
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
        ->arrayNode('blackbox')
          ->info('Settings of the backend that drives the site through the browser only. It has no options, and it performs no backend operation, so it provides no capability a step can resolve.')
        ->end()
        ->arrayNode('drupal')
          ->info('Settings of the backend that bootstraps Drupal in-process.')
          ->children()
            ->scalarNode('drupal_root')
              ->isRequired()
              ->cannotBeEmpty()
              ->info('Path to the Drupal root the in-process backend bootstraps.')
            ->end()
          ->end()
        ->end()
        ->arrayNode('drush')
          ->info('Settings of the backend that reaches the site by running Drush.')
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
   * Registers the first-party factory behind Mink's 'browserkit_http' driver.
   *
   * Mink keys its driver factories by driver name, so this registration
   * replaces Mink's own. Behat initializes every extension before it builds
   * any configuration tree, so the replacement is in place when Mink declares
   * the session options.
   *
   * @param \Behat\Testwork\ServiceContainer\ExtensionManager $extension_manager
   *   The manager holding every activated extension.
   */
  protected function initializeBrowserKitFactory(ExtensionManager $extension_manager): void {
    $mink = $extension_manager->getExtension('mink');

    if (!$mink instanceof MinkExtension) {
      return;
    }

    $this->browserKitFactory = new BrowserKitFactory();

    $mink->registerDriverFactory($this->browserKitFactory);
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
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   */
  protected function loadParameters(ContainerBuilder $container, array $config): void {
    $this->rejectMovedKeys($config);

    $regions = $config['regions'] ?? [];

    // Mirror the map into the config so the 'behat_steps.parameters' and
    // 'behat_steps.regions' container parameters always expose the same value,
    // even when the optional 'regions' key was omitted from the configuration.
    $config['regions'] = $regions;

    $container->setParameter('behat_steps.parameters', $config);
    $container->setParameter('behat_steps.regions', $regions);
    $container->setParameter(self::BACKENDS_PARAMETER, $config['backends'] ?? []);
  }

  /**
   * Rejects a 'selectors' key that a trait declares as an option.
   *
   * The 'selectors' node keeps the keys it does not declare, so that a project
   * can add named selectors of its own and read them back. A stray
   * 'selectors: messages:' is therefore accepted and never read, and the
   * message steps would fail one by one for a missing selector.
   *
   * @param array<string, mixed> $config
   *   The extension configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the configuration carries 'selectors: messages:'.
   */
  protected function rejectMovedKeys(array $config): void {
    if (isset($config['selectors']['messages'])) {
      throw new InvalidConfigurationException(sprintf('The "selectors: messages:" setting under "%s" moved to "steps: message: selectors:". Move each severity selector across.', self::CONFIG_KEY));
    }
  }

  /**
   * Rejects a 'drivers' key, naming 'backends' in its place.
   *
   * The tree refuses an undeclared key before 'load()' runs, with a message
   * listing only the declared keys, so this check runs before normalization.
   *
   * @param array<array-key, mixed> $config
   *   The extension configuration, as written.
   *
   * @return array<array-key, mixed>
   *   The configuration, unchanged.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the configuration carries a 'drivers' key.
   */
  protected static function rejectRenamedKeys(array $config): array {
    if (array_key_exists('drivers', $config)) {
      throw new InvalidConfigurationException(sprintf('The "drivers" setting under "%s" moved to "backends". Rename the key; its entries are unchanged.', self::CONFIG_KEY));
    }

    return $config;
  }

  /**
   * Loads the blackbox backend.
   */
  protected function loadBlackbox(FileLoader $loader): void {
    // The blackbox backend needs no configuration, unlike the Drupal and Drush
    // backends, which load only when configured.
    $loader->load('backends/blackbox.yml');
  }

  /**
   * Loads the Drupal backend.
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
      $loader->load('backends/drupal.yml');
      $container->setParameter('behat_steps.backend.drupal.drupal_root', $config['drupal']['drupal_root']);
    }
  }

  /**
   * Loads the Drush backend.
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
      $loader->load('backends/drush.yml');
      if (!isset($config['drush']['alias']) && !isset($config['drush']['root'])) {
        throw new \RuntimeException('Drush `alias` or `root` path is required for the Drush backend.');
      }
      $config['drush']['alias'] ??= FALSE;
      $container->setParameter('behat_steps.backend.drush.alias', $config['drush']['alias']);

      $config['drush']['binary'] ??= 'vendor/bin/drush';
      $config['drush']['binary'] = self::resolveBinaryPath($config['drush']['binary']);
      $container->setParameter('behat_steps.backend.drush.binary', $config['drush']['binary']);

      $config['drush']['root'] ??= FALSE;
      $container->setParameter('behat_steps.backend.drush.root', $config['drush']['root']);

      $this->setDrushOptions($container, $config);
    }
  }

  /**
   * Resolves a relative binary path to an absolute path.
   *
   * Probes the current working directory and its parent to locate the binary.
   * This ensures the path remains valid after 'Core::bootstrap()' changes the
   * working directory to DRUPAL_ROOT via chdir().
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
      $definition = $container->getDefinition('behat_steps.backend.drush');
      $definition->addMethodCall('setArguments', [$config['drush']['global_options']]);
    }
  }

  /**
   * Runs the backend pass.
   */
  protected function processBackendPass(ContainerBuilder $container): void {
    $backend_pass = new BackendPass();
    $backend_pass->process($container);
  }

  /**
   * Validates the ordered backend list the extension configuration declares.
   *
   * The 'backends' list is both the allow-list and the precedence order.
   * Checking it at container build means a typo fails before the first
   * scenario rather than at the step that would have resolved it.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When an entry is not a name, a tag name is not tag-safe, or a name
   *   refers to a backend that is not registered.
   */
  protected function processBackends(ContainerBuilder $container): void {
    if (!$container->hasParameter(self::BACKENDS_PARAMETER)) {
      return;
    }

    $backends = $container->getParameter(self::BACKENDS_PARAMETER);

    if (!is_array($backends)) {
      return;
    }

    $registered = BackendPass::registeredNames($container);
    $seen = [];

    foreach ($backends as $tag => $name) {
      $tag = $this->validateBackendEntry($tag, $name, $registered);

      // Resolution lowercases a name, so two entries differing only by case
      // would collapse into one and the later would silently take the
      // earlier's place in the order.
      if (isset($seen[$tag])) {
        throw new InvalidConfigurationException(sprintf('The "backends" list under "%s" names "%s" twice. A name is matched without regard to case, so it may appear only once.', self::CONFIG_KEY, $tag));
      }

      $seen[$tag] = TRUE;
    }
  }

  /**
   * Validates one entry of the backend list and returns its tag name.
   *
   * @param int|string $tag
   *   The entry's key: an integer for a bare entry, the tag name otherwise.
   * @param mixed $name
   *   The entry's value, expected to be a registered backend name.
   * @param array<int, string> $registered
   *   The names the extension registers backends under.
   *
   * @return string
   *   The entry's tag name, lowercased.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the entry is not a name, the tag name is not tag-safe, or the name
   *   refers to a backend that is not registered.
   */
  protected function validateBackendEntry(int|string $tag, mixed $name, array $registered): string {
    if (!is_string($name) || $name === '') {
      throw new InvalidConfigurationException(sprintf('The "backends" list under "%s" holds an entry that is not a backend name. Write each entry as a backend name, or as "tag: backend name".', self::CONFIG_KEY));
    }

    $tag = strtolower(is_int($tag) ? $name : $tag);

    // A tag name is typed into a feature file after '@backend:', so it cannot
    // carry whitespace or a second colon. '\z' rather than '$', which would
    // also match before a trailing newline and let one through.
    if (preg_match('/^[a-z0-9_-]+\z/', $tag) !== 1) {
      throw new InvalidConfigurationException(sprintf('The "backends" list under "%s" names a backend "%s". A backend name may hold only letters, digits, "_" and "-", so that "@backend:%s" is a valid tag.', self::CONFIG_KEY, $tag, $tag));
    }

    if (!in_array(strtolower($name), $registered, TRUE)) {
      throw new InvalidConfigurationException(sprintf('The "backends" list under "%s" names the backend "%s", which is not registered. Registered backends: %s.', self::CONFIG_KEY, $name, $registered === [] ? 'none' : implode(', ', $registered)));
    }

    return $tag;
  }

  /**
   * Defines the transport the detached and bare browsers send through.
   *
   * Mink hands the 'browserkit_http' factory each session's options while the
   * extensions load, so they are read here, in the process pass after it.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When 2 'browserkit_http' sessions declare different options.
   */
  protected function processHttpClient(ContainerBuilder $container): void {
    $options = $this->browserKitFactory?->getClientOptions() ?? [];
    $base_url = $container->hasParameter('mink.base_url') ? $container->getParameter('mink.base_url') : NULL;

    $definition = new Definition(HttpClientInterface::class, [$options, is_string($base_url) ? $base_url : NULL]);
    $definition->setFactory([HttpClientFactory::class, 'createTransport']);

    $container->setDefinition(self::TRANSPORT_SERVICE, $definition);
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
