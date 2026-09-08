<?php

/**
 * @file
 * Feature context for testing Behat-steps.
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Steps\Drupal\BigPipeTrait;
use DrevOps\BehatSteps\Steps\Drupal\BlockTrait;
use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigOverrideTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use DrevOps\BehatSteps\Steps\Drupal\DraggableviewsTrait;
use DrevOps\BehatSteps\Steps\Drupal\EckTrait;
use DrevOps\BehatSteps\Steps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use DrevOps\BehatSteps\Steps\Drupal\HelperTrait as DrupalHelperTrait;
use DrevOps\BehatSteps\Steps\Drupal\MediaTrait;
use DrevOps\BehatSteps\Steps\Drupal\MenuTrait;
use DrevOps\BehatSteps\Steps\Drupal\ModuleTrait;
use DrevOps\BehatSteps\Steps\Drupal\OverrideTrait;
use DrevOps\BehatSteps\Steps\Drupal\ParagraphsTrait;
use DrevOps\BehatSteps\Steps\Drupal\QueueTrait;
use DrevOps\BehatSteps\Steps\Drupal\RedirectTrait;
use DrevOps\BehatSteps\Steps\Drupal\SearchApiTrait;
use DrevOps\BehatSteps\Steps\Drupal\StateTrait;
use DrevOps\BehatSteps\Steps\Drupal\TaxonomyTrait;
use DrevOps\BehatSteps\Steps\Drupal\TestmodeTrait;
use DrevOps\BehatSteps\Steps\Drupal\TimeTrait;
use DrevOps\BehatSteps\Steps\Drupal\UserTrait;
use DrevOps\BehatSteps\Steps\Drupal\WatchdogTrait;
use DrevOps\BehatSteps\Steps\Drupal\WebformTrait;
use DrevOps\BehatSteps\Steps\Generic\AccessibilityTrait;
use DrevOps\BehatSteps\Steps\Generic\CommandTrait;
use DrevOps\BehatSteps\Steps\Generic\CookieTrait;
use DrevOps\BehatSteps\Steps\Generic\DateTrait;
use DrevOps\BehatSteps\Steps\Generic\DiagnosticsTrait;
use DrevOps\BehatSteps\Steps\Generic\DropzoneTrait;
use DrevOps\BehatSteps\Steps\Generic\ElementTrait;
use DrevOps\BehatSteps\Steps\Generic\FieldTrait;
use DrevOps\BehatSteps\Steps\Generic\FileDownloadTrait;
use DrevOps\BehatSteps\Steps\Generic\HelperTrait;
use DrevOps\BehatSteps\Steps\Generic\IframeTrait;
use DrevOps\BehatSteps\Steps\Generic\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Generic\JsonTrait;
use DrevOps\BehatSteps\Steps\Generic\KeyboardTrait;
use DrevOps\BehatSteps\Steps\Generic\LinkTrait;
use DrevOps\BehatSteps\Steps\Generic\MetatagTrait;
use DrevOps\BehatSteps\Steps\Generic\ModalTrait;
use DrevOps\BehatSteps\Steps\Generic\PathTrait;
use DrevOps\BehatSteps\Steps\Generic\ResponseTrait;
use DrevOps\BehatSteps\Steps\Generic\ResponsiveTrait;
use DrevOps\BehatSteps\Steps\Generic\RestTrait;
use DrevOps\BehatSteps\Steps\Generic\TableTrait;
use DrevOps\BehatSteps\Steps\Generic\WaitTrait;
use DrevOps\BehatSteps\Steps\Generic\XmlTrait;
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
   * Override elementGetScrollIntoViewCenter() to allow runtime toggling.
   *
   * This cannot be moved to FeatureContextTrait because traits cannot override
   * methods from other traits.
   */
  protected function elementGetScrollIntoViewCenter(): bool {
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
    $this->bigPipeWaitTimeout = $scope->getScenario()->hasTag('test-bigpipe-timeout') ? 2000 : self::BIG_PIPE_DEFAULT_WAIT_TIMEOUT;
  }

}
