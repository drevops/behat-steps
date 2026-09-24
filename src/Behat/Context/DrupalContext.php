<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use DrevOps\BehatSteps\Helper\DrupalApiTrait;
use DrevOps\BehatSteps\Steps\Drupal\BatchTrait;
use DrevOps\BehatSteps\Steps\Drupal\BigPipeTrait;
use DrevOps\BehatSteps\Steps\Drupal\BlockTrait;
use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigOverrideTrait;
use DrevOps\BehatSteps\Steps\Drupal\ConfigTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use DrevOps\BehatSteps\Steps\Drupal\DraggableviewsTrait;
use DrevOps\BehatSteps\Steps\Drupal\DrushTrait;
use DrevOps\BehatSteps\Steps\Drupal\EckTrait;
use DrevOps\BehatSteps\Steps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Steps\Drupal\EntityTrait;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use DrevOps\BehatSteps\Steps\Drupal\LanguageTrait;
use DrevOps\BehatSteps\Steps\Drupal\MediaTrait;
use DrevOps\BehatSteps\Steps\Drupal\MenuTrait;
use DrevOps\BehatSteps\Steps\Drupal\ModuleTrait;
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

/**
 * Zero-config context carrying the whole vocabulary a Drupal suite needs.
 *
 * Extending this context is enough to write features against a Drupal site
 * without writing any PHP: it is 'WebContext' plus 'DrupalApiTrait' plus
 * every trait under 'Steps\Drupal', so a Drupal project extends one class
 * and gets all 57 steps.
 *
 * A trait for a contrib module resolves nothing until one of its steps runs,
 * and then fails with a message naming the module, so composing all of them
 * costs a project nothing.
 *
 * Registering this context beside 'WebContext' is fatal, because the 28 web
 * traits would register their steps twice. 'WebContext::assertOneContext()'
 * reports that rather than letting Behat name an arbitrary step.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebContext
 * @see \DrevOps\BehatSteps\Helper\DrupalApiTrait
 */
class DrupalContext extends WebContext implements DrupalApiInterface {

  use DrupalApiTrait;

  use BatchTrait;
  use BigPipeTrait;
  use BlockTrait;
  use CacheTrait;
  use ConfigOverrideTrait;
  use ConfigTrait;
  use ContentBlockTrait;
  use ContentTrait;
  use DraggableviewsTrait;
  use DrushTrait;
  use EckTrait;
  use EmailTrait;
  use EntityTrait;
  use FileTrait;
  use LanguageTrait;
  use MediaTrait;
  use MenuTrait;
  use ModuleTrait;
  use ParagraphsTrait;
  use QueueTrait;
  use RedirectTrait;
  use SearchApiTrait;
  use StateTrait;
  use TaxonomyTrait;
  use TestmodeTrait;
  use TimeTrait;
  use UserTrait;
  use WatchdogTrait;
  use WebformTrait;

}
