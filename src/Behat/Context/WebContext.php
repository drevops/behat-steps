<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Environment\ContextEnvironment;
use Behat\Hook\BeforeSuite;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use DrevOps\BehatSteps\Steps\Web\AccessibilityTrait;
use DrevOps\BehatSteps\Steps\Web\BasicAuthTrait;
use DrevOps\BehatSteps\Steps\Web\CommandTrait;
use DrevOps\BehatSteps\Steps\Web\CookieTrait;
use DrevOps\BehatSteps\Steps\Web\DateTrait;
use DrevOps\BehatSteps\Steps\Web\DiagnosticsTrait;
use DrevOps\BehatSteps\Steps\Web\DropzoneTrait;
use DrevOps\BehatSteps\Steps\Web\ElementTrait;
use DrevOps\BehatSteps\Steps\Web\FieldTrait;
use DrevOps\BehatSteps\Steps\Web\FileDownloadTrait;
use DrevOps\BehatSteps\Steps\Web\IframeTrait;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\JsonTrait;
use DrevOps\BehatSteps\Steps\Web\KeyboardTrait;
use DrevOps\BehatSteps\Steps\Web\LinkTrait;
use DrevOps\BehatSteps\Steps\Web\MappingTrait;
use DrevOps\BehatSteps\Steps\Web\MessageTrait;
use DrevOps\BehatSteps\Steps\Web\MetatagTrait;
use DrevOps\BehatSteps\Steps\Web\ModalTrait;
use DrevOps\BehatSteps\Steps\Web\PathTrait;
use DrevOps\BehatSteps\Steps\Web\RandomTrait;
use DrevOps\BehatSteps\Steps\Web\RegionTrait;
use DrevOps\BehatSteps\Steps\Web\ResponseTrait;
use DrevOps\BehatSteps\Steps\Web\ResponsiveTrait;
use DrevOps\BehatSteps\Steps\Web\RestTrait;
use DrevOps\BehatSteps\Steps\Web\TableTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;
use DrevOps\BehatSteps\Steps\Web\XmlTrait;

/**
 * Zero-config context carrying the whole web vocabulary.
 *
 * Registering this context in a suite is enough to write features against a
 * web page without writing any PHP. It defines no steps of its own: it is
 * 'WebRawContext' plus every trait under 'Steps\Web'.
 *
 * A trait that can fail a scenario for a reason it did not ask about carries
 * an 'enabled' option, so a project switches it off through configuration
 * rather than by composing its own context.
 *
 * A Drupal suite extends 'DrupalContext', which extends this class, so it
 * gets the web vocabulary too. Registering both is fatal.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebRawContext
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalContext
 */
class WebContext extends WebRawContext {

  use AccessibilityTrait;
  use BasicAuthTrait;
  use CommandTrait;
  use CookieTrait;
  use DateTrait;
  use DiagnosticsTrait;
  use DropzoneTrait;
  use ElementTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use IframeTrait;
  use JavascriptTrait;
  use JsonTrait;
  use KeyboardTrait;
  use LinkTrait;
  use MappingTrait;
  use MessageTrait;
  use MetatagTrait;
  use ModalTrait;
  use PathTrait;
  use RandomTrait;
  use RegionTrait;
  use ResponseTrait;
  use ResponsiveTrait;
  use RestTrait;
  use TableTrait;
  use WaitTrait;
  use XmlTrait;

  /**
   * Rejects a suite that registers this context and a subclass of it.
   *
   * Both register the same 28 web traits, and Behat reports that as a
   * 'RedundantStepException' naming whichever step text it reached first,
   * which says nothing about the cause.
   *
   * @throws \RuntimeException
   *   When the suite registers two contexts that both carry this class.
   */
  #[BeforeSuite]
  public static function assertOneContext(BeforeSuiteScope $scope): void {
    $environment = $scope->getEnvironment();

    if (!$environment instanceof ContextEnvironment) {
      return;
    }

    $registered = array_values(array_filter($environment->getContextClasses(), static fn(string $class): bool => is_a($class, self::class, TRUE)));

    if (count($registered) < 2) {
      return;
    }

    throw new \RuntimeException(sprintf('The "%s" suite registers %s, which all carry the %s vocabulary, so every web step would register more than once. Register the one lowest in the chain and drop the rest: a Drupal suite needs %s alone.', $scope->getSuite()->getName(), implode(' and ', $registered), self::class, DrupalContext::class));
  }

}
