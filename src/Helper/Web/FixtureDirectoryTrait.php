<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

/**
 * Resolves fixture files in the directory Mink's `files_path` parameter names.
 *
 * A path resolves only to a file inside that directory, so a `..` segment
 * cannot reach a file elsewhere on disk.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait FixtureDirectoryTrait {

  /**
   * Find the fixtures directory.
   *
   * @return string|null
   *   The absolute path of the directory, or NULL when the `files_path`
   *   parameter is unset or names no directory.
   */
  public function fixtureDirectoryFind(): ?string {
    $files_path = $this->getMinkParameter('files_path');

    if (empty($files_path)) {
      return NULL;
    }

    $directory = realpath((string) $files_path);

    return $directory !== FALSE && is_dir($directory) ? rtrim($directory, DIRECTORY_SEPARATOR) : NULL;
  }

  /**
   * Get the fixtures directory.
   *
   * @return string
   *   The absolute path of the directory.
   *
   * @throws \RuntimeException
   *   When the `files_path` parameter is unset or names no directory.
   */
  public function fixtureDirectoryGet(): string {
    $directory = $this->fixtureDirectoryFind();

    if ($directory !== NULL) {
      return $directory;
    }

    if (empty($this->getMinkParameter('files_path'))) {
      throw new \RuntimeException('The Mink "files_path" parameter is not configured.');
    }

    throw new \RuntimeException('The Mink "files_path" parameter is invalid or not accessible.');
  }

  /**
   * Find a file in the fixtures directory.
   *
   * @param string $path
   *   The path of the file, relative to the fixtures directory.
   *
   * @return string|null
   *   The absolute path of the file, or NULL when the fixtures directory is
   *   not configured, the file does not exist, or the path leads outside the
   *   directory.
   */
  public function fixtureDirectoryFindFile(string $path): ?string {
    $directory = $this->fixtureDirectoryFind();

    if ($directory === NULL) {
      return NULL;
    }

    $file = $directory . '/' . ltrim($path, '/\\');

    if (!is_file($file)) {
      return NULL;
    }

    $resolved = realpath($file);

    // is_file() also succeeds for a '..' path that resolves outside the
    // fixtures directory.
    return $resolved !== FALSE && str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR) ? $resolved : NULL;
  }

  /**
   * Get a file in the fixtures directory.
   *
   * @param string $path
   *   The path of the file, relative to the fixtures directory. Surrounding
   *   whitespace is ignored.
   *
   * @return string
   *   The absolute path of the file.
   *
   * @throws \RuntimeException
   *   When the path is empty, the fixtures directory is not configured, the
   *   file does not exist, or the path leads outside the directory.
   */
  public function fixtureDirectoryGetFile(string $path): string {
    $path = trim($path);

    if ($path === '') {
      throw new \RuntimeException('A fixture file path cannot be empty.');
    }

    $file = $this->fixtureDirectoryFindFile($path);

    if ($file !== NULL) {
      return $file;
    }

    $unresolved = $this->fixtureDirectoryGet() . '/' . ltrim($path, '/\\');

    if (!is_file($unresolved)) {
      throw new \RuntimeException(sprintf('The fixture file "%s" does not exist.', $unresolved));
    }

    throw new \RuntimeException(sprintf('The fixture file "%s" is outside the configured "files_path".', $path));
  }

  /**
   * Read a file in the fixtures directory.
   *
   * @param string $path
   *   The path of the file, relative to the fixtures directory.
   *
   * @return string
   *   The contents of the file.
   *
   * @throws \RuntimeException
   *   When the file cannot be resolved, as for fixtureDirectoryGetFile(), or
   *   cannot be read.
   */
  public function fixtureDirectoryReadFile(string $path): string {
    $file = $this->fixtureDirectoryGetFile($path);

    $content = file_get_contents($file);

    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Failed to read the file "%s".', $file));
      // @codeCoverageIgnoreEnd
    }

    return $content;
  }

}
