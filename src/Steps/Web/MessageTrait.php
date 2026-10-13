<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use DrevOps\BehatSteps\Extension\Config\Option;

/**
 * Assert status, error, warning and success messages rendered on the page.
 *
 * - Match a single message by substring, per message type.
 * - Match a table of messages in 1 step.
 *
 * Each message type resolves to a CSS selector from the `message.selectors`
 * option, keyed `default`, `error`, `success` and `warning`. A message
 * matches when the text of any element found by that selector contains the
 * expected string.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait MessageTrait {

  /**
   * Assert that a status message is present.
   *
   * @code
   * Then I should see the message "Changes saved"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the message :message')]
  public function messageAssertExists(string $message): void {
    $this->messageAssertExistsOfType($message, 'default');
  }

  /**
   * Assert that a status message is absent.
   *
   * @code
   * Then I should not see the message "Access denied"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the message :message')]
  public function messageAssertNotExists(string $message): void {
    $this->messageAssertNotExistsOfType($message, 'default');
  }

  /**
   * Assert that an error message is present.
   *
   * @code
   * Then I should see the error message "Username field is required"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the error message :message')]
  public function messageAssertErrorExists(string $message): void {
    $this->messageAssertExistsOfType($message, 'error');
  }

  /**
   * Assert that an error message is absent.
   *
   * @code
   * Then I should not see the error message "Access denied"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the error message :message')]
  public function messageAssertErrorNotExists(string $message): void {
    $this->messageAssertNotExistsOfType($message, 'error');
  }

  /**
   * Assert that a success message is present.
   *
   * @code
   * Then I should see the success message "Article has been created"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the success message :message')]
  public function messageAssertSuccessExists(string $message): void {
    $this->messageAssertExistsOfType($message, 'success');
  }

  /**
   * Assert that a success message is absent.
   *
   * @code
   * Then I should not see the success message "Changes saved"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the success message :message')]
  public function messageAssertSuccessNotExists(string $message): void {
    $this->messageAssertNotExistsOfType($message, 'success');
  }

  /**
   * Assert that a warning message is present.
   *
   * @code
   * Then I should see the warning message "This action cannot be undone"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the warning message :message')]
  public function messageAssertWarningExists(string $message): void {
    $this->messageAssertExistsOfType($message, 'warning');
  }

  /**
   * Assert that a warning message is absent.
   *
   * @code
   * Then I should not see the warning message "deprecated"
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the warning message :message')]
  public function messageAssertWarningNotExists(string $message): void {
    $this->messageAssertNotExistsOfType($message, 'warning');
  }

  /**
   * Assert that every error message in the table is present.
   *
   * @code
   * Then I should see the following error messages:
   *   | Username field is required |
   *   | Password field is required |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the following error messages:')]
  public function messageAssertErrorsExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertExistsOfType(trim($message), 'error');
    }
  }

  /**
   * Assert that no error message in the table is present.
   *
   * @code
   * Then I should not see the following error messages:
   *   | Access denied |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the following error messages:')]
  public function messageAssertErrorsNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNotExistsOfType(trim($message), 'error');
    }
  }

  /**
   * Assert that every success message in the table is present.
   *
   * @code
   * Then I should see the following success messages:
   *   | Article has been created |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the following success messages:')]
  public function messageAssertSuccessesExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertExistsOfType(trim($message), 'success');
    }
  }

  /**
   * Assert that no success message in the table is present.
   *
   * @code
   * Then I should not see the following success messages:
   *   | Changes saved |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the following success messages:')]
  public function messageAssertSuccessesNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNotExistsOfType(trim($message), 'success');
    }
  }

  /**
   * Assert that every warning message in the table is present.
   *
   * @code
   * Then I should see the following warning messages:
   *   | This action cannot be undone |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the following warning messages:')]
  public function messageAssertWarningsExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertExistsOfType(trim($message), 'warning');
    }
  }

  /**
   * Assert that no warning message in the table is present.
   *
   * @code
   * Then I should not see the following warning messages:
   *   | deprecated |
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the following warning messages:')]
  public function messageAssertWarningsNotExist(TableNode $messages): void {
    foreach ($messages->getColumn(0) as $message) {
      $this->messageAssertNotExistsOfType(trim($message), 'warning');
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
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the page renders no message of that type.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no message of that type contains the expected text.
   */
  public function messageAssertExistsOfType(string $message, string $type): void {
    $elements = $this->getSession()->getPage()->findAll('css', $this->messageGetSelector($type));

    if ($elements === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('%s message', $type), 'css', $this->messageGetSelector($type));
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
  public function messageAssertNotExistsOfType(string $message, string $type): void {
    $elements = $this->getSession()->getPage()->findAll('css', $this->messageGetSelector($type));

    foreach ($elements as $element) {
      if (str_contains(trim($element->getText()), $message)) {
        throw new ExpectationException(sprintf('The page "%s" contains the "%s" message "%s".', $this->getSession()->getCurrentUrl(), $type, $message), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Return the configured CSS selector for a message type.
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
  public function messageGetSelector(string $type): string {
    $selectors = $this->getOptionArray('message', 'selectors');

    if (!isset($selectors[$type]) || !is_string($selectors[$type])) {
      throw new \RuntimeException(sprintf('No CSS selector is configured for the "%s" message type. Set it under "behat_steps: steps: message: selectors: %s:".', $type, $type));
    }

    return $selectors[$type];
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function messageConfigSchema(): array {
    return [
      new Option('selectors', default: [], description: 'Selectors of the message regions the message steps assert against, one per severity: `default`, `error`, `success` and `warning`.'),
    ];
  }

}
