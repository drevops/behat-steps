<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web\Fixtures;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Session;

/**
 * Recording test double for a Mink session.
 *
 * Records each visit and reports the last visited URL as the current one.
 */
class RecordingSession extends Session {

  /**
   * URLs visited, in order.
   *
   * @var list<string>
   */
  public array $visited = [];

  /**
   * URLs whose visit fails.
   *
   * @var list<string>
   */
  public array $failingUrls = [];

  /**
   * A URL after whose visit the session can no longer report its URL.
   */
  public string $failCurrentUrlAfter = '';

  /**
   * The URL of the last successful visit.
   */
  protected string $currentUrl = 'about:blank';

  /**
   * {@inheritdoc}
   */
  public function visit(string $url): void {
    if (in_array($url, $this->failingUrls, TRUE)) {
      throw new DriverException(sprintf('Unable to reach "%s".', $url));
    }

    $this->visited[] = $url;
    $this->currentUrl = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUrl(): string {
    if ($this->failCurrentUrlAfter !== '' && $this->currentUrl === $this->failCurrentUrlAfter) {
      throw new DriverException('The session has no URL.');
    }

    return $this->currentUrl;
  }

}
