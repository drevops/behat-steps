<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

/**
 * Assert status, error, warning and success messages rendered on the page.
 *
 * - Match a single message by substring, per message type.
 * - Match a table of messages in one step.
 *
 * Each message type resolves to a CSS selector configured under the
 * `selectors: messages:` map in the extension configuration, keyed `default`,
 * `error`, `success` and `warning`. A message matches when the text of any
 * element found by that selector contains the expected string.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait MessageTrait {

  /**
   * Assert that a status message is present.
   *
   * @code
   * Then the message "Changes saved" should exist
   * @endcode
   */
  #[Then('the message :message should exist')]
  public function messageAssertExists(string $message): void {
    $this->messageAssert($message, 'default');
  }

  /**
   * Assert that a status message is absent.
   *
   * @code
   * Then the message "Access denied" should not exist
   * @endcode
   */
  #[Then('the message :message should not exist')]
  public function messageAssertNotExists(string $message): void {
    $this->messageAssertNot($message, 'default');
  }

  /**
   * Assert that an error message is present.
   *
   * @code
   * Then the error message "Username field is required" should exist
   * @endcode
   */
  #[Then('the error message :message should exist')]
  public function messageAssertErrorExists(string $message): void {
    $this->messageAssert($message, 'error');
  }

  /**
   * Assert that an error message is absent.
   *
   * @code
   * Then the error message "Access denied" should not exist
   * @endcode
   */
  #[Then('the error message :message should not exist')]
  public function messageAssertErrorNotExists(string $message): void {
    $this->messageAssertNot($message, 'error');
  }

  /**
   * Assert that a success message is present.
   *
   * @code
   * Then the success message "Article has been created" should exist
   * @endcode
   */
  #[Then('the success message :message should exist')]
  public function messageAssertSuccessExists(string $message): void {
    $this->messageAssert($message, 'success');
  }

  /**
   * Assert that a success message is absent.
   *
   * @code
   * Then the success message "Changes saved" should not exist
   * @endcode
   */
  #[Then('the success message :message should not exist')]
  public function messageAssertSuccessNotExists(string $message): void {
    $this->messageAssertNot($message, 'success');
  }

  /**
   * Assert that a warning message is present.
   *
   * @code
   * Then the warning message "This action cannot be undone" should exist
   * @endcode
   */
  #[Then('the warning message :message should exist')]
  public function messageAssertWarningExists(string $message): void {
    $this->messageAssert($message, 'warning');
  }

  /**
   * Assert that a warning message is absent.
   *
   * @code
   * Then the warning message "deprecated" should not exist
   * @endcode
   */
  #[Then('the warning message :message should not exist')]
  public function messageAssertWarningNotExists(string $message): void {
    $this->messageAssertNot($message, 'warning');
  }

  /**
   * Assert that every error message in the table is present.
   *
   * @code
   * Then the following error messages should exist:
   *   | Username field is required |
   *   | Password field is required |
   * @endcode
   */
  #[Then('the following error messages should exist:')]
  public function messageAssertErrorsExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssert(trim($message), 'error');
    }
  }

  /**
   * Assert that no error message in the table is present.
   *
   * @code
   * Then the following error messages should not exist:
   *   | Access denied |
   * @endcode
   */
  #[Then('the following error messages should not exist:')]
  public function messageAssertErrorsNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNot(trim($message), 'error');
    }
  }

  /**
   * Assert that every success message in the table is present.
   *
   * @code
   * Then the following success messages should exist:
   *   | Article has been created |
   * @endcode
   */
  #[Then('the following success messages should exist:')]
  public function messageAssertSuccessesExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssert(trim($message), 'success');
    }
  }

  /**
   * Assert that no success message in the table is present.
   *
   * @code
   * Then the following success messages should not exist:
   *   | Changes saved |
   * @endcode
   */
  #[Then('the following success messages should not exist:')]
  public function messageAssertSuccessesNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNot(trim($message), 'success');
    }
  }

  /**
   * Assert that every warning message in the table is present.
   *
   * @code
   * Then the following warning messages should exist:
   *   | This action cannot be undone |
   * @endcode
   */
  #[Then('the following warning messages should exist:')]
  public function messageAssertWarningsExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssert(trim($message), 'warning');
    }
  }

  /**
   * Assert that no warning message in the table is present.
   *
   * @code
   * Then the following warning messages should not exist:
   *   | deprecated |
   * @endcode
   */
  #[Then('the following warning messages should not exist:')]
  public function messageAssertWarningsNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNot(trim($message), 'warning');
    }
  }

  /**
   * Assert that a message of the given type contains the expected text.
   *
   * @param string $message
   *   The expected text.
   * @param string $type
   *   The message type: 'default', 'error', 'success' or 'warning'.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the page renders no message of that type, or none containing the
   *   expected text.
   */
  protected function messageAssert(string $message, string $type): void {
    $elements = $this->getSession()->getPage()->findAll('css', $this->messageSelector($type));

    if ($elements === []) {
      throw new ExpectationException(sprintf('The page "%s" does not contain any "%s" messages.', $this->getSession()->getCurrentUrl(), $type), $this->getSession()->getDriver());
    }

    foreach ($elements as $element) {
      if (str_contains(trim($element->getText()), $message)) {
        return;
      }
    }

    throw new ExpectationException(sprintf('The page "%s" does not contain the "%s" message "%s".', $this->getSession()->getCurrentUrl(), $type, $message), $this->getSession()->getDriver());
  }

  /**
   * Assert that no message of the given type contains the expected text.
   *
   * @param string $message
   *   The text that must not appear.
   * @param string $type
   *   The message type: 'default', 'error', 'success' or 'warning'.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a message of that type contains the text.
   */
  protected function messageAssertNot(string $message, string $type): void {
    $elements = $this->getSession()->getPage()->findAll('css', $this->messageSelector($type));

    foreach ($elements as $element) {
      if (str_contains(trim($element->getText()), $message)) {
        throw new ExpectationException(sprintf('The page "%s" contains the "%s" message "%s".', $this->getSession()->getCurrentUrl(), $type, $message), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Resolve the configured CSS selector for a message type.
   *
   * @param string $type
   *   The message type: 'default', 'error', 'success' or 'warning'.
   *
   * @return string
   *   The CSS selector.
   *
   * @throws \RuntimeException
   *   When the message type has no configured selector.
   */
  protected function messageSelector(string $type): string {
    $selectors = $this->getParameter('selectors');

    if (!is_array($selectors) || !isset($selectors['messages'][$type]) || !is_string($selectors['messages'][$type])) {
      throw new \RuntimeException(sprintf('No CSS selector is configured for the "%s" message type. Set it under "behat_steps: selectors: messages: %s:".', $type, $type));
    }

    return $selectors['messages'][$type];
  }

}
