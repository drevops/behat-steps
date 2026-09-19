<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Time;

use Drupal\Component\Datetime\TimeInterface as CoreTimeInterface;

/**
 * Time service interface with state-based override support.
 */
interface TimeInterface extends CoreTimeInterface {

}
