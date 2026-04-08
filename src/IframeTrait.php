<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Step\When;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Switch between iframes and the root document.
 *
 * - Switch to iframes by CSS selector, including unnamed iframes.
 * - Switch back to the root (top-level) document.
 */
trait IframeTrait {

  /**
   * Switch to an iframe identified by CSS selector.
   *
   * Handles unnamed iframes by auto-assigning a name via JavaScript.
   *
   * @code
   * When I switch to iframe with locator "iframe.payment-form"
   * When I switch to iframe with locator "#recaptcha iframe"
   * @endcode
   *
   * @javascript
   */
  #[When('I switch to iframe with locator :locator')]
  public function iframeSwitchTo(string $locator): void {
    $iframe = $this->getSession()->getPage()->find('css', $locator);

    if ($iframe === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'iframe', 'css', $locator);
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

      $iframe = $this->getSession()->getPage()->find('css', $locator);

      if ($iframe === NULL) {
        throw new ElementNotFoundException($this->getSession()->getDriver(), 'iframe', 'css', $locator);
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
