<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Plain class composing the traits the reader's failure and cache tests read.
 */
class PrerequisiteReaderHost {

  use CountedPrerequisiteTrait;
  use NotListPrerequisiteTrait;
  use WrongEntryPrerequisiteTrait;

}
