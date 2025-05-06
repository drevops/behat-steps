<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Simulate keyboard interactions in Drupal browser testing.
 *
 * - Trigger key press events including special keys and key combinations.
 * - Assert keyboard navigation and shortcut functionality.
 * - Support for targeted key presses on specific page elements.
 */
trait KeyboardTrait {

  /**
   * Press a single keyboard key.
   *
   * @code
   * When I press the key "a"
   * When I press the key "tab"
   * @endcode
   *
   * @When I press the key :key
   */
  public function keyboardPressKey(string $key): void {
    $this->keyboardPressKeyOnElementSingle($key, NULL);
  }

  /**
   * Press a single keyboard key on the element.
   *
   * @code
   * When I press the key "a" on the element "#edit-title"
   * When I press the key "tab" on the element "#edit-title"
   * @endcode
   *
   * @When I press the key :key on the element :selector
   */
  public function keyboardPressKeyOnElement(string $key, ?string $selector): void {
    $this->keyboardPressKeyOnElementSingle($key, $selector);
  }

  /**
   * Press multiple keyboard keys.
   *
   * @code
   * When I press the keys "abc"
   * @endcode
   *
   * @When I press the keys :keys
   */
  public function keyboardPressKeys(string $keys): void {
    $this->keyboardPressKeysOnElement($keys, NULL);
  }

  /**
   * Press multiple keyboard keys on the element.
   *
   * @code
   * When I press the keys "abc" on the element "#edit-title"
   * @endcode
   *
   * @When I press the keys :keys on the element :selector
   */
  public function keyboardPressKeysOnElement(string $keys, ?string $selector): void {
    $chars = preg_split('//u', $keys, -1, PREG_SPLIT_NO_EMPTY);

    if ($chars === FALSE) {
      throw new \RuntimeException('Unable to split provided string into characters.');
    }

    foreach ($chars as $char) {
      $this->keyboardPressKeyOnElementSingle($char, $selector);
    }
  }

  /**
   * Press keyboard key, optionally on element.
   *
   * @param string $char
   *   Character or one of the pre-defined special keyboard keys.
   * @param string $selector
   *   Optional CSS selector for an element to trigger the key on. If omitted,
   *   the key will be triggered on the 'html' element of the page.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   If method is used for invalid driver.
   */
  public function keyboardPressKeyOnElementSingle(string $char, ?string $selector): void {
    $driver = $this->getSession()->getDriver();

    if (!$driver instanceof Selenium2Driver) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium2 driver', $driver);
    }

    $keys = [
      'backspace' => "\b",
      'tab' => "\t",
      'enter' => "\r",
      'shift' => 'shift',
      'ctrl' => 'ctrl',
      'alt' => 'alt',
      'pause' => 'pause',
      'break' => 'break',
      'escape' => 'escape',
      'esc' => 'escape',
      'end' => 'end',
      'home' => 'home',
      'left' => 'left',
      'up' => 'up',
      'right' => 'right',
      'down' => 'down',
      'insert' => 'insert',
      'delete' => 'delete',
      'pageup' => 'page-up',
      'page-up' => 'page-up',
      'pagedown' => 'page-down',
      'page-down' => 'page-down',
      'capslock' => 'caps',
      'caps' => 'caps',
    ];

    // Convert provided character sequence to special keys.
    if (strlen($char) < 1) {
      throw new \Exception('keyPress($char) was invoked but the $char parameter was empty.');
    }
    // Consider provided characters string longer then 1 to be a keyboard key.
    elseif (strlen($char) > 1) {
      if (!array_key_exists(strtolower($char), $keys)) {
        throw new \RuntimeException(sprintf('Unsupported key "%s" provided', $char));
      }

      // Special case for tab key triggered in window without target element
      // focused: Syn (JS library that provides synthetic events) can tab only
      // from another element that can receive focus, so we inject such
      // element as a very first element after opening <body> tag. This
      // element is visually hidden, but compatible with screen readers. Then
      // we trigger key on this element to make sure that an element that
      // supposed to get the very first focus from tab index actually gets it.
      // Note that injecting element and triggering key press on it does not
      // make it focused itself.
      if (is_null($selector) && $char === 'tab') {
        $selector = '#injected-focusable';

        $script = <<<JS
          (function() {
            if (document.querySelectorAll('body #injected-focusable').length === 0) {
              document.querySelector('body').insertAdjacentHTML('afterbegin', '<a id="injected-focusable" style="position: absolute;width: 1px;height: 1px;margin: -1px;padding: 0;overflow: hidden;clip: rect(0,0,0,0);border: 0;"></a>');
            }
          })();
        JS;
        $this->getSession()->getDriver()->evaluateScript($script);
      }

      $char = $keys[strtolower($char)];
    }

    $selector = $selector ?: 'html';
    $this->assertSession()->elementExists('css', $selector);
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new \RuntimeException(sprintf('Unable to find an element with "%s" selector.', $selector));
    }

    $this->keyboardTriggerKey($element->getXpath(), $char);
  }

  /**
   * Trigger key on the element.
   *
   * Use Syn library injected by original Selenium2 class to trigger browser
   * events.
   *
   * @param string $xpath
   *   XPath string for an element to trigger the key on.
   * @param string $key
   *   Key to trigger. Special key values must be provided as strings (i.e.
   *   'tab' key as "\t", 'enter' key as "\r" etc.).
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   If method is used for invalid driver.
   */
  protected function keyboardTriggerKey(string $xpath, string $key): void {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium2 driver', $driver);
    }

    // Use reflection to re-use Syn library injection and execution of JS on
    // element.
    $reflector = new \ReflectionClass($driver);
    $with_syn_reflection = $reflector->getMethod('withSyn');
    $with_syn_reflection->setAccessible(TRUE);
    $execute_js_on_xpath_reflection = $reflector->getMethod('executeJsOnXpath');
    $execute_js_on_xpath_reflection->setAccessible(TRUE);
    $withSynResult = $with_syn_reflection->invoke($driver);

    $execute_js_on_xpath_reflection->invokeArgs($withSynResult, [
      $xpath,
      sprintf("syn.key({{ELEMENT}}, '%s');", $key),
    ]);
  }

}
