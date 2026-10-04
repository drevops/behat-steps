<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;

/**
 * Create the languages a scenario needs.
 *
 * - Add languages by their ISO code, skipping ones already installed.
 *
 * Languages created here are removed after the scenario along with every other
 * entity the scenario created.
 *
 * The 2 teardown hooks run in no guaranteed order. A scenario that also
 * installs the 'language' module therefore leaves that removal to the module
 * uninstall, with '@behat-steps-entity-cleanup-skip:language'.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait LanguageTrait {

  use EntityLifecycleTrait;

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
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($table->getHash() as $row) {
      $langcode = $row['langcode'] ?? reset($row);

      if (!is_string($langcode) || $langcode === '') {
        throw new \RuntimeException('Each row must carry a non-empty "langcode" value.');
      }

      $this->entityLifecycleLanguageCreate(new EntityStub('language', NULL, ['langcode' => $langcode]));
    }
  }

}
