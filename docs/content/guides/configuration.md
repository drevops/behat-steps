---
title: Configuration
sidebar_position: 3
description: Behat Steps reads its configuration from suites and context arguments, the extension key, tags and environment variables. This page covers all 4 channels and lists every option and tag.
---

<!-- cspell:ignore bigpipe testmode -->

# Configuration

This page is the reference for everything you can set. Behat Steps reads its configuration through 4 channels, apart from the Mink settings it shares with Mink: `base_url`, `files_path` and each `browserkit_http` session's `http_client_parameters`. Each channel answers a different question, so they aren't interchangeable:

| Channel | Answers | Scope |
| --- | --- | --- |
| [Suites and context arguments](#1-suites-and-context-arguments) | Which scenarios run against which surface, with which steps | Per suite |
| [The `behat_steps` extension key](#2-the-behat_steps-extension-key) | How the package talks to the site | Per profile |
| [Tags](#3-tags) | What a single scenario opts into | Per scenario or feature |
| [Environment variables](#4-environment-variables) | What differs between one machine and another | Per run |

[docs.php](../../../docs.php) generates the tables in sections 2 and 3 from the source, and CI fails when they fall behind it. [Documentation](../contributing/documentation.md#generated-pages) covers regenerating them.

## 1. Suites and context arguments

### Suite per surface

A suite pairs a set of feature files with a set of contexts. The [quick start](../quick-start.md) uses 1 suite with your `FeatureContext` in it, and many projects never need another. When you test more than 1 surface, such as pages in a browser and an API, you can give each surface a suite of its own, and every suite registers the same `FeatureContext`:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\Context\MinkContext;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;

// The browser surface: what an editor or a visitor can do in a page.
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class)
  ->addContext(MinkContext::class);

// The API surface: responses, headers and payloads, with no browser.
$api = (new Suite('api'))
  ->withPaths('%paths.base%/tests/behat/features/api')
  ->addContext(FeatureContext::class);

// The specification surface: the few domain-language scenarios a
// stakeholder reads.
$spec = (new Suite('spec'))
  ->withPaths('%paths.base%/tests/behat/features/spec')
  ->addContext(FeatureContext::class);

$profile = (new Profile('default', ['autoload' => ['%paths.base%/tests/behat/bootstrap']]))
  ->withSuite($ui)
  ->withSuite($api)
  ->withSuite($spec)
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
  ]));

return (new Config())->withProfile($profile);
```

Run 1 surface with `vendor/bin/behat --suite=api`, or all of them with a bare `vendor/bin/behat`.

The layout gives you 3 things:

- The spec suite and the regression suites do different jobs. The spec suite holds domain-language scenarios written over your own step definitions, and the regression suites hold broad scenarios written in the shipped steps. [Scenario styles](scenario-styles.md) explains why both exist.
- You can exclude a slow surface without touching the others. Accessibility, visual and `@javascript` scenarios sit behind their own suite or a tag filter, so the fast suites stay fast.
- A surface can offer only the steps that work on it. Register a narrower context there instead of `FeatureContext`, [composed from `WebRawContext`](contexts.md#compose-your-own-context): an API suite built on `RestTrait`, `JsonTrait` and `ResponseTrait` never loads a trait that expects a rendered page. A step that can't work on a surface then isn't defined on it, so when a scenario uses it there, Behat reports the step as undefined instead of running it into a confusing failure.

### Context constructor arguments

The extension key in section 2 is read once per profile. Pass a value that has to differ between suites as a context constructor argument instead.

`WebRawContext` declares 1 argument of its own, `config`, and every shipped context and every context of yours inherits it without redeclaring a constructor. It carries the [trait options](#trait-options) that differ between 2 suites of the same profile:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class, [
    'config' => ['wait' => ['ajax_timeout' => 10]],
  ]);
```

Behat passes a context only the arguments on its own registration, so `config` goes on the registration of `FeatureContext`, the class your suite registers. An argument registered for `DrupalContext` or `WebContext` never reaches a `FeatureContext` that extends it.

A group names the trait that declares it, so a context accepts only the groups its own traits bring. `WaitTrait` and `JavascriptTrait` are web traits, so `WebContext` takes `wait` and `javascript`, among others. `DrupalContext` extends it and adds the groups of the Drupal traits, such as `watchdog`, `big_pipe`, `cache`, `queue` and `email`, and a `FeatureContext` that extends `DrupalContext` takes them all. A group that no trait of the context declares, counting the traits of the classes it extends, fails the context at construction, with an error that names the groups it accepts.

A context can take arguments of its own too. It declares them in its constructor and forwards `config` to the parent. A default keeps an argument optional, so a suite that doesn't pass it, like the `api` suite in the first example, still builds the context:

```php
class FeatureContext extends DrupalContext {

  public function __construct(
    protected ?string $fixtures_path = NULL,
    array $config = [],
  ) {
    parent::__construct($config);
  }

}
```

Behat matches the arguments to the constructor by name, so the array keys are the parameter names, and the suite passes both:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class, [
    'fixtures_path' => '%paths.base%/tests/behat/fixtures',
    'config' => ['javascript' => ['fail_on_errors' => FALSE]],
  ]);
