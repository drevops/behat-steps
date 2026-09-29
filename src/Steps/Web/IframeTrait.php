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
   * When I switch to the iframe with the selector "iframe.payment-form"
   * When I switch to the iframe with the selector "#recaptcha iframe"
   * @endcode
   *
   * @javascript
   */
  #[When('I switch to the iframe with the selector :selector')]
  public function iframeSwitchTo(string $selector): void {
    // Switching frames and naming an unnamed one both need a real browser, so
    // a driver that runs no JavaScript fails naming the capability.
    $this->browserDriverFor(JavascriptCapabilityInterface::class);

    $iframe = $this->getSession()->getPage()->find('css', $selector);

    if ($iframe === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'iframe', 'css', $selector);
    }

    $iframe_name = $iframe->getAttribute('name');

    if (empty($iframe_name)) {
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

      $iframe_name = $iframe->getAttribute('name');
    }

    $this->getSession()->getDriver()->switchToIFrame($iframe_name);
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

}
