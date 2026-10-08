<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

/**
 * Holds the JavaScript errors collected from the pages a scenario visited.
 *
 * A page's error collector buffers each error in `window.jsErrors`. The traits
 * that collect, assert or report the errors read the buffer and registry
 * through this trait, so they share 1 registry per context.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait JavascriptErrorTrait {

  /**
   * JavaScript errors collected during the scenario, keyed by page URL.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  protected array $javascriptErrorRegistry = [];

  /**
   * Read the errors buffered by the current page's collector.
   *
   * @return array<int, array<string, mixed>>
   *   The buffered errors, or an empty array when the page has no collector.
   */
  protected function javascriptErrorReadBuffer(): array {
    $errors = $this->getSession()->evaluateScript('return typeof window.jsErrors !== "undefined" ? window.jsErrors : [];');

    return is_array($errors) ? $errors : [];
  }

  /**
   * Empty the error buffer of the current page's collector.
   */
  protected function javascriptErrorClearBuffer(): void {
    $this->getSession()->executeScript('window.jsErrors = [];');
  }

  /**
   * Record errors collected from a page.
   *
   * @param string $url
   *   The URL of the page the errors were collected from.
   * @param array<int, array<string, mixed>> $errors
   *   The errors, in the order the page raised them.
   */
  protected function javascriptErrorRecord(string $url, array $errors): void {
    foreach ($errors as $error) {
      $this->javascriptErrorRegistry[$url][] = $error;
    }
  }

  /**
   * Return the recorded errors.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   The errors, keyed by the URL of the page that raised them.
   */
  protected function javascriptErrorGetAll(): array {
    return $this->javascriptErrorRegistry;
  }

  /**
   * Return the message of every recorded error.
   *
   * @return array<int, string>
   *   The messages, in the order the errors were recorded.
   */
  protected function javascriptErrorGetMessages(): array {
    $messages = [];

    foreach ($this->javascriptErrorRegistry as $errors) {
      $messages = [...$messages, ...$this->javascriptErrorExtractMessages($errors)];
    }

    return $messages;
  }

  /**
   * Extract the message of each error that carries one.
   *
   * @param array<int, array<string, mixed>> $errors
   *   The errors, as the collector buffers them.
   *
   * @return array<int, string>
   *   The messages, in the order of the errors.
   */
  protected function javascriptErrorExtractMessages(array $errors): array {
    $messages = [];

    foreach ($errors as $error) {
      if (isset($error['message'])) {
        $messages[] = (string) $error['message'];
      }
    }

    return $messages;
  }

  /**
   * Drop every recorded error.
   */
  protected function javascriptErrorClear(): void {
    $this->javascriptErrorRegistry = [];
  }

}
