<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;

/**
 * Switch between iframes and the root document.
 *
 * - Switch to iframes by CSS selector, including unnamed iframes.
 * - Switch back to the root (top-level) document.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait IframeTrait {

  /**
   * Switch to an iframe identified by CSS selector.
   *
   * Handles unnamed iframes by auto-assigning a name via JavaScript.
   *
   * @code
   * When I switch to the iframe "iframe.payment-form"
   * When I switch to the iframe "#recaptcha iframe"
   * @endcode
   *
   * @javascript
   */
  #[When('I switch to the iframe :selector')]
  public function iframeSwitchTo(string $selector): void {
    $this->browserDriverFor(JavascriptCapabilityInterface::class);

    $this->getSession()->getDriver()->switchToIFrame($this->iframeGetName($selector));
  }

  /**
   * Switch back to the root (top-level) document from an iframe.
   *
   * @code
   * When I switch to the root document
   * @endcode
   */
  #[When('I switch to the root document')]
  public function iframeSwitchToRootDocument(): void {
    $this->getSession()->getDriver()->switchToIFrame();
  }

  /**
   * Get the name of an iframe, assigning one when it has none.
   *
   * A browser driver switches to an iframe by name, so every unnamed iframe
   * on the page is named "behat_iframe_N" after its 1-based position.
   *
   * @param string $selector
   *   The CSS selector of the iframe.
   *
   * @return string
   *   The iframe name.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no iframe matches the selector.
   */
  public function iframeGetName(string $selector): string {
    $iframe = $this->getSession()->getPage()->find('css', $selector);

    if ($iframe === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'iframe', 'css', $selector);
    }

    $iframe_name = $iframe->getAttribute('name');

    if ($iframe_name !== NULL && $iframe_name !== '') {
      return $iframe_name;
    }

    $this->getSession()->executeScript(
      "(function(){
        var iframes = document.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
          if (!iframes[i].name) {
            iframes[i].name = 'behat_iframe_' + (i + 1);
          }
        }
      })()"
    );

    $iframe = $this->getSession()->getPage()->find('css', $selector);

    if ($iframe === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'iframe', 'css', $selector);
    }

    return (string) $iframe->getAttribute('name');
  }

}
