<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Listener;

use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the classes the Drupal backend options name.
 *
 * The container builds the backends before Behat registers its 'autoload'
 * paths, so the Core, handler and alias classes are resolved when the
 * exercise starts.
 */
final class ConfiguredClassListener implements EventSubscriberInterface {

  /**
   * Constructs a ConfiguredClassListener.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface|null $drupalBackend
   *   The in-process backend, or NULL when the 'drupal' section is absent.
   * @param array<string, string> $fieldHandlers
   *   The handler classes the in-process backend registers, keyed by field
   *   type.
   * @param array<string, array{backend: \DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface, classes: array<int, string>}> $creationAliases
   *   The alias classes and the backend registering them, keyed by the option
   *   naming them, such as 'drupal.creation_aliases'.
   */
  public function __construct(
    protected ?DrupalBackendInterface $drupalBackend = NULL,
    protected array $fieldHandlers = [],
    protected array $creationAliases = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExerciseCompleted::BEFORE => 'registerConfiguredClasses',
    ];
  }

  /**
   * Builds the Core and registers the configured handlers and aliases.
   *
   * The Core constructor registers the shipped handlers and aliases, so an
   * entry replaces the shipped one of the same field type or name.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the Core class cannot be built for the detected major.
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a handler or an alias class cannot be registered.
   */
  public function registerConfiguredClasses(): void {
    if ($this->drupalBackend instanceof DrupalBackendInterface) {
      $this->drupalBackend->getCore();
      $this->registerFieldHandlers($this->drupalBackend);
    }

    foreach ($this->creationAliases as $option => ['backend' => $backend, 'classes' => $classes]) {
      foreach ($classes as $class) {
        $backend->registerCreationAlias($this->buildCreationAlias($option, $class));
      }
    }
  }

  /**
   * Registers the configured field handlers on the in-process backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface $drupal
   *   The in-process backend.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the backend rejects a handler class.
   */
  protected function registerFieldHandlers(DrupalBackendInterface $drupal): void {
    foreach ($this->fieldHandlers as $field_type => $class) {
      try {
        $drupal->registerFieldHandler((string) $field_type, $class);
      }
      catch (\RuntimeException $exception) {
        throw new InvalidConfigurationException(sprintf('The "drupal.field_handlers" option maps the field type "%s" to a class the backend rejects. %s', $field_type, $exception->getMessage()), 0, $exception);
      }
    }
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
  protected function buildCreationAlias(string $option, string $class): CreationAliasInterface {
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

}
