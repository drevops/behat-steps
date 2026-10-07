<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;

/**
 * Finds a heading by its text within the page or an element of it.
 */
trait HeadingTrait {

  /**
   * Find a heading whose text matches exactly.
   *
   * Any `h1` to `h6` element counts, and whitespace around its text is
   * ignored.
   *
   * @param \Behat\Mink\Element\TraversableElement $container
   *   The element to search within, such as the page or a region.
   * @param string $heading
   *   The heading text.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The first matching heading, or NULL when the container holds none.
   */
  public function headingFind(TraversableElement $container, string $heading): ?NodeElement {
    foreach ($container->findAll('css', 'h1, h2, h3, h4, h5, h6') as $element) {
      if (trim($element->getText()) === $heading) {
        return $element;
      }
    }

    return NULL;
  }

}
