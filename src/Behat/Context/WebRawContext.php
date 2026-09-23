<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use DrevOps\BehatSteps\Helper\JavascriptSupportTrait;
use DrevOps\BehatSteps\Helper\LastStepTrait;
use DrevOps\BehatSteps\Helper\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\StringTrait;

/**
 * Base context carrying the web plumbing.
 *
 * Composes the helper traits the web half shares and registers no step
 * definitions. It references no Drupal class, which a layer lint holds.
 *
 * Extend this to compose a web context out of a chosen set of traits;
 * register 'WebContext' instead to get the whole web vocabulary.
 *
 * The helper traits it composes are on '$this' for a consuming project's own
 * step definitions, and composing one again in a step trait shares the same
 * state rather than duplicating it.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebContext
 */
class WebRawContext extends RawContext {

  use JavascriptSupportTrait;
  use LastStepTrait;
  use RequestHeadersTrait;
  use StringTrait;

}
