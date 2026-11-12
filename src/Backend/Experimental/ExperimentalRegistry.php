<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Experimental;

/**
 * Default implementation of the experimental registry.
 */
final class ExperimentalRegistry implements ExperimentalRegistryInterface {

  /**
   * The recorded notices keyed by ID.
   *
   * @var array<string, array{message: string, count: int}>
   */
  protected array $notices = [];

  /**
   * Sets the mode.
   *
   * @param bool $strict
   *   Whether fallbacks rethrow the failure they would absorb.
   */
  public function __construct(protected readonly bool $strict = FALSE) {}

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   *   When the ID or the message is empty.
   */
  public function record(string $id, string $message): void {
    if (trim($id) === '') {
      throw new \InvalidArgumentException('An experimental notice needs a non-empty ID.');
    }

    if (trim($message) === '') {
      throw new \InvalidArgumentException(sprintf('The experimental notice "%s" needs a non-empty message.', $id));
    }

    if (isset($this->notices[$id])) {
      $this->notices[$id]['count']++;

      return;
    }

    $this->notices[$id] = ['message' => $message, 'count' => 1];
  }

  /**
   * {@inheritdoc}
   */
  public function fallback(string $id, string $message, \Throwable $exception): void {
    $this->record($id, $message);

    if ($this->strict) {
      throw $exception;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isStrict(): bool {
    return $this->strict;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotices(): array {
    return $this->notices;
  }

}
