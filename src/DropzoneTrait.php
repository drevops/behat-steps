<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\When;

/**
 * Simulate a real multi-file drag-and-drop gesture onto a Dropzone target.
 *
 * - Drop one or more files on a CSS-selected target in a single native event.
 * - Fixture paths resolve against the Mink `files_path` parameter.
 * - Works on any element that handles native `drop` events (Dropzone.js,
 *   custom drop targets, framework widgets).
 *
 * Why this exists: Mink's `attachFile` writes each file to a hidden
 * `<input type="file">` sequentially, so file A finishes uploading before
 * file B starts. Real users release multiple files together, which fires a
 * single `drop` event whose `dataTransfer.files` contains all of them and
 * triggers concurrent uploads. Race conditions in dedup maps, status
 * indicators, error handlers, and server-side queues only reproduce under
 * the multi-file path - this trait reproduces it.
 *
 * `@javascript`-only: requires a headless browser session.
 */
trait DropzoneTrait {

  /**
   * Drop a single file on the target element.
   *
   * @code
   * When I drop the file "document.pdf" on the ".dropzone" dropzone
   * @endcode
   *
   * @javascript
   */
  #[When('I drop the file :path on the :selector dropzone')]
  public function dropzoneDropFile(string $path, string $selector): void {
    $this->dropzoneDropFiles($selector, new TableNode([[$path]]));
  }

  /**
   * Drop one or more files on the target element in a single native event.
   *
   * Provide one fixture path per row.
   *
   * @code
   * When I drop the following files on the ".dropzone" dropzone:
   *   | document.pdf |
   *   | image.png    |
   *   | text.txt     |
   * @endcode
   *
   * @javascript
   */
  #[When('I drop the following files on the :selector dropzone:')]
  public function dropzoneDropFiles(string $selector, TableNode $paths): void {
    $session = $this->getSession();
    $page = $session->getPage();

    if ($page->find('css', $selector) === NULL) {
      throw new ElementNotFoundException($session->getDriver(), 'element', 'css', $selector);
    }

    $resolved_paths = [];
    foreach ($paths->getColumn(0) as $row) {
      $resolved_paths[] = $this->dropzoneResolvePath((string) $row);
    }

    $token = str_replace('.', '', uniqid('', TRUE));
    $holder_ids = [];
    foreach (array_keys($resolved_paths) as $index) {
      $holder_ids[] = 'behat_dropzone_holder_' . $token . '_' . $index;
    }

    $session->executeScript(sprintf(
      "(function(){
        var ids = %s;
        for (var i = 0; i < ids.length; i++) {
          var input = document.createElement('input');
          input.type = 'file';
          input.id = ids[i];
          input.style.position = 'fixed';
          input.style.left = '-9999px';
          input.style.opacity = '0';
          document.body.appendChild(input);
        }
      })();",
      json_encode($holder_ids)
    ));

    foreach ($holder_ids as $index => $holder_id) {
      $page->attachFileToField($holder_id, $resolved_paths[$index]);
    }

    $session->executeScript(sprintf(
      "(function(){
        var ids = %s;
        var selector = %s;
        var target = document.querySelector(selector);
        if (!target) {
          throw new Error('Dropzone target \"' + selector + '\" disappeared between resolution and dispatch.');
        }
        var dt = new DataTransfer();
        for (var i = 0; i < ids.length; i++) {
          var holder = document.getElementById(ids[i]);
          if (holder && holder.files && holder.files.length > 0) {
            dt.items.add(holder.files[0]);
          }
        }
        target.dispatchEvent(new DragEvent('drop', {
          bubbles: true,
          cancelable: true,
          dataTransfer: dt
        }));
        for (var j = 0; j < ids.length; j++) {
          var node = document.getElementById(ids[j]);
          if (node && node.parentNode) {
            node.parentNode.removeChild(node);
          }
        }
      })();",
      json_encode($holder_ids),
      json_encode($selector)
    ));
  }

  /**
   * Resolve a fixture path against the Mink `files_path` parameter.
   *
   * @param string $path
   *   Fixture path, typically a bare filename like `document.pdf`.
   *
   * @return string
   *   Absolute path to the fixture file.
   *
   * @throws \RuntimeException
   *   When the resolved fixture does not exist.
   */
  protected function dropzoneResolvePath(string $path): string {
    $path = trim($path);

    if ($path === '') {
      throw new \RuntimeException('A fixture file path cannot be empty.');
    }

    $files_path = $this->getMinkParameter('files_path');

    if (empty($files_path)) {
      throw new \RuntimeException('The Mink "files_path" parameter is not configured.');
    }

    $base = rtrim((string) realpath((string) $files_path), DIRECTORY_SEPARATOR);
    $full_path = $base . DIRECTORY_SEPARATOR . ltrim($path, '/');

    if (!is_file($full_path)) {
      throw new \RuntimeException(sprintf('The fixture file "%s" does not exist.', $full_path));
    }

    return $full_path;
  }

}
