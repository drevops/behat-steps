<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context composing traits that declare prerequisites.
 */
class PrerequisiteContext extends WebRawContext {

  use SamplePrerequisiteTrait;
  use StepPrerequisiteTrait;

  /**
   * Public bridge to the protected prerequisite assertion.
   */
  public function callAssertPrerequisites(string $trait): void {
    $this->assertPrerequisites($trait);
  }

  /**
   * Public bridge to the protected prerequisite check.
   */
  public function callPrerequisitesMet(string $trait): bool {
    return $this->prerequisitesMet($trait);
  }

  /**
   * Public bridge to the protected lookup that reuses a reached backend.
   *
   * @param class-string $capability
   *   The capability interface to look up.
   */
  public function callAnyBackendFor(string $capability): object {
    return $this->anyBackendFor($capability);
  }

}
