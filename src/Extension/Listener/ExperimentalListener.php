<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Listener;

use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistryInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lists the experimental code paths a run took, once, after the run.
 *
 * The list changes no result: a run that took a fallback passes or fails as
 * it would without the list.
 */
final readonly class ExperimentalListener implements EventSubscriberInterface {

  /**
   * Sets the registry to list and the output to list it to.
   *
   * @param \DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistryInterface $experimentalRegistry
   *   The registry the run recorded its experimental code paths to.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output of the run.
   */
  public function __construct(
    protected ExperimentalRegistryInterface $experimentalRegistry,
    protected OutputInterface $output,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ExerciseCompleted::AFTER => ['printNotices', 0],
    ];
  }

  /**
   * Prints the recorded notices, or nothing when none was recorded.
   */
  public function printNotices(): void {
    $notices = $this->experimentalRegistry->getNotices();

    if ($notices === []) {
      return;
    }

    $lines = ['', '--- Experimental behavior used in this run:', ''];

    foreach ($notices as $id => $notice) {
      $times = $notice['count'] === 1 ? '1 time' : $notice['count'] . ' times';
      $lines[] = sprintf('    [%s] %s (%s)', $id, $notice['message'], $times);
    }

    $lines[] = '';

    $this->output->writeln($lines);
  }

}
