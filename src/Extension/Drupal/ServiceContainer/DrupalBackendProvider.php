<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\ServiceContainer;

use DrevOps\BehatSteps\Extension\ServiceContainer\BackendProviderInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

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
   * Tag a Core service carries to be injected into the in-process backend.
   */
  public const string CORE_TAG = 'behat_steps.core';

  /**
   * Parameter holding the configured map of field types to handler classes.
   */
  public const string FIELD_HANDLERS_PARAMETER = 'behat_steps.backend.drupal.field_handlers';

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
          ->arrayNode('field_handlers')
            ->info('Map of field types to the handler classes that turn a value a step gives a field into the value Drupal stores. An entry adds a handler for a field type the package ships none for, or replaces the shipped one. A class implements FieldHandlerInterface.' . PHP_EOL
              . '  rating: Acme\Behat\Field\RatingHandler' . PHP_EOL
              . '  datetime: Acme\Behat\Field\FiscalDatetimeHandler' . PHP_EOL)
            ->useAttributeAsKey('field_type')
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
   *
   * The in-process backend takes a single Core, so only the first service
   * tagged 'behat_steps.core' is injected.
   */
  public function process(ContainerBuilder $container): void {
    if (!$container->hasDefinition(self::DRUPAL_SERVICE)) {
      return;
    }

    $core_ids = array_keys($container->findTaggedServiceIds(self::CORE_TAG));

    if ($core_ids === []) {
      return;
    }

    $container->getDefinition(self::DRUPAL_SERVICE)->addMethodCall('setCore', [new Reference($core_ids[0])]);

    $this->registerFieldHandlers($container);
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
   * Loads the in-process backend.
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
      $container->setParameter(self::FIELD_HANDLERS_PARAMETER, $config['drupal']['field_handlers'] ?? []);
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
   * Registers the configured field handlers on the in-process backend.
   *
   * The calls follow 'setCore()', so an entry for a shipped field type
   * replaces the handler the Core constructor registered.
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

}
