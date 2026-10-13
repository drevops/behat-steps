<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Mink\Element\DocumentElement as UpstreamDocumentElement;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Extension\Auth\Authenticator;
use DrevOps\BehatSteps\Extension\Browser\Element\DocumentElement;
use DrevOps\BehatSteps\Extension\Drupal\ServiceContainer\DrupalBackendProvider;
use DrevOps\BehatSteps\Extension\Generator\ClassGenerator;
use DrevOps\BehatSteps\Extension\Http\HttpClientFactory;
use DrevOps\BehatSteps\Extension\ServiceContainer\Driver\BrowserKitFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Behat extension wiring the backend layer, services and hooks into a suite.
 */
final class BehatStepsExtension implements ExtensionInterface {

  /**
   * Key this extension's settings live under in the Behat configuration.
   */
  public const string CONFIG_KEY = 'behat_steps';

  /**
   * Container parameter holding the configured ordered backend list.
   */
  public const string BACKENDS_PARAMETER = 'behat_steps.backends';

  /**
   * Service ID of the transport the detached and bare browsers send through.
   */
  public const string TRANSPORT_SERVICE = 'behat_steps.http_client';

  /**
   * The factory registered with Mink, NULL when the suite registers no Mink.
   */
  protected ?BrowserKitFactory $browserKitFactory = NULL;

  /**
   * The providers of the shipped backends, in registration order.
   *
   * @var array<int, \DrevOps\BehatSteps\Extension\ServiceContainer\BackendProviderInterface>
   */
  protected readonly array $backendProviders;

  /**
   * Constructs the extension with the shipped backend providers.
   */
  public function __construct() {
    $this->backendProviders = [new DrupalBackendProvider()];
  }

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
    $loader->load('backends/blackbox.yml');

    $this->loadParameters($container, $config);

