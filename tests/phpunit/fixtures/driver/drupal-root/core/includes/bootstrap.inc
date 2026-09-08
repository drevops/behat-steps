<?php

/**
 * @file
 * Stands in for the bootstrap include a real Drupal root exposes.
 *
 * 'DrupalDriver::detectMajorVersion()' requires this file to decide the root
 * holds a Drupal installation. Tests that reach it override
 * 'readVersionConstant()', so nothing here needs to define '\Drupal'.
 */

declare(strict_types=1);
