<?php

namespace IntegratedExperts\BehatSteps;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Trait KeyboardTrait.
 *
 * Behat trait for keyboard interactions.
 */
trait KeyboardTrait {

  /**
   * @Given I press the :keys keys
   * @Given I press the :keys keys on :selector
   */
  public function keyboardPressKeysOnElement($keys, $selector = NULL) {
    foreach (str_split($keys) as $char) {
      $this->keyboardPressKeyOnElement($char, $selector);
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
   *
   * @Given I press the :char key
   * @Given I press the :char key on :selector
   */
  public function keyboardPressKeyOnElement($char, $selector = NULL) {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium driver', $driver);
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
    if (is_string($char)) {
      if (strlen($char) < 1) {
        throw new \Exception('keyPress($char) was invoked but the $char parameter was empty.');
      }
      // Consider provided characters string longer then 1 to be a keyboard key.
      elseif (strlen($char) > 1) {
        if (!array_key_exists(strtolower($char), $keys)) {
          throw new \Exception('Unsupported key name provided');
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
        if (is_null($selector) && $char == 'tab') {
          $hiddenFocusableElement = '<a id="injected-focusable" style="position: absolute;width: 1px;height: 1px;margin: -1px;padding: 0;overflow: hidden;clip: rect(0,0,0,0);border: 0;"></a>';
          $this->keyboardInjectHtml($hiddenFocusableElement, 'body', 'prepend');
          $selector = '#injected-focusable';
        }

        $char = $keys[strtolower($char)];
      }
    }

    $selector = $selector ? $selector : 'html';

    // Element to trigger key press on.
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new \RuntimeException(sprintf('Unable to find an element with "%s" selector.', $selector));
    }

    $this->keyboardTriggerKey($element->getXpath(), $char);
  }

  /**
   * Trigger key on the element.
   *
   * Uses Syn library injected by original Selenium2 class to trigger browser
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
  protected function keyboardTriggerKey($xpath, $key) {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium driver', $driver);
    }

    // Use reflection to re-use Syn library injection and execution of JS on
    // element.
    $reflector = new \ReflectionClass($driver);
    $withSynReflection = $reflector->getMethod('withSyn');
    $withSynReflection->setAccessible(TRUE);
    $executeJsOnXpathReflection = $reflector->getMethod('executeJsOnXpath');
    $executeJsOnXpathReflection->setAccessible(TRUE);
    $withSynResult = $withSynReflection->invoke($driver);

    $executeJsOnXpathReflection->invokeArgs($withSynResult, [
      $xpath,
      "Syn.key({{ELEMENT}}, '$key');",
    ]);
  }

  /**
   * Inject HTML into page.
   *
   * @param string $html
   *   HTML string to inject.
   * @param string $selector
   *   CSS selector to use as a reference for injection position.
   * @param string $position
   *   Injection position: before, after, prepend, append.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   If method is used for invalid driver.
   * @throws RuntimeException
   *   If invalid position specified.
   */
  protected function keyboardInjectHtml($html, $selector, $position) {
    $allowedPositions = [
      'before',
      'after',
      'prepend',
      'append',
    ];

    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium driver', $driver);
    }

    if (!in_array($position, $allowedPositions)) {
      throw new \RuntimeException('Invalid position specified');
    }

    // Ensure jQuery has loaded.
    $this->getSession()->wait(5000, 'typeof window.jQuery === "function"');
    $driver->evaluateScript("jQuery('$selector').$position('$html');");
  }

}