    foreach ($this->backendProviders as $provider) {
      $provider->load($container, $config);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->processBackendPass($container);

    foreach ($this->backendProviders as $provider) {
      $provider->process($container);
    }

    $this->processBackends($container);
    $this->processClassGenerator($container);
    $this->processHttpClient($container);
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    $defaults = $this->collectParameterDefaults();

    // @formatter:off
    // phpcs:disable
    $builder
      ->beforeNormalization()
        ->ifArray()
        ->then(static fn(array $config): array => self::rejectRenamedKeys($config))
      ->end();

    $children = $builder->children();

    $children
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
          . '  logout: "Sign out"' . PHP_EOL
          . '  login: "Sign in"' . PHP_EOL
          . '  password_field: "Enter your password"' . PHP_EOL
          . '  username_field: "Nickname"'
        )
        ->ignoreExtraKeys(FALSE)
        ->addDefaultsIfNotSet()
        ->children()
          ->scalarNode('login_url')
            ->defaultValue($defaults['text']['login_url'] ?? NULL)
            ->info('Path the login steps submit the login form on.')
          ->end()
          ->scalarNode('logout_url')
            ->defaultValue($defaults['text']['logout_url'] ?? NULL)
            ->info('Path the logout steps request.')
          ->end()
          ->scalarNode('logout_confirm_url')
            ->defaultValue($defaults['text']['logout_confirm_url'] ?? NULL)
            ->info('Path of the logout confirmation form, submitted when the site asks to confirm.')
          ->end()
          ->scalarNode('login')
            ->defaultValue($defaults['text']['login'] ?? NULL)
            ->info('Text of the login submit button.')
          ->end()
          ->scalarNode('logout')
            ->defaultValue($defaults['text']['logout'] ?? NULL)
            ->info('Text of the logout link.')
          ->end()
          ->scalarNode('password_field')
            ->defaultValue($defaults['text']['password_field'] ?? NULL)
            ->info('Label of the password field on the login form.')
          ->end()
          ->scalarNode('username_field')
            ->defaultValue($defaults['text']['username_field'] ?? NULL)
            ->info('Label of the username field on the login form.')
          ->end()
        ->end()
      ->end()
      ->integerNode('login_wait')
        ->min(0)
        ->defaultValue(0)
        ->info(sprintf('Maximum seconds to wait for post-login DOM signals (URL change, body render, logged-in selector, logout link). Set to 0 to disable waiting. A JavaScript session still waits up to %d seconds, or this value when it is larger, for the browser to leave the login form or show the logged-in selector, and for the logged-in selector or the logout link on the homepage. Each wait ends as soon as its signal appears.', Authenticator::JAVASCRIPT_LOGIN_WAIT))
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
            ->defaultValue($defaults['selectors']['login_form_selector'] ?? NULL)
            ->info('Selector of the login form, used to tell a login page from a page that merely holds a login block.')
          ->end()
          ->scalarNode('logged_in_selector')
            ->defaultValue($defaults['selectors']['logged_in_selector'] ?? NULL)
            ->info('Selector present only while a user is authenticated, used to confirm a login took effect.')
          ->end()
        ->end()
      ->end()
      ->arrayNode('blackbox')
        ->info('Settings of the backend that drives the site through the browser only. It has no options, and it performs no backend operation, so it provides no capability a step can resolve.')
      ->end();
    // phpcs:enable
    // @formatter:on
    foreach ($this->backendProviders as $provider) {
      $provider->configure($children);
    }
  }

  /**
   * Registers the first-party factory for the 'browserkit_http' browser driver.
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
   * so the check reads declared classes only. An already-declared name is
   * left alone.
   *
   * Behat loads this extension while the container is built, before
   * any element is requested from Mink, so the replacement holds for the
   * session. A process that loaded Mink's class first, such as this package's
   * PHPUnit suite, keeps Mink's behavior, which affects only page-text
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
   * Merges the login text and selector defaults the backend providers ship.
   *
   * A later provider overrides an earlier one, key by key.
   *
   * @return array{text: array<string, string>, selectors: array<string, string>}
   *   Values keyed by setting name.
   */
  protected function collectParameterDefaults(): array {
    $defaults = ['text' => [], 'selectors' => []];

    foreach ($this->backendProviders as $provider) {
      $provided = $provider->getParameterDefaults();
      $defaults['text'] = $provided['text'] + $defaults['text'];
      $defaults['selectors'] = $provided['selectors'] + $defaults['selectors'];
    }

    return $defaults;
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

    // Mirror the map into the config so 'behat_steps.parameters' and
    // 'behat_steps.regions' expose the same value when 'regions' is omitted.
    $config['regions'] = $regions;

    $container->setParameter('behat_steps.parameters', $config);
    $container->setParameter('behat_steps.regions', $regions);
    $container->setParameter(self::BACKENDS_PARAMETER, $config['backends'] ?? []);
  }

  /**
   * Rejects a key that the extension reads at another path.
   *
   * The 'selectors' and 'text' nodes keep undeclared keys, so a project can
   * add entries of its own and read them back. A stray 'selectors: messages:'
   * or 'text: log_in:' is therefore accepted and never read, and the steps
   * that need its value fail one by one.
   *
   * @param array<string, mixed> $config
   *   The extension configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the configuration carries 'selectors: messages:', 'text: log_in:'
   *   or 'text: log_out:'.
   */
  protected function rejectMovedKeys(array $config): void {
    if (isset($config['selectors']['messages'])) {
      throw new InvalidConfigurationException(sprintf('The "selectors: messages:" setting under "%s" moved to "steps: message: selectors:". Move each severity selector across.', self::CONFIG_KEY));
    }

    $text = $config['text'] ?? [];

    foreach (['log_in' => 'login', 'log_out' => 'logout'] as $key => $replacement) {
      if (is_array($text) && array_key_exists($key, $text)) {
        throw new InvalidConfigurationException(sprintf('The "text: %s:" setting under "%s" moved to "text: %s:". Rename the key; its value is unchanged.', $key, self::CONFIG_KEY, $replacement));
      }
    }
  }

  /**
   * Rejects a 'drivers' key, naming 'backends' in its place.
   *
   * The tree rejects an undeclared key before 'load()' runs, with a message
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

      // Resolution lowercases a name, so 2 entries differing only by case
      // would collapse into 1 and the later would silently replace the
      // earlier.
      if (isset($seen[$tag])) {
        throw new InvalidConfigurationException(sprintf('The "backends" list under "%s" names "%s" twice. A name is matched without regard to case, so it may appear only once.', self::CONFIG_KEY, $tag));
      }

      $seen[$tag] = TRUE;
    }
  }

  /**
   * Validates 1 entry of the backend list and returns its tag name.
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
    // carry whitespace or a second colon. '$' would also match before a
    // trailing newline, so the pattern ends in '\z'.
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
   * 'process()' runs, as references to a service id. Replacing the definition
   * behind that id swaps the class in place.
   */
  protected function processClassGenerator(ContainerBuilder $container): void {
    $definition = new Definition(ClassGenerator::class);
    $container->setDefinition(ContextExtension::CLASS_GENERATOR_TAG . '.simple', $definition);
  }

}
