<?php

/**
 * Reference configuration setting every option this package accepts.
 *
 * The suite in this repository runs from 'behat.php', which takes precedence,
 * so Behat never loads this file here.
 */

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/Behat/features')
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'files_path' => '%paths.base%/tests/fixtures/files',
    'browser_name' => 'chrome',
    'javascript_session' => 'selenium2',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
      'selenium2' => ['selenium2' => ['wd_host' => 'http://localhost:4444/wd/hub']],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'default_driver' => 'blackbox',
    'api_driver' => 'drupal',
    'drush_driver' => 'drush',
    'login_field' => 'name',
    'login_wait' => 0,
    'ajax_timeout' => 5,
    'blackbox' => NULL,
    'drupal' => ['drupal_root' => 'web'],
    'drush' => [
      'alias' => 'self',
      'binary' => 'vendor/bin/drush',
      'root' => 'web',
      'global_options' => '--uri=http://your-site.local',
    ],
    'text' => [
      'login_url' => '/user',
      'logout_url' => '/user/logout',
      'logout_confirm_url' => '/user/logout/confirm',
      'log_in' => 'Log in',
      'log_out' => 'Log out',
      'username_field' => 'Username',
      'password_field' => 'Password',
    ],
    'selectors' => [
      'messages' => [
        'default' => '.messages',
        'error' => '.messages.messages--error',
        'success' => '.messages.messages--status',
        'warning' => '.messages.messages--warning',
      ],
      'login_form_selector' => 'form#user-login,form#user-login-form',
      'logged_in_selector' => 'body.logged-in,body.user-logged-in',
    ],
    'regions' => [
      'content' => '#content',
      'sidebar' => '#sidebar',
      'footer' => '#footer',
    ],
    'mappings' => [
      'paths' => [
        'User Login' => '/user/login',
        'User Registration' => '/user/register',
      ],
    ],
  ]));

return (new Config())->withProfile($profile);
