<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * A stream that can be stat'ed and read, but never written.
 *
 * File permissions do not restrict a process running as root, so a read-only
 * file cannot reliably simulate a failed write. A path under this protocol
 * passes is_file(), chmod() and file_get_contents(), then fails
 * file_put_contents().
 *
 * PHP calls the handler methods by the names below, so they cannot be renamed.
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 *
 * phpcs:disable Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
 */
class UnwritableStream {

  /**
   * The protocol the wrapper is registered under.
   */
  public const PROTOCOL = 'unwritable';

  /**
   * The contents every path under the protocol reads back.
   */
  public static string $contents = '';

  /**
   * The stream context, assigned by PHP.
   *
   * @var resource|null
   */
  public $context;

  /**
   * Read offset into the contents.
   */
  protected int $offset = 0;

  /**
   * Registers the wrapper and the contents its paths read back.
   *
   * @param string|null $contents
   *   The contents every path under the protocol reads back, or NULL for none.
   */
  public static function register(?string $contents = NULL): void {
    static::$contents = $contents ?? '';

    stream_wrapper_register(static::PROTOCOL, static::class);
  }

  /**
   * Unregisters the wrapper.
   */
  public static function unregister(): void {
    stream_wrapper_unregister(static::PROTOCOL);
  }

  /**
   * Builds a path under the protocol.
   *
   * @param string $name
   *   The file name.
   *
   * @return string
   *   The path.
   */
  public static function path(string $name): string {
    return static::PROTOCOL . '://' . $name;
  }

  /**
   * Opens a path, for reading only.
   *
   * @param string $path
   *   The path being opened.
   * @param string $mode
   *   The mode the caller asked for.
   *
   * @return bool
   *   TRUE for a read, FALSE for anything that would write.
   */
  public function stream_open(string $path, string $mode): bool {
    $this->offset = 0;

    return str_starts_with($mode, 'r');
  }

  /**
   * Reads from the contents.
   *
   * @param int $count
   *   The number of bytes to read.
   *
   * @return string
   *   The bytes read.
   */
  public function stream_read(int $count): string {
    $chunk = substr(static::$contents, $this->offset, $count);
    $this->offset += strlen($chunk);

    return $chunk;
  }

  /**
   * Reports whether the whole of the contents has been read.
   */
  public function stream_eof(): bool {
    return $this->offset >= strlen(static::$contents);
  }

  /**
   * Describes the open stream.
   *
   * @return array<string, int>
   *   The stat record.
   */
  public function stream_stat(): array {
    return $this->record();
  }

  /**
   * Describes a path, as a regular file so that is_file() accepts it.
   *
   * @param string $path
   *   The path being described.
   * @param int $flags
   *   The stat flags.
   *
   * @return array<string, int>
   *   The stat record.
   */
  public function url_stat(string $path, int $flags): array {
    return $this->record();
  }

  /**
   * Accepts chmod() and the other metadata calls.
   *
   * @param string $path
   *   The path being changed.
   * @param int $option
   *   The metadata option.
   * @param mixed $value
   *   The value for the option.
   */
  public function stream_metadata(string $path, int $option, mixed $value): bool {
    return TRUE;
  }

  /**
   * Builds the stat record of a regular, readable file.
   *
   * @return array<string, int>
   *   The stat record.
   */
  protected function record(): array {
    // 0100000 marks a regular file, which is_file() checks for.
    return ['mode' => 0100644, 'size' => strlen(static::$contents)];
  }

}
