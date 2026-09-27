<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Processes items from the "behat_test" queue.
 *
 * The queue steps resolve the queue name as a queue worker plugin ID, so a
 * plugin with a matching ID must exist for a queue to be processed at all.
 */
#[QueueWorker(
  id: 'behat_test',
  title: new TranslatableMarkup('Behat test queue'),
)]
class BehatTestQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    // Claiming and deleting the item is the whole observable effect, so the
    // payload needs no work.
  }

}
