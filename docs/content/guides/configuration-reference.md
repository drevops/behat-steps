---
title: Configuration reference
sidebar_position: 4
description: Every option of the behat_steps extension key, every tag Behat Steps reads and every environment variable it reads, with each one's type, default and effect.
---

<!-- cspell:ignore bigpipe nojs testmode -->

# Configuration reference

This page lists every option of the `behat_steps` extension key, every tag a scenario or a feature can carry and every environment variable Behat Steps reads. Use it to look up what a setting does, its type and its default. [Configuration](configuration.md) explains where each kind of setting goes and how they combine. The options a trait declares are on that trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md).

[docs.php](../../../docs.php) generates the tables of extension options and tags from the source, and CI fails when a table doesn't match it. [Documentation](../contributing/documentation.md#generated-pages) covers regenerating them.

## Extension options

These options sit under the `behat_steps` extension key of a profile, as [The `behat_steps` extension key](configuration.md#2-the-behat_steps-extension-key) shows. A nested option is a section in the configuration and a dotted path in the table:

[//]: # (START_EXTENSION_OPTIONS)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `backends` | map | `[]` | The backends a scenario can use, most preferred first. A step asks for a capability, and the first backend in the list that provides it does the work, so the list is both the allowlist and the precedence order. A bare entry names a registered backend, such as `drupal`, `drush` or `blackbox`. A `name: backend` entry gives the backend a name of its own, so each profile can map the same `@backend:name` tag to a different backend. A name holds only letters, digits, `_` and `-`, and appears once, whatever its case. An empty list, the default, allows every registered backend, in registration order.<pre><code>- drupal<br/>- remote: drush<br/>- blackbox<br/></code></pre> |
| `login_field` | string | `'name'` | The user property whose value the login steps type into the username field. Set it to `mail` for a site that signs users in by email, or to any other property the site accepts as a login. `text.username_field` holds the label of the field the value goes into. |
| `regions` | map | `[]` | The named regions the region steps look in, each mapped to a CSS selector. A step such as `I press the button :button in the region :region` takes the name as written, and a name the map doesn't hold fails the step.<pre><code>Header: "#header"<br/>Content: "#main .region-content"<br/>Right sidebar: "#sidebar-second"<br/></code></pre> |
| `text` | section | - | The paths and labels of the login and logout forms, for a site whose forms differ from the defaults. The section accepts keys it doesn't declare, so a misspelled key is ignored, and the key it was meant to set keeps its default.<pre><code>login_url: "/account/login"<br/>logout_url: "/account/logout"<br/>logout_confirm_url: "/account/logout/confirm"<br/>login: "Sign in"<br/>logout: "Sign out"<br/>password_field: "Enter your password"<br/>username_field: "Email address"<br/></code></pre> |
| `text.login_url` | string | `'/user'` | The path of the login form the login steps fill in and submit. |
| `text.logout_url` | string | `'/user/logout'` | The path the logout steps visit to log out. |
| `text.logout_confirm_url` | string | `'/user/logout/confirm'` | The path of the logout confirmation form. When the logout steps land on it, they press the button `text.logout` names. |
| `text.login` | string | `'Log in'` | The label of the login form's submit button. |
| `text.logout` | string | `'Log out'` | The text of the logout link, and the label of the button on the logout confirmation form. |
| `text.password_field` | string | `'Password'` | The label of the password field on the login form. |
| `text.username_field` | string | `'Username'` | The label of the username field on the login form. |
| `login_wait` | integer | `0` | The longest time, in seconds, the login steps wait for each sign that a login took effect: the browser leaving the login form, the page rendering and the logged-in selector matching. At `0`, the lowest value, they don't wait. In a JavaScript session, 2 waits last up to 10 seconds each even at `0`, or this value when it's larger: the one for the browser to leave the login form, and the one for the logged-in selector or the logout link to show on the homepage, the last place the login steps check. Each wait ends as soon as its sign appears. |
| `steps` | map | `[]` | The profile-wide defaults of the options the step traits declare, keyed by group and then by option name. A group takes the name of the trait that declares it, in snake case, so `JavascriptTrait` reads `javascript` and `BigPipeTrait` reads `big_pipe`. A group or an option that no registered context declares is ignored, so 1 profile can carry the defaults of every suite. A value of the wrong type fails the context that declares the option.<pre><code>javascript:<br/>  enabled: true<br/>  fail_on_errors: false<br/>wait:<br/>  ajax_timeout: 10<br/></code></pre> |
| `selectors` | section | - | The CSS selectors the login steps check pages against. The section accepts keys it doesn't declare, so a misspelled key is ignored, and the key it was meant to set keeps its default. |
| `selectors.login_form_selector` | string | `'form#user-login,form#user-login-form'` | The CSS selector of the login form. When the page at `text.login_url` shows it and `selectors.logged_in_selector` matches nothing, the login steps count nobody as logged in. |
| `selectors.logged_in_selector` | string | `'body.logged-in,body.user-logged-in'` | The CSS selector that matches only while a user is logged in, such as a class on `body`. The login steps wait for it to confirm a login took effect. |
| `blackbox` | section | - | The backend that drives the site through the browser only. It's always registered and takes no options, so this section changes nothing. It provides no capability, so a step that asks for one fails when no other backend in the scenario's order provides it. |
| `drupal` | section | - | The backend that bootstraps Drupal in the test process. It's registered only when this section is present, and it uses Mink's `base_url` as the site's URI. |
| `drupal.drupal_root` | string | required | The path to the Drupal root, the directory holding `index.php` and `core`. A relative path resolves against the directory Behat runs in. |
| `drupal.core` | string | - | The class of the Core the in-process backend builds, in place of the one this package ships for the Drupal major it detects. The class implements `CoreInterface`, and a class built on a shipped Core extends the Core for that major. |
| `drupal.field_handlers` | map | `[]` | The handler classes that turn a value a step gives a field into the value Drupal stores, keyed by field type. An entry adds a handler for a field type this package has none for, or replaces the shipped one. Each class implements `FieldHandlerInterface`.<pre><code>rating: Acme\Behat\Field\RatingHandler<br/>datetime: Acme\Behat\Field\FiscalDatetimeHandler<br/></code></pre> |
| `drupal.creation_aliases` | map | `[]` | The creation alias classes the backend registers after the ones it ships. An alias resolves a value a creation step gives an entity that isn't a field of it, such as `author` on a node. An entry adds an alias, or replaces the shipped one with the same name on the same entity type. Each class implements `PreCreateAliasInterface` or `PostCreateAliasInterface` and takes no constructor arguments.<pre><code>- Acme\Behat\Alias\OrganizationAlias<br/></code></pre> |
| `drush` | section | - | The backend that reaches the site by running Drush 13 or newer. It's registered only when this section is present, and the section needs `alias` or `root`. |
| `drush.alias` | string | - | The Drush site alias every command runs against, with or without the leading `@`. It takes precedence over `root` when both are set. |
| `drush.binary` | string | `'vendor/bin/drush'` | The path to the Drush binary. A relative path resolves against the directory Behat runs in, then its parent. A bare `drush` resolves through `COMPOSER_BIN_DIR`, then `vendor/bin/drush` in the directory Behat runs in, then the `PATH`. |
| `drush.root` | string | - | The Drupal root passed to Drush as `--root`, for a site Drush can't find on its own. The directory has to exist. A relative path resolves against the directory Behat runs in. |
| `drush.global_options` | string | - | The options added to every Drush command, such as `--uri=http://example.com`. The value is split on whitespace, so an option's value can't hold a space. |
| `drush.creation_aliases` | map | `[]` | The creation alias classes the backend registers after the ones it ships. The Drush backend creates only users, so it runs only the aliases for the `user` entity type. Each class implements `PreCreateAliasInterface` or `PostCreateAliasInterface` and takes no constructor arguments.<pre><code>- Acme\Behat\Alias\DepartmentAlias<br/></code></pre> |

[//]: # (END_EXTENSION_OPTIONS)

## Tags

A tag configures the scenario it's on, or every scenario of the feature when it's on the `Feature:` line. [Tags](configuration.md#3-tags) explains how a scenario's tags combine with its feature's.

A parametrized tag takes its value after a colon, never a hyphen: `@module:redirect`, not `@module-redirect`. A flag tag takes no value. 2 parametrized tags also work bare, with no colon: `@email` and `@accessibility`, as their rows describe. Any other parametrized tag without a value is ignored, except `@behat-steps-skip` and `@backend:`. Those 2 stop the whole run at that scenario's start, as they do for any value they can't resolve. The other tags never stop the run. A value one of them can't use either fails only its scenario, as with `@breakpoint:`, or falls back to a default, as with `@accessibility:`.

The following table lists every tag Behat Steps reads:

[//]: # (START_TAGS)

| Tag | Description |
| --- | --- |
| `@behat-steps-skip:VALUE` | Switches off every hook of the named trait, such as `EmailTrait`, for the scenario, and the trait's steps still work. On a trait that declares an `enabled` option, the tag sets it to `FALSE`. A value that isn't a trait name, a missing value or a trait that no context of the suite composes stops the whole run at that scenario's start. |
| `@behat-steps-entity-cleanup-skip:VALUE` | Keeps the entities of the named entity type, such as `node`, that the scenario's steps created, instead of deleting them after the scenario. Repeated tags keep several types. Users and roles that the user steps create are deleted either way. |
| `@backend:VALUE` | Moves the named backend to the front of the configured `backends` list for the scenario. Repeated tags promote several: the scenario's names go first, then the feature's, each group in the configured order, so the order the tags are written in doesn't matter. The tag reorders the list and never adds to it, so a name the list doesn't hold, or no name, stops the whole run at that scenario's start. |
| `@module:VALUE` | Installs the named module for the scenario, or uninstalls it when the name starts with `!`, as in `@module:!redirect`, and restores the module's original state afterwards. Uninstalling deletes the module's data, which the restore doesn't bring back. A module the codebase doesn't hold fails the scenario. |
| `@breakpoint:VALUE` | Resizes the browser window to the named breakpoint before the first step: `mobile_portrait`, `mobile_landscape`, `tablet_portrait`, `tablet_landscape`, `laptop`, `desktop` or a breakpoint a context's constructor adds through `responsiveSetBreakpoints()`. A scenario and its feature take 1 tag each, and the scenario's replaces the feature's. The scenario or its feature has to be `@javascript`, and an unknown name fails the scenario. |
| `@email:VALUE` | Collects the email the site sends during the scenario, by pointing the named key of `system.mail`'s `interface` map, such as `default`, at a test collector and restoring it afterwards. A bare `@email` collects the `default` key, and repeated tags collect several. |
| `@watchdog:VALUE` | Adds the named log type, such as `my_module`, to the types the Watchdog check reads, beside `php`, which it always reads. An entry of a tracked type at warning severity or worse fails the scenario, unless `@error` allows it. |
| `@disable-config-override:VALUE` | Asks the site to ignore the `settings.php` overrides of the named configuration object, by sending its name in an `X-Config-No-Override` header with each request of the scenario. The site has to read the header itself, and a Selenium2 session sends no request headers. |
| `@accessibility:VALUE` | Assesses every page the scenario visits with axe-core, and fails the scenario on the violations a threshold selects. `critical`, `serious`, `moderate` or `minor` fails on that impact or a worse one, `any` fails on every violation and `warning`, or `warn`, reports without failing. `strict` also fails the scenario on findings axe-core can't decide, whatever the threshold. A bare `@accessibility`, or a value outside these, uses the `accessibility.failure_threshold` option. The scenario has to be `@javascript`. |
| `@bigpipe` | Sets the `big_pipe_nojs` cookie before each step, so Drupal renders BigPipe placeholders on the server, for a browser driver that can't run JavaScript. A driver that runs JavaScript doesn't get the cookie. |
| `@disable-form-validation` | Adds `novalidate` to every form on the page after each step, so a scenario can submit values the browser's own validation would block. It acts only in a browser that runs JavaScript, since BrowserKit doesn't validate forms. |
| `@js-errors` | Lets a `@javascript` scenario pass with JavaScript errors, which otherwise fail it. The errors are still collected. Sets the `javascript.fail_on_errors` option to `FALSE`. |
| `@rest-no-cookies` | Sends the scenario's REST requests without the browser session's cookies, so a site authenticates them by their own headers, such as `Authorization`, and a logged-in session isn't asked for a CSRF token. Cookies a REST response sets are discarded, and the browser session keeps its own. Sets the `rest.send_cookies` option to `FALSE`. |
| `@download` | Empties and creates the directory the `file_download.temp_dir` option names before the scenario, and deletes it afterwards, so the download steps write into an empty directory. Anything already in that directory is deleted. |
| `@testmode` | Switches on the test mode of the Testmode module, from `drupal/testmode`, for the scenario, and switches it off afterwards. The module has to be enabled already, or the scenario fails at its start. |
| `@debug` | Prints every email the collector holds, with its subject, body and addresses, each time an email step reads it. It works only in an `@email` scenario. |
| `@error` | Lets the scenario pass when it logs errors to Watchdog, which otherwise fail it. The errors are still read and cleared, and a scenario that logs none passes too. Sets the `watchdog.fail_on_errors` option to `FALSE`. |

[//]: # (END_TAGS)

`@javascript` is Mink's own tag: Mink runs a scenario that carries it on Mink's JavaScript session, as [Browsers](browsers.md#how-a-scenario-picks-a-browser) explains.

## Environment variables

Behat Steps reads the following environment variables, and nothing else from the environment. Each one varies a run without changing any committed configuration:

| Variable | Read by | Effect |
| --- | --- | --- |
| `BEHAT_STEPS_DISABLE_CLEANUP` | `EntityLifecycleTrait`, `AuthTrait` | Set to `1`, `true`, `yes` or `on`, in any letter case, to keep the entities, users and roles a scenario created, instead of deleting them in the teardown. The logged-in user also stays logged in for the scenarios that follow. It's meant for inspecting the state a failing scenario left behind, not for CI. |
| `BEHAT_ACCESSIBILITY_PRINT` | `AccessibilityTrait` | Set to any non-empty value other than `0` to print a 1-line accessibility summary per page to the console. `false` turns it on too. |
| `COMPOSER_BIN_DIR` | `DrushBackend` | When `drush.binary` is the bare `drush`, makes the backend look for `drush` in this directory first, then in `vendor/bin` under the directory Behat runs in, then on the `PATH`. Composer reads the same variable to move its `bin-dir`. A `bin-dir` set in `composer.json` doesn't set the variable. |

`BEHAT_PARAMS` is Behat's own variable, not Behat Steps'. [Environment variables](configuration.md#4-environment-variables) explains which settings it can change.
