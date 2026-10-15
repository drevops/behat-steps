<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

/**
 * Field handler for 'created' fields.
 *
 * A 'created' field stores a Unix timestamp, so it takes the values a
 * 'timestamp' field takes.
 */
class CreatedHandler extends TimestampHandler {}