```

Behat 4 reads PHP configuration only, from `behat.php` or, when there's no `behat.php`, from `behat.dist.php`. Behat 3 reads the same settings from `behat.yml`. Prefer `behat.php`: both majors accept it, and it's the only format Behat 4 accepts.

While your project is still on Behat 3, the YAML form of the 2 preceding suites is:

```yaml
default:
  suites:
    ui:
      paths: ['%paths.base%/tests/behat/features/ui']
      contexts:
        - FeatureContext:
            fixtures_path: '%paths.base%/tests/behat/fixtures'
    api:
      paths: ['%paths.base%/tests/behat/features/api']
      contexts: [FeatureContext]
  extensions:
    DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension:
      drupal:
        drupal_root: web
```

[behat.dist.php](../../../behat.dist.php) in this repository sets every extension option, plus the trait options most projects set, as a reference.

## 2. The `behat_steps` extension key

Settings under this key configure how the package reaches the site: which backends a scenario may resolve and in what order, how each of them connects, what the login form looks like and which CSS selector each named region resolves to. They're read once per profile.

HTTP connection settings are the exception. Certificate checks, a proxy or DNS pinning live on Mink's `browserkit_http` session as `http_client_parameters`, and requests that steps send from PHP, such as a file download, share them with the page. [HTTP clients](http-clients.md) covers how.

For example, this profile allows 2 backends, tells the Drupal backend where Drupal lives and names 1 region:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['drupal', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'regions' => ['content' => '#content'],
]));
```

The following table lists every option. A nested option is a section in the configuration and a dotted path in the table.

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

### Backend resolution

5 authorities decide which backend runs a step, and each decides exactly 1 thing:

| Authority | Decides | Where |
| --- | --- | --- |
| Extension | Which backends exist at all, and in what order | `behat_steps: { backends: [...] }` |
| Profile | Which backends exist per environment | `behat -p remote` |
| Scenario tag | Preference among the backends that exist | `@backend:NAME` |
| Step | Which capability it needs | `backendFor(X::class)` |
| Capability interface | Which backends are even eligible | `instanceof` during the walk |

The `backends` list is both the allow-list and the precedence order. Each entry names a backend the extension registers; a keyed entry gives the backend a name of its own, so the same feature file can run against a different backend in another profile. In this profile, `api` names the backend registered as `acme-jsonapi`:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'blackbox' => NULL,
  'drupal' => ['drupal_root' => 'web'],
  'backends' => [
    'drupal',
    'api' => 'acme-jsonapi',
    'blackbox',
  ],
]));
```

That aliasing lets a profile swap the implementation behind a name without touching any Gherkin:

```php
// Default profile: "api" is the in-process backend.
'backends' => ['api' => 'drupal', 'blackbox'],

// "behat -p staging": "api" is the real HTTP API, and Drupal is unreachable.
'backends' => ['api' => 'acme-jsonapi', 'blackbox'],
```

A name is unique, and it holds only letters, digits, `_` and `-` so that `@backend:NAME` is a valid tag. A name the extension doesn't register fails the container build. A configuration that declares no list gets every registered backend, in registration order.

`acme-jsonapi` here stands for a backend of your own, which [A backend of your own](../extending/backend.md#a-backend-of-your-own) shows how to write and register.

Scoping a run to a restricted backend set is a profile's job, not a suite's: the package reads nothing from a Behat suite's settings.

At step time the resolution is:

1. Start from the configured order.
2. Move every name a `@backend:` tag promotes to the front: the names on the scenario line first, then those on the feature line. Each group keeps the configured order, so the order the tags are written in never changes the result.
3. Walk that order and return the first backend implementing the capability the step asked for, bootstrapping only that one.

For example, with the `backends` list `drupal`, `api` and `blackbox` from the first example in this section, this scenario promotes `api`:

```gherkin
@backend:api
Scenario: Content created over the public API is immediately visible
  Given the following "article" content exists:
    | title      |
    | Lab report |
  When I visit "/articles"
  Then I should see "Lab report"
