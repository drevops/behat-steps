<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: read and delete the site's log entries.
 *
 * A severity is an RFC 5424 level, so a lower number is more severe.
 */
interface LogCapabilityInterface {

  /**
   * The level of an emergency.
   */
  public const int SEVERITY_EMERGENCY = 0;

  /**
   * The level of an alert.
   */
  public const int SEVERITY_ALERT = 1;

  /**
   * The level of a critical condition.
   */
  public const int SEVERITY_CRITICAL = 2;

  /**
   * The level of an error.
   */
  public const int SEVERITY_ERROR = 3;

  /**
   * The level of a warning.
   */
  public const int SEVERITY_WARNING = 4;

  /**
   * The level of a notice.
   */
  public const int SEVERITY_NOTICE = 5;

  /**
   * The level of an informational message.
   */
  public const int SEVERITY_INFO = 6;

  /**
   * The level of a debug message.
   */
  public const int SEVERITY_DEBUG = 7;

  /**
   * Returns the id of the newest log entry.
   *
   * @return int
   *   The id, or 0 when the log holds no entry.
   */
  public function logGetLastId(): int;

  /**
   * Returns the log entries newer than an id.
   *
   * @param array<int, string> $types
   *   The message types to include, such as 'php'.
   * @param int $after_id
   *   The id of the newest entry to leave out, as 'logGetLastId()' returned
   *   it before the entries of interest were logged.
   * @param int $severity
   *   The least severe level to include, so 'SEVERITY_WARNING' includes the
   *   warnings and everything more severe.
   *
   * @return array<int, array<string, mixed>>
   *   The entries, oldest first. Each carries 'wid', 'type', 'severity' as a
   *   level, 'message' and 'variables', plus the other columns the backend
   *   reads.
   */
  public function logRead(array $types, int $after_id, int $severity): array;

  /**
   * Deletes log entries.
   *
   * Does nothing for an id that names no entry.
   *
   * @param array<int, int> $ids
   *   The entry ids.
   */
  public function logDelete(array $ids): void;

}
