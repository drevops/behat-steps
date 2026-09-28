<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes items from the "behat_test" queue.
 *
 * The queue steps resolve the queue name as a queue worker plugin ID, so a
 * plugin with a matching ID must exist for a queue to be processed at all.
 *
 * Each processed item decrements the "queue_budget" key of the
 * "mysite_core.settings" configuration, which gives a scenario a value that
 * only this worker can change.
 */
#[QueueWorker(
  id: 'behat_test',
  title: new TranslatableMarkup('Behat test queue'),
)]
final class BehatTestQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a BehatTestQueueWorker.
   *
   * @param array<mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $config = $this->configFactory->getEditable('mysite_core.settings');
    $budget = $config->get('queue_budget');

    // Writing an unseeded budget would create a configuration object that
    // ConfigTrait never snapshotted, leaving it behind after the scenario.
    if ($budget === NULL) {
      return;
    }

    $config->set('queue_budget', (int) $budget - 1)->save();
  }

}
