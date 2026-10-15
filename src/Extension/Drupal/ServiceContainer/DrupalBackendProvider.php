<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\ServiceContainer;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Extension\ServiceContainer\BackendProviderInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Ships the backends that reach a Drupal site: in-process and over Drush.
 */
final class DrupalBackendProvider implements BackendProviderInterface {

  /**
   * Service ID of the backend that bootstraps Drupal in-process.
   */
  public const string DRUPAL_SERVICE = 'behat_steps.backend.drupal';

  /**
   * Service ID of the backend that runs Drush.
   */
  public const string DRUSH_SERVICE = 'behat_steps.backend.drush';

  /**
   * Parameter holding the configured Core class, or NULL for the Core the
   * package ships for the detected major.
   */
  public const string CORE_PARAMETER = 'behat_steps.backend.drupal.core';

  /**
   * Parameter holding the configured map of field types to handler classes.
   */
  public const string FIELD_HANDLERS_PARAMETER = 'behat_steps.backend.drupal.field_handlers';

  /**
   * Parameter holding the alias classes the in-process backend registers.
   */
  public const string DRUPAL_CREATION_ALIASES_PARAMETER = 'behat_steps.backend.drupal.creation_aliases';

  /**
   * Parameter holding the alias classes the Drush backend registers.
   */
  public const string DRUSH_CREATION_ALIASES_PARAMETER = 'behat_steps.backend.drush.creation_aliases';

  /**
   * {@inheritdoc}
   */
  public function configure(NodeBuilder $children): void {
    // @formatter:off
    // phpcs:disable
    $children
      ->arrayNode('drupal')
        ->info('Settings of the backend that bootstraps Drupal in-process.')
        ->children()
          ->scalarNode('drupal_root')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Path to the Drupal root the in-process backend bootstraps.')
          ->end()
          ->scalarNode('core')
            ->info('Class of the Core the in-process backend delegates to, in place of the one the package ships for the Drupal major the backend detects. A class implements CoreInterface, and one built on a shipped Core extends the Core for that major.')
          ->end()
          ->arrayNode('field_handlers')
            ->info('Map of field types to the handler classes that turn a value a step gives a field into the value Drupal stores. An entry adds a handler for a field type the package ships none for, or replaces the shipped one. A class implements FieldHandlerInterface.' . PHP_EOL
              . '  rating: Acme\Behat\Field\RatingHandler' . PHP_EOL
              . '  datetime: Acme\Behat\Field\FiscalDatetimeHandler' . PHP_EOL)
            ->useAttributeAsKey('field_type')
            ->prototype('scalar')->cannotBeEmpty()->end()
          ->end()
          ->arrayNode('creation_aliases')
            ->info('List of creation alias classes the backend registers after the ones it ships. An alias resolves a value a creation step gives an entity that is not a field of it, such as "author" on a node. An entry adds an alias, or replaces the shipped one with the same name on the same entity type. A class implements PreCreateAliasInterface or PostCreateAliasInterface and takes no constructor arguments.' . PHP_EOL
              . '  - Acme\Behat\Alias\OrganizationAlias' . PHP_EOL)
            ->prototype('scalar')->cannotBeEmpty()->end()
          ->end()
        ->end()
      ->end()
      ->arrayNode('drush')
        ->info('Settings of the backend that reaches the site by running Drush 13 or newer.')
        ->children()
          ->scalarNode('alias')->info('Drush site alias to run every command against.')->end()
          ->scalarNode('binary')->defaultValue('vendor/bin/drush')->info('Path to the Drush binary.')->end()
          ->scalarNode('root')->info('Drupal root passed to Drush, for a site Drush cannot locate on its own.')->end()
          ->scalarNode('global_options')->info('Options appended to every Drush command, such as "--uri=http://example.com".')->end()
          ->arrayNode('creation_aliases')
            ->info('List of creation alias classes the backend registers after the ones it ships. The Drush backend creates only users, so it runs only the aliases for the "user" entity type. A class implements PreCreateAliasInterface or PostCreateAliasInterface and takes no constructor arguments.' . PHP_EOL
              . '  - Acme\Behat\Alias\DepartmentAlias' . PHP_EOL)
            ->prototype('scalar')->cannotBeEmpty()->end()
          ->end()
        ->end()
      ->end();
    // phpcs:enable
    // @formatter:on
  }

