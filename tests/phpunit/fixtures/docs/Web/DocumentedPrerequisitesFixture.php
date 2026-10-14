<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;

/**
 * Trait declaring its prerequisites in the method its own name derives.
 */
trait DocumentedPrerequisitesTrait {

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function documentedPrerequisitesPrerequisites(): array {
    return [
      Prerequisite::capability(ContentCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('documented'), 'the "documented" module is enabled'),
    ];
  }

}

/**
 * Class composing the trait that declares prerequisites.
 */
class DocumentedPrerequisitesContext {

  use DocumentedPrerequisitesTrait;

}
