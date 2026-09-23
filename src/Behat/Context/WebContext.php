<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

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
 * Register it beside 'DrupalContext' rather than under it: the two halves are
 * siblings, and a suite that registers only 'DrupalContext' reaches no
 * navigation step and none of the value transforms.
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

}
