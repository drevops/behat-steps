<?php

/**
 * @file
 * Feature context for testing Behat-steps.
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\AccessibilityTrait;
use DrevOps\BehatSteps\CommandTrait;
use DrevOps\BehatSteps\CookieTrait;
use DrevOps\BehatSteps\DateTrait;
use DrevOps\BehatSteps\DiagnosticsTrait;
use DrevOps\BehatSteps\Drupal\BigPipeTrait;
use DrevOps\BehatSteps\Drupal\BlockTrait;
use DrevOps\BehatSteps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Drupal\ConfigOverrideTrait;
use DrevOps\BehatSteps\Drupal\ConfigTrait;
use DrevOps\BehatSteps\Drupal\ContentBlockTrait;
use DrevOps\BehatSteps\Drupal\ContentTrait;
use DrevOps\BehatSteps\Drupal\DraggableviewsTrait;
use DrevOps\BehatSteps\Drupal\EckTrait;
use DrevOps\BehatSteps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Drupal\FileTrait;
use DrevOps\BehatSteps\Drupal\HelperTrait as DrupalHelperTrait;
use DrevOps\BehatSteps\Drupal\MediaTrait;
use DrevOps\BehatSteps\Drupal\MenuTrait;
use DrevOps\BehatSteps\Drupal\ModuleTrait;
use DrevOps\BehatSteps\Drupal\OverrideTrait;
use DrevOps\BehatSteps\Drupal\ParagraphsTrait;
use DrevOps\BehatSteps\Drupal\QueueTrait;
use DrevOps\BehatSteps\Drupal\RedirectTrait;
use DrevOps\BehatSteps\Drupal\SearchApiTrait;
use DrevOps\BehatSteps\Drupal\StateTrait;
use DrevOps\BehatSteps\Drupal\TaxonomyTrait;
use DrevOps\BehatSteps\Drupal\TestmodeTrait;
use DrevOps\BehatSteps\Drupal\TimeTrait;
use DrevOps\BehatSteps\Drupal\UserTrait;
use DrevOps\BehatSteps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Drupal\WebformTrait;
use DrevOps\BehatSteps\DropzoneTrait;
use DrevOps\BehatSteps\ElementTrait;
use DrevOps\BehatSteps\FieldTrait;
use DrevOps\BehatSteps\FileDownloadTrait;
use DrevOps\BehatSteps\HelperTrait;
use DrevOps\BehatSteps\IframeTrait;
use DrevOps\BehatSteps\JavascriptTrait;
use DrevOps\BehatSteps\JsonTrait;
use DrevOps\BehatSteps\KeyboardTrait;
use DrevOps\BehatSteps\LinkTrait;
use DrevOps\BehatSteps\MetatagTrait;
use DrevOps\BehatSteps\ModalTrait;
use DrevOps\BehatSteps\PathTrait;
use DrevOps\BehatSteps\ResponseTrait;
use DrevOps\BehatSteps\ResponsiveTrait;
use DrevOps\BehatSteps\RestTrait;
use DrevOps\BehatSteps\TableTrait;
use DrevOps\BehatSteps\WaitTrait;
use DrevOps\BehatSteps\XmlTrait;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use AccessibilityTrait;
  use BigPipeTrait;
  use BlockTrait;
  use CacheTrait;
  use CommandTrait;
  use ConfigOverrideTrait;
  use ConfigTrait;
  use ContentBlockTrait;
  use ContentTrait;
  use CookieTrait;
  use DateTrait;
  use DiagnosticsTrait;
  use DraggableviewsTrait;
  use DropzoneTrait;
  use EckTrait;
  use ElementTrait;
  use EmailTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use IframeTrait;
  use FileTrait;
  use JavascriptTrait;
  use JsonTrait;
  use KeyboardTrait;
  use LinkTrait;
  use MediaTrait;
  use MenuTrait;
  use MetatagTrait;
  use ModalTrait;
  use ModuleTrait;
  use OverrideTrait;
  use ParagraphsTrait;
  use PathTrait;
  use QueueTrait;
  use RedirectTrait;
  use ResponseTrait;
  use RestTrait;
  use ResponsiveTrait;
  use SearchApiTrait;
  use StateTrait;
  use TableTrait;
  use TaxonomyTrait;
  use TestmodeTrait;
  use TimeTrait;
  use UserTrait;
  use HelperTrait;
  use DrupalHelperTrait;
  use WaitTrait;
  use WatchdogTrait;
  use WebformTrait;
  use XmlTrait;

  use FeatureContextTrait;

  /**
   * Override dateNow() method to return a preset value for testing.
   *
   * This cannot be moved to FeatureContextTrait because traits cannot override
   * methods from other traits.
   */
  protected static function dateNow(): int {
    return strtotime('2024-07-15 12:00:00');
  }

  /**
   * Override elementScrollIntoViewCenter() to allow runtime toggling.
   *
   * This cannot be moved to FeatureContextTrait because traits cannot override
   * methods from other traits.
   */
  protected function elementScrollIntoViewCenter(): bool {
    return $this->testElementScrollCenter;
  }

  /**
   * Override accessibilityGetReportDir() to anchor reports to the base path.
   *
   * Behat is launched from the build directory but configured with the
   * project-root behat.yml, so the captured working directory is not the
   * base path. Deriving the base from the Mink files_path keeps accessibility
   * reports in the same .logs tree as the other Behat artifacts.
   *
   * This cannot be moved to FeatureContextTrait because traits cannot override
   * methods from other traits.
   */
  protected function accessibilityGetReportDir(): string {
    return dirname((string) $this->getMinkParameter('files_path'), 3) . '/.logs/test_results/accessibility';
  }

  /**
   * Shorten the BigPipe wait timeout for the timeout coverage scenario.
   *
   * Scenarios tagged '@test-bigpipe-timeout' use a short timeout so they can
   * exercise the wait timing out quickly; every other scenario keeps the trait's
   * default.
   */
  #[BeforeScenario]
  public function bigPipeSetWaitTimeout(BeforeScenarioScope $scope): void {
    $this->bigPipeWaitTimeout = $scope->getScenario()->hasTag('test-bigpipe-timeout') ? 2000 : self::DEFAULT_WAIT_TIMEOUT;
  }

}
