<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Mink\Capability\KeyboardCapabilityInterface;

/**
 * Simulate keyboard interactions in the browser.
 *
 * - Trigger key press events, including named special keys.
 * - Press a string of characters 1 key at a time.
 * - Target a key press at a page element or at the focused element.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait KeyboardTrait {

  /**
   * Press a single keyboard key.
   *
   * @code
   * When I press the key "a"
   * When I press the key "tab"
   * @endcode
   */
  #[When('I press the key :key')]
  public function keyboardPressKey(string $key): void {
    $this->keyboardPressKeyOnElementSingle($key);
  }

  /**
   * Press a single keyboard key on the element.
   *
   * @code
   * When I press the key "a" on the element "#edit-title"
   * When I press the key "tab" on the element "#edit-title"
   * @endcode
   */
  #[When('I press the key :key on the element :selector')]
  public function keyboardPressKeyOnElement(string $key, string $selector): void {
    $this->keyboardPressKeyOnElementSingle($key, $selector);
  }

  /**
   * Press multiple keyboard keys.
   *
   * @code
   * When I press the keys "abc"
   * @endcode
   */
  #[When('I press the keys :keys')]
  public function keyboardPressKeys(string $keys): void {
    $this->keyboardPressKeySequence($keys);
  }

  /**
   * Press multiple keyboard keys on the element.
   *
   * @code
   * When I press the keys "abc" on the element "#edit-title"
   * @endcode
   */
  #[When('I press the keys :keys on the element :selector')]
  public function keyboardPressKeysOnElement(string $keys, string $selector): void {
    $this->keyboardPressKeySequence($keys, $selector);
  }

  /**
   * Press each character of a string as a separate key, optionally on element.
   *
   * @param string $keys
   *   The characters to press, in order.
   * @param string|null $selector
   *   CSS selector for an element to trigger the keys on, or NULL for the
   *   currently focused element.
   */
  protected function keyboardPressKeySequence(string $keys, ?string $selector = NULL): void {
    $chars = preg_split('//u', $keys, -1, PREG_SPLIT_NO_EMPTY);

    // @codeCoverageIgnoreStart
    if ($chars === FALSE) {
      throw new \RuntimeException('Unable to split provided string into characters.');
    }

    // @codeCoverageIgnoreEnd
    foreach ($chars as $char) {
      $this->keyboardPressKeyOnElementSingle($char, $selector);
    }
  }

  /**
   * Press keyboard key, optionally on element.
   *
   * @param string $char
   *   Character or one of the pre-defined special keyboard keys.
   * @param string|null $selector
   *   CSS selector for an element to trigger the key on. Pass NULL to trigger
   *   the key on the currently focused element. An exception is raised when no
   *   element is focused.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   If method is used for invalid browser driver.
   */
  protected function keyboardPressKeyOnElementSingle(string $char, ?string $selector = NULL): void {
    // Resolve the capability before the key map is built, so a browser
    // driver that cannot dispatch a key event fails naming the capability.
    $keyboard = $this->browserDriverFor(KeyboardCapabilityInterface::class);

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

    if (strlen($char) < 1) {
      throw new \RuntimeException('The keyboard key must not be empty.');
    }

    if (strlen($char) > 1) {
      if (!array_key_exists(strtolower($char), $keys)) {
        throw new \RuntimeException(sprintf('Unsupported key "%s" provided.', $char));
      }

      // Syn, the synthetic-events JS library, can tab only from a focusable
      // element, so a tab press with no selector targets an injected anchor.
      if ($selector === NULL && $char === 'tab') {
        // The anchor is visually hidden, screen-reader compatible and the
        // first element inside <body>. Triggering the key on it moves focus
        // to the first element in the tab order without focusing the anchor
        // itself.
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

    if ($selector === NULL) {
      $script = <<<'JS'
        (function() {
          var el = document.activeElement;
          if (!el || el === document.body || el === document.documentElement) {
            return null;
          }

          function getPathTo(element) {
            if (element.id !== '')
              return 'id("' + element.id + '")';
            if (element === document.body)
              return '/html/body';

            var ix = 0;
            var siblings = element.parentNode.childNodes;
            for (var i = 0; i < siblings.length; i++) {
              var sibling = siblings[i];
              if (sibling === element)
                return getPathTo(element.parentNode) + '/' + element.tagName.toLowerCase() + '[' + (ix + 1) + ']';
              if (sibling.nodeType === 1 && sibling.tagName === element.tagName)
                ix++;
            }
          }

          return getPathTo(el);
        })()
JS;
      $xpath = $this->getSession()->evaluateScript($script);

      if (!$xpath) {
        throw new \RuntimeException('No element is currently focused. Please focus an element first using a step with a selector.');
      }

      $keyboard->keyboardTriggerKey($xpath, $char);
    }
    else {
      $this->assertSession()->elementExists('css', $selector);
      $element = $this->getSession()->getPage()->find('css', $selector);

      // @codeCoverageIgnoreStart
      if (!$element) {
        throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
      }

      // @codeCoverageIgnoreEnd
      $keyboard->keyboardTriggerKey($element->getXpath(), $char);
    }
  }

}
