---
title: Configuration reference
sidebar_position: 4
description: Every option of the behat_steps extension key, every tag Behat Steps reads and every environment variable it reads, with each one's type, default and effect.
---

<!-- cspell:ignore bigpipe testmode -->

# Configuration reference

This page lists every setting Behat Steps reads: the options of the `behat_steps` extension key, the tags a scenario or a feature can carry and the environment variables. Use it to look up what a setting does, its type and its default. [Configuration](configuration.md) explains where each kind of setting goes and how they combine, and each trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md) lists that trait's options.

[docs.php](../../../docs.php) generates the tables of extension options and tags from the source, and CI fails when they fall behind it. [Documentation](../contributing/documentation.md#generated-pages) covers regenerating them.

## Extension options

These options sit under the `behat_steps` extension key of a profile, as [The `behat_steps` extension key](configuration.md#2-the-behat_steps-extension-key) shows. A nested option is a section in the configuration and a dotted path in the table:

[//]: # (START_EXTENSION_OPTIONS)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `backends` | map | `[]` | Ordered list of the backends a scenario may resolve, most preferred first. It is both the allow-list and the precedence order: a step names the capability it needs and the first backend here providing it answers. A bare entry names a registered backend; a `tag: backend` entry gives it a name of its own, so the same feature file runs against a different backend in another profile. Omit it to get every registered backend, in registration order.<pre><code>- drupal<br/>- api: acme-jsonapi<br/>- blackbox<br/></code></pre> |
| `login_field` | string | `'name'` | User entity property submitted as the login value. Defaults to `name`. Set to `mail` for sites that authenticate by email, or any other user property. |
| `regions` | map | `[]` | Map of named regions to CSS selectors. Region steps such as 'I press the button :button in the region :region' resolve against this map.<pre><code>My region: "#css-selector"<br/>Content: "#main .region-content"<br/>Right sidebar: "#sidebar-second"<br/></code></pre> |
| `text` | section | - | Text strings, such as Log out or the Username field can be altered in the Behat configuration if they vary from the default values.<pre><code>login_url: "/user"<br/>logout_url: "/user/logout"<br/>logout_confirm_url: "/user/logout/confirm"<br/>logout: "Sign out"<br/>login: "Sign in"<br/>password_field: "Enter your password"<br/>username_field: "Nickname"<br/></code></pre> |
| `text.login_url` | string | `'/user'` | Path the login steps submit the login form on. |
| `text.logout_url` | string | `'/user/logout'` | Path the logout steps request. |
| `text.logout_confirm_url` | string | `'/user/logout/confirm'` | Path of the logout confirmation form, submitted when the site asks to confirm. |
| `text.login` | string | `'Log in'` | Text of the login submit button. |
| `text.logout` | string | `'Log out'` | Text of the logout link. |
| `text.password_field` | string | `'Password'` | Label of the password field on the login form. |
| `text.username_field` | string | `'Username'` | Label of the username field on the login form. |
| `login_wait` | integer | `0` | Maximum seconds to wait for post-login DOM signals (URL change, body render, logged-in selector, logout link). Set to 0 to disable waiting. A JavaScript session still waits up to 10 seconds, or this value when it is larger, for the browser to leave the login form or show the logged-in selector, and for the logged-in selector or the logout link on the homepage. Each wait ends as soon as its signal appears. |
| `steps` | map | `[]` | Default values of the options the step traits declare, keyed by trait group and then by option name. Each group is named after the trait that declares it, so `JavascriptTrait` reads `javascript` and `BigPipeTrait` reads `big_pipe`. A group naming a trait none of the registered contexts composes is ignored, so one profile can carry the defaults of every suite.<pre><code>javascript:<br/>  enabled: true<br/>  fail_on_errors: false<br/>wait:<br/>  ajax_timeout: 10<br/></code></pre> |
| `selectors` | section | - | CSS selectors the steps resolve page structures against. |
| `selectors.login_form_selector` | string | `'form#user-login,form#user-login-form'` | Selector of the login form, used to tell a login page from a page that merely holds a login block. |
| `selectors.logged_in_selector` | string | `'body.logged-in,body.user-logged-in'` | Selector present only while a user is authenticated, used to confirm a login took effect. |
| `blackbox` | section | - | Settings of the backend that drives the site through the browser only. It has no options, and it performs no backend operation, so it provides no capability a step can resolve. |
| `drupal` | section | - | Settings of the backend that bootstraps Drupal in-process. |
| `drupal.drupal_root` | string | required | Path to the Drupal root the in-process backend bootstraps. |
| `drupal.core` | string | - | Class of the Core the in-process backend delegates to, in place of the one the package ships for the Drupal major the backend detects. A class implements CoreInterface, and one built on a shipped Core extends the Core for that major. |
| `drupal.field_handlers` | map | `[]` | Map of field types to the handler classes that turn a value a step gives a field into the value Drupal stores. An entry adds a handler for a field type the package ships none for, or replaces the shipped one. A class implements FieldHandlerInterface.<pre><code>rating: Acme\Behat\Field\RatingHandler<br/>datetime: Acme\Behat\Field\FiscalDatetimeHandler<br/></code></pre> |
| `drupal.creation_aliases` | map | `[]` | List of creation alias classes the backend registers after the ones it ships. An alias resolves a value a creation step gives an entity that is not a field of it, such as `author` on a node. An entry adds an alias, or replaces the shipped one with the same name on the same entity type. A class implements PreCreateAliasInterface or PostCreateAliasInterface and takes no constructor arguments.<pre><code>- Acme\Behat\Alias\OrganizationAlias<br/></code></pre> |
| `drush` | section | - | Settings of the backend that reaches the site by running Drush 13 or newer. |
| `drush.alias` | string | - | Drush site alias to run every command against. |
| `drush.binary` | string | `'vendor/bin/drush'` | Path to the Drush binary. |
| `drush.root` | string | - | Drupal root passed to Drush, for a site Drush cannot locate on its own. |
| `drush.global_options` | string | - | Options appended to every Drush command, such as `--uri=http://example.com`. |
| `drush.creation_aliases` | map | `[]` | List of creation alias classes the backend registers after the ones it ships. The Drush backend creates only users, so it runs only the aliases for the `user` entity type. A class implements PreCreateAliasInterface or PostCreateAliasInterface and takes no constructor arguments.<pre><code>- Acme\Behat\Alias\DepartmentAlias<br/></code></pre> |

[//]: # (END_EXTENSION_OPTIONS)

## Tags

A tag configures the scenario it's on, or every scenario of the feature when it's on the `Feature:` line. [Tags](configuration.md#3-tags) explains how a scenario's tags combine with its feature's.

A parametrized tag takes its value after a colon, never a hyphen: `@module:redirect`, not `@module-redirect`. A flag tag takes no value. A tag with nothing after the colon is ignored, except `@behat-steps-skip:` and `@backend:`, which fail at scenario start like any other value they can't resolve. `@module:` also switches its value off with a `!`: `@module:!redirect`.

The following table lists every tag Behat Steps reads:

[//]: # (START_TAGS)

| Tag | Description |
| --- | --- |
| `@behat-steps-skip:VALUE` | Switch off every hook of the named trait, such as `EmailTrait`. On a trait that declares an `enabled` option, the tag sets it to FALSE. A value not shaped like a trait name, or naming a trait no context of the suite composes, fails the run at scenario start. |
| `@behat-steps-entity-cleanup-skip:VALUE` | Keep entities of the named entity type after the scenario. Repeat the tag to keep several types. |
| `@backend:VALUE` | Move the named backend to the front of the configured backend list for the scenario. Repeat the tag to promote several: they keep the configured order among themselves, so the order the tags are written in does not matter. The tag reorders the list; it never adds to it. |
| `@module:VALUE` | Enable the named module for the scenario, or disable it when the name is prefixed with `!`. The original state is restored afterwards. |
| `@breakpoint:VALUE` | Resize the viewport to the named breakpoint before the first step. A scenario and its feature take 1 tag each, and the scenario's replaces the feature's. The scenario or its feature has to be `@javascript`. |
| `@email:VALUE` | Collect email for the scenario with the named handler type. A bare `@email` uses the `default` handler. |
| `@watchdog:VALUE` | Track the named Watchdog message type in addition to `php`, which is always tracked. |
| `@disable-config-override:VALUE` | Disable `settings.php` overrides for the named configuration object for the duration of the scenario. |
| `@accessibility:VALUE` | Assess every page the scenario visits. The value sets the impact threshold that fails the scenario: `critical`, `serious`, `moderate`, `minor`, `any`, `warning` or `strict`. |
| `@bigpipe` | Render BigPipe placeholders server-side, for a browser driver without JavaScript. |
| `@disable-form-validation` | Strip HTML5 validation from every form on the page so a scenario can submit values the browser would block. |
| `@js-errors` | Allow JavaScript errors, which otherwise fail the scenario. They are still collected. Sets the `javascript.fail_on_errors` option to FALSE. |
| `@download` | Prepare the download directory for the scenario and clean it up afterwards. |
| `@testmode` | Enable the Testmode module for the scenario. |
| `@debug` | Print detailed diagnostics while the scenario runs. |
| `@error` | Expect the scenario to log an error. The errors are still read and cleared, and the scenario is not failed. Sets the `watchdog.fail_on_errors` option to FALSE. |

[//]: # (END_TAGS)

`@javascript` is Mink's own tag: it runs the scenario on Mink's JavaScript session, as [Browsers](browsers.md#how-a-scenario-picks-a-browser) explains.

## Environment variables

Behat Steps reads the following environment variables, and nothing else from the environment. Each one varies a run without changing any committed configuration:

| Variable | Read by | Effect |
| --- | --- | --- |
| `BEHAT_STEPS_DISABLE_CLEANUP` | `EntityLifecycleTrait`, `AuthTrait` | Set to `1`, `true`, `yes` or `on` to keep the entities, users and roles a scenario created, instead of deleting them in the teardown. The logged-in user also stays logged in for the scenarios that follow. For inspecting the state a failing scenario left behind, not for CI. |
| `BEHAT_ACCESSIBILITY_PRINT` | `AccessibilityTrait` | Set to any non-empty value other than `0` to print a one-line accessibility summary per page to the console. |
| `COMPOSER_BIN_DIR` | `DrushBackend` | When `drush.binary` is the bare `drush`, names the directory the Drush binary is resolved from, before the backend falls back to `vendor/bin/drush` under the working directory and then to `drush` on the `PATH`. Composer sets it inside its own scripts. |

`BEHAT_PARAMS` is Behat's own variable, not Behat Steps'. [Environment variables](configuration.md#4-environment-variables) explains which settings it can change.
