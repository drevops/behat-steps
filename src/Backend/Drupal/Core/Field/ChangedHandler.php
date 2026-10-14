<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

/**
 * Field handler for 'changed' fields.
 *
 * A 'changed' field stores a Unix timestamp, so it takes the values a
 * 'timestamp' field takes.
 */
class ChangedHandler extends TimestampHandler {}
