<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: dispatch a keyboard event onto an element.
 */
interface KeyboardCapabilityInterface {

  /**
   * Triggers a key on the element at the given XPath.
   *
   * @param string $xpath
   *   XPath of the element to trigger the key on.
   * @param string $key
   *   The key to trigger. A special key is given as its control character
   *   or its name, such as "\t" for tab or 'page-up'.
   */
  public function keyboardTriggerKey(string $xpath, string $key): void;

}
