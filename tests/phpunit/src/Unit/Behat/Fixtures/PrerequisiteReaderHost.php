<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Plain class composing the counted and malformed prerequisite traits.
 */
class PrerequisiteReaderHost {

  use CountedPrerequisiteTrait;
  use NotListPrerequisiteTrait;
  use WrongEntryPrerequisiteTrait;

}
