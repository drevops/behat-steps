<?php

/**
 * Reference configuration setting every option this package accepts.
 *
 * The suite in this repository runs from 'behat.php'. That file takes
 * precedence, so Behat never loads this one here.
 */

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

// The two halves are siblings: a Drupal suite registers both, because the
// navigation steps and the value transforms live in the web half.
$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext(WebContext::class)
  ->addContext(DrupalContext::class)
  ->addContext(MinkContext::class);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'files_path' => '%paths.base%/tests/behat/fixtures',
    'browser_name' => 'chrome',
    'javascript_session' => 'selenium2',
    'sessions' => [
      'browserkit_http' => ['browserkit_http' => NULL],
      'selenium2' => ['selenium2' => ['wd_host' => 'http://localhost:4444/wd/hub']],
    ],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    // Both the allow-list and the precedence order: a step resolves the first
    // driver here that provides the capability the step needs.
    'drivers' => ['drupal', 'drush', 'blackbox'],
    'login_field' => 'name',
    'login_wait' => 0,
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
      'login_form_selector' => 'form#user-login,form#user-login-form',
      'logged_in_selector' => 'body.logged-in,body.user-logged-in',
    ],
    'regions' => [
      'content' => '#content',
      'sidebar' => '#sidebar',
      'footer' => '#footer',
    ],
    // Defaults of the options the step traits declare, keyed by trait group.
    // STEPS.md lists every group and option; the few below are the ones a
    // project almost always sets.
    'steps' => [
      'javascript' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
      'watchdog' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
      'wait' => ['ajax_timeout' => 5],
      'message' => [
        'selectors' => [
          'default' => '.messages',
          'error' => '.messages.messages--error',
          'success' => '.messages.messages--status',
          'warning' => '.messages.messages--warning',
        ],
      ],
      'mapping' => [
        'groups' => [
          'paths' => [
            'User Login' => '/user/login',
            'User Registration' => '/user/register',
          ],
        ],
      ],
    ],
  ]));

return (new Config())->withProfile($profile);
