<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;

/**
 * Create the languages a scenario needs.
 *
 * - Add languages by their ISO code, skipping ones already installed.
 *
 * Languages created here are removed after the scenario along with every other
 * entity the scenario created. A scenario that also installs the 'language'
 * module leaves that removal to the module uninstall, with
 * '@behat-steps-entity-cleanup-skip:language', because the two teardown hooks
 * run in no guaranteed order.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait LanguageTrait {

  /**
   * Create the listed languages.
   *
   * @code
   * Given the following languages exist:
   *   | langcode |
   *   | fr       |
   *   | de       |
   * @endcode
   */
  #[Given('the following languages exist:')]
  public function languageCreateMultiple(TableNode $table): void {
    $this->drupal();

    foreach ($table->getHash() as $row) {
      $langcode = $row['langcode'] ?? reset($row);

      if (!is_string($langcode) || $langcode === '') {
        throw new \RuntimeException('Each row must carry a non-empty "langcode" value.');
      }

      $this->languageCreate(new EntityStub('language', NULL, ['langcode' => $langcode]));
    }
  }

}
