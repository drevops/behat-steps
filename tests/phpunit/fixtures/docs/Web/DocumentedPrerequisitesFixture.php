<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;

/**
 * Trait declaring its prerequisites in the method its own name derives.
 */
trait DocumentedPrerequisitesTrait {

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function documentedPrerequisitesPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
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
