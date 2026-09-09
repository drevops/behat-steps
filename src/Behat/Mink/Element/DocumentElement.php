<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Element;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Element\TraversableElement;

/**
 * Document element that reads page text the way a browser renders it.
 *
 * Registered as a 'class_alias' over Mink's own 'DocumentElement', so it must
 * extend 'TraversableElement' rather than that class: the alias is installed
 * before the Mink class is autoloaded.
 *
 * Under BrowserKit, Mink reads the text of the '//html' node, which counts the
 * contents of '<head>' and Drupal's settings JSON as page text, and throws
 * outright on a response that is not HTML.
 *
 * @see https://github.com/minkphp/MinkBrowserKitDriver/issues/153
 * @see https://www.drupal.org/project/drupal/issues/3175718
 */
class DocumentElement extends TraversableElement {

  /**
   * Returns XPath for handled element.
   *
   * @return string
   *   The XPath expression.
   */
  public function getXpath() {
    return '//html';
  }

  /**
   * Returns document content.
   *
   * @return string
   *   The trimmed page content.
   */
  public function getContent(): string {
    return trim($this->getDriver()->getContent());
  }

  /**
   * Check whether document has specified content.
   *
   * @param string $content
   *   The content to check for.
   *
   * @return bool
   *   TRUE if the content is found, FALSE otherwise.
   */
  public function hasContent(string $content) {
    return $this->has('named', ['content', $content]);
  }

  /**
   * {@inheritdoc}
   */
  public function getText() {
    if (!$this->getDriver() instanceof BrowserKitDriver) {
      return parent::getText();
    }

    // Strip what the reader does not see. 'strip_tags()' below drops the
    // tags but keeps their bodies, so a script or style body would otherwise
    // count as page text.
    $raw_content = preg_replace([
      '@<head>(.+?)</head>@si',
      '@<script\b[^>]*>.*?</script>@si',
      '@<style\b[^>]*>.*?</style>@si',
    ], '', $this->getContent());

    $text = strip_tags((string) $raw_content);

    // Mink's own 'getText()' includes the page title, so keep it.
    $title_element = $this->find('css', 'title');

    if ($title_element) {
      $text = $title_element->getText() . ' ' . $text;
    }

    $text = html_entity_decode($text, ENT_QUOTES);
    $text = str_replace("\n", ' ', $text);
    $text = preg_replace('/ {2,}/', ' ', $text);

    return trim((string) $text);
  }

}
