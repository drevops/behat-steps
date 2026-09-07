<?php

/**
 * @file
 * Makes the fixture site's Drupal classes resolvable to static analysis.
 *
 * Drupal's classes come from the fixture site's own autoloader, and its
 * test-only namespaces are mapped from its PHPUnit bootstrap rather than from
 * a Composer autoload entry. A tool that loads only this package's autoloader
 * therefore cannot resolve either, which the driver kernel tests need.
 *
 * Loaded as a bootstrap file by Rector; a no-op until the fixture site is
 * built.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$drupal_root = dirname(__DIR__) . '/build/web';
$autoload = $drupal_root . '/autoload.php';

if (!file_exists($autoload)) {
  return;
}

$loader = require $autoload;

if (!$loader instanceof ClassLoader) {
  return;
}

foreach (['Tests', 'KernelTests', 'FunctionalTests', 'FunctionalJavascriptTests', 'TestTools', 'BuildTests', 'TestSite'] as $namespace) {
  $loader->addPsr4('Drupal\\' . $namespace . '\\', $drupal_root . '/core/tests/Drupal/' . $namespace);
}