  /**
   * {@inheritdoc}
   */
  public function getParameterDefaults(): array {
    return [
      'text' => [
        'login_url' => '/user',
        'logout_url' => '/user/logout',
        'logout_confirm_url' => '/user/logout/confirm',
        'login' => 'Log in',
        'logout' => 'Log out',
        'password_field' => 'Password',
        'username_field' => 'Username',
      ],
      'selectors' => [
        'login_form_selector' => 'form#user-login,form#user-login-form',
        'logged_in_selector' => 'body.logged-in,body.user-logged-in',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function load(ContainerBuilder $container, array $config): void {
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));

    $this->loadDrupal($loader, $container, $config);
    $this->loadDrush($loader, $container, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->processDrupal($container);
    $this->registerCreationAliases($container, self::DRUSH_SERVICE, self::DRUSH_CREATION_ALIASES_PARAMETER, 'drush.creation_aliases');
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

    // Probe the parent directory, which covers a working directory 1 level
    // deep such as a Drupal root inside a project.
    $candidate = dirname($cwd) . '/' . $binary;

    if (file_exists($candidate)) {
      return $candidate;
    }

    return $binary;
  }

  /**
   * Builds a creation alias from a class a 'creation_aliases' option names.
   *
   * @param string $option
   *   The option naming the class, such as 'drupal.creation_aliases'.
   * @param string $class
   *   The alias class.
   *
   * @return \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface
   *   The alias, built with no constructor arguments.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the class does not exist, does not implement
   *   'CreationAliasInterface', cannot be instantiated or requires
   *   constructor arguments.
   */
  public static function buildCreationAlias(string $option, string $class): CreationAliasInterface {
    if (!class_exists($class) && !interface_exists($class)) {
      throw new InvalidConfigurationException(sprintf('The "%s" option names the class "%s", which does not exist.', $option, $class));
    }

    if (!is_subclass_of($class, CreationAliasInterface::class)) {
      throw new InvalidConfigurationException(sprintf('The "%s" option names the class "%s", which does not implement "%s".', $option, $class, CreationAliasInterface::class));
    }

    $reflection = new \ReflectionClass($class);

    if (!$reflection->isInstantiable()) {
      throw new InvalidConfigurationException(sprintf('The "%s" option names the class "%s", which cannot be instantiated.', $option, $class));
    }

    if (($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
      throw new InvalidConfigurationException(sprintf('The "%s" option names the class "%s", whose constructor requires arguments. Build it in a context and register it through CreationAliasCapabilityInterface::registerCreationAlias().', $option, $class));
    }

    return new $class();
  }

  /**
   * Loads the in-process backend.
   *
   * @param \Symfony\Component\DependencyInjection\Loader\FileLoader $loader
   *   The file loader.
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, mixed> $config
   *   The extension configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the Core class is configured empty.
   */
  protected function loadDrupal(FileLoader $loader, ContainerBuilder $container, array $config): void {
    if (isset($config['drupal'])) {
      $loader->load('backends/drupal.yml');

      if (($config['drupal']['core'] ?? NULL) === '') {
        throw new InvalidConfigurationException('Drupal "core" is empty. Set a value, or leave it out.');
      }

      $container->setParameter('behat_steps.backend.drupal.drupal_root', $config['drupal']['drupal_root']);
      $container->setParameter(self::CORE_PARAMETER, $config['drupal']['core'] ?? NULL);
      $container->setParameter(self::FIELD_HANDLERS_PARAMETER, $config['drupal']['field_handlers'] ?? []);
      $container->setParameter(self::DRUPAL_CREATION_ALIASES_PARAMETER, $config['drupal']['creation_aliases'] ?? []);
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
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When neither a Drush alias nor a Drupal root is configured, or when
   *   either is configured empty.
   */
  protected function loadDrush(FileLoader $loader, ContainerBuilder $container, array $config): void {
    if (isset($config['drush'])) {
      $loader->load('backends/drush.yml');

      foreach (['alias', 'root'] as $key) {
        if (($config['drush'][$key] ?? NULL) === '') {
          throw new InvalidConfigurationException(sprintf('Drush "%s" is empty. Set a value, or leave it out.', $key));
        }
      }

      if (!isset($config['drush']['alias']) && !isset($config['drush']['root'])) {
        throw new InvalidConfigurationException('Drush "alias" or "root" path is required for the Drush backend.');
      }

      $container->setParameter('behat_steps.backend.drush.alias', $config['drush']['alias'] ?? NULL);

      $config['drush']['binary'] ??= 'vendor/bin/drush';
      $config['drush']['binary'] = self::resolveBinaryPath($config['drush']['binary']);
      $container->setParameter('behat_steps.backend.drush.binary', $config['drush']['binary']);

      $container->setParameter('behat_steps.backend.drush.root', $config['drush']['root'] ?? NULL);
      $container->setParameter(self::DRUSH_CREATION_ALIASES_PARAMETER, $config['drush']['creation_aliases'] ?? []);

      $this->setDrushOptions($container, $config);
    }
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
      $definition = $container->getDefinition(self::DRUSH_SERVICE);
      $definition->addMethodCall('setArguments', [$config['drush']['global_options']]);
    }
  }

  /**
   * Registers the handlers and aliases on the in-process backend.
   *
   * The backend builds its Core in its constructor, so the calls run on that
   * Core.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   */
  protected function processDrupal(ContainerBuilder $container): void {
    if (!$container->hasDefinition(self::DRUPAL_SERVICE)) {
      return;
    }

    $this->registerFieldHandlers($container);
    $this->registerCreationAliases($container, self::DRUPAL_SERVICE, self::DRUPAL_CREATION_ALIASES_PARAMETER, 'drupal.creation_aliases');
  }

  /**
   * Registers the configured field handlers on the in-process backend.
   *
   * The calls run on the backend once it has built its Core, so an entry for
   * a shipped field type replaces the handler the Core constructor
   * registered.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   */
  protected function registerFieldHandlers(ContainerBuilder $container): void {
    if (!$container->hasParameter(self::FIELD_HANDLERS_PARAMETER)) {
      return;
    }

    $definition = $container->getDefinition(self::DRUPAL_SERVICE);

    foreach ((array) $container->getParameter(self::FIELD_HANDLERS_PARAMETER) as $field_type => $class) {
      $definition->addMethodCall('registerFieldHandler', [(string) $field_type, $class]);
    }
  }

  /**
   * Registers the configured creation aliases on a backend.
   *
   * Each class is built by 'buildCreationAlias()' when the container builds
   * the backend rather than here, because a class on the suite's 'autoload'
   * path cannot be loaded before the run starts.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param string $service
   *   The ID of the backend service.
   * @param string $parameter
   *   The parameter holding the alias classes.
   * @param string $option
   *   The option the classes come from, such as 'drupal.creation_aliases'.
   */
  protected function registerCreationAliases(ContainerBuilder $container, string $service, string $parameter, string $option): void {
    if (!$container->hasDefinition($service) || !$container->hasParameter($parameter)) {
      return;
    }

    $definition = $container->getDefinition($service);

    foreach ((array) $container->getParameter($parameter) as $class) {
      $alias = new Definition(CreationAliasInterface::class, [$option, $class]);
      $alias->setFactory([self::class, 'buildCreationAlias']);

      $definition->addMethodCall('registerCreationAlias', [$alias]);
    }
  }

}
