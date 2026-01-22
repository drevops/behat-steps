<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Time;

use Drupal\Component\Datetime\TimeInterface as CoreTimeInterface;
use Drupal\Core\State\StateInterface;

/**
 * Time service with state-based override support.
 *
 * Wraps the core datetime.time service, allowing the current time
 * to be overridden via Drupal state for testing purposes.
 */
class Time implements TimeInterface {

  /**
   * State key for time override.
   */
  public const STATE_KEY = 'testing.time';

  /**
   * Constructs a new Time object.
   */
  public function __construct(
    protected CoreTimeInterface $coreTime,
    protected StateInterface $state,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    $override = $this->state->get(self::STATE_KEY);
    if (is_numeric($override)) {
      return (int) $override;
    }
    return (int) $this->coreTime->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    $override = $this->state->get(self::STATE_KEY);
    if (is_numeric($override)) {
      return (float) $override;
    }
    return (float) $this->coreTime->getRequestMicroTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime(): int {
    $override = $this->state->get(self::STATE_KEY);
    if (is_numeric($override)) {
      return (int) $override;
    }
    return (int) $this->coreTime->getCurrentTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    $override = $this->state->get(self::STATE_KEY);
    if (is_numeric($override)) {
      return (float) $override;
    }
    return (float) $this->coreTime->getCurrentMicroTime();
  }

}