```

The order becomes `api, drupal, blackbox`, and the content step resolves to `api`, the `acme-jsonapi` backend. A cache step in the same scenario still resolves to `drupal`, because `acme-jsonapi` implements no cache capability. Promotion only affects the capabilities the promoted backend provides.

Repeated tags promote their names in the configured order: `@backend:blackbox @backend:api` and `@backend:api @backend:blackbox` both give `api, blackbox, drupal`. A `Scenario Outline` row counts the outline's tags and its `Examples:` table's tags as 1 scenario line.

`@backend:NAME` reorders the configured list; it never adds to it. A name outside the list is an error at scenario start, so a typo can't silently run the wrong backend, and no tag can hand a Drupal backend to a `smoke` profile that lists only `blackbox`. When no backend in the order provides the capability a step asked for, the step fails with an `UnsupportedBackendActionException` naming the capability and the resolved order.

The backends also take classes of your own through the `drupal.field_handlers`, `drupal.creation_aliases`, `drush.creation_aliases` and `drupal.core` options, which [Extending the backend layer](../extending/backend.md) covers.

### Trait options

Every configurable trait declares its own options, and the `steps` section holds their profile-wide defaults. A group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe` and `FileDownloadTrait` reads `file_download`. A trait's page under [Web steps](../web-steps/README.md) or [Drupal steps](../drupal-steps/README.md) lists its options beside its steps. This profile sets defaults for 5 groups:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => ['drupal_root' => 'web'],

  'steps' => [
    'javascript' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
    'watchdog' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
    'big_pipe' => ['wait_timeout' => 5000],
    'wait' => ['ajax_timeout' => 10],
    'modal' => ['selectors' => ['.ui-dialog', '[role="dialog"]']],
  ],
]));
```

An option resolves through 5 layers, each overriding the one before it:

1. The default the trait declares.
2. `behat_steps.steps`, for the whole profile.
3. The context's `config` argument, for 1 context.
4. A tag on the feature.
5. A tag on the scenario.

The 2 levels, `steps` and a context's `config`, differ in how strictly they're read, because they know different things:

- `steps` is permissive. A group there may name a trait that only 1 of the registered contexts composes, so a group or an option a context can't serve is ignored, and 1 profile can carry the defaults of every suite.
- A context's `config` is strict. The context knows which traits it composes, so an unknown group, an unknown option or a value of the wrong type is an error naming what the context accepts.

2 options recur, and they're deliberately separate switches:

| Option | Effect |
| --- | --- |
| `enabled` | The trait's hooks do nothing at all. Equivalent to `@behat-steps-skip:TRAIT` on every scenario. |
| `fail_on_errors` | The trait still collects, and doesn't fail the scenario on what it found. |

`fail_on_errors` only covers what a trait collected. A trait whose prerequisites don't hold, like `WatchdogTrait` on a site without the core `dblog` module, fails the scenario at its start whatever `fail_on_errors` says, so `enabled` is the switch there. [Switch a trait off](usage.md#switch-a-trait-off) has the details.

Where a trait offers a `<trait>Get<Noun>()` method for an option, overriding it in your context sits outside these layers and replaces the resolution entirely. Use that override for anything the configuration can't express.

[Extending the step vocabulary](../extending/steps.md#declare-options-for-a-trait-of-your-own) covers declaring options for a trait of your own, and [Extending the extension layer](../extending/extension.md#replace-option-resolution) covers replacing the resolution itself.

## 3. Tags

A tag configures 1 scenario or 1 feature. A parametrized tag takes its value after a colon, never a hyphen: `@module:redirect`, not `@module-redirect`. A flag tag takes no value.

A tag with nothing after the colon is ignored, except `@behat-steps-skip:` and `@backend:`, which fail at scenario start like any other value they can't resolve. `@module:` also switches its value off with a `!`: `@module:!redirect`. A scenario can carry several tags:

```gherkin
@module:redirect @behat-steps-skip:WatchdogTrait
Scenario: Editor publishes a page
```

A tag on the `Feature:` line applies to every scenario in that feature. Where a scenario and its feature carry the same kind of tag, they combine as follows:

- A flag takes effect from either line.
- `@behat-steps-skip:VALUE`, `@email:VALUE`, `@watchdog:VALUE`, `@disable-config-override:VALUE` and `@behat-steps-entity-cleanup-skip:VALUE` add up across both lines.
- `@module:VALUE` for the same module, `@breakpoint:VALUE` and an `@accessibility:VALUE` threshold take the scenario's value over the feature's, while `@accessibility:strict` applies from either line.
- `@backend:VALUE` promotes from both lines, the scenario's names ahead of the feature's, as [backend resolution](#backend-resolution) describes.

The following table lists every tag:

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

`@javascript` is Mink's own tag: it selects the browser session. [Backend resolution](#backend-resolution) and the `@backend:NAME` tag decide which backend a step runs against.

## 4. Environment variables

The package reads the following environment variables, and nothing else from the environment. Each one varies a run without changing any committed configuration:

| Variable | Read by | Effect |
| --- | --- | --- |
| `BEHAT_STEPS_DISABLE_CLEANUP` | `EntityLifecycleTrait`, `AuthTrait` | Set to `1`, `true`, `yes` or `on` to keep the entities, users and roles a scenario created, instead of deleting them in the teardown. The logged-in user also stays logged in for the scenarios that follow. For inspecting the state a failing scenario left behind, not for CI. |
| `BEHAT_ACCESSIBILITY_PRINT` | `AccessibilityTrait` | Set to any non-empty value other than `0` to print a one-line accessibility summary per page to the console. |
| `COMPOSER_BIN_DIR` | `DrushBackend` | When `drush.binary` is the bare `drush`, names the directory the Drush binary is resolved from, before the backend falls back to `vendor/bin/drush` under the working directory and then to `drush` on the `PATH`. Composer sets it inside its own scripts. |

`BEHAT_PARAMS` is Behat's own override channel and applies here as it does to any extension: it carries a JSON object merged over the loaded configuration, which is the usual way to point `base_url` or a browser driver's `api_url` somewhere else for 1 run.
