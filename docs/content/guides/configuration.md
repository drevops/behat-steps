---
title: Configuration
sidebar_position: 3
description: Where a Behat Steps setting goes, from suites and context arguments to the extension key, tags and the environment, and how settings in different places combine.
---

# Configuration

This page explains where each Behat Steps setting goes and how settings in different places combine. It's for you when you set up a suite or change what a trait does. To look up a setting you already know, see [Configuration reference](configuration-reference.md), which lists every extension option, tag and environment variable with its type and default.

Behat Steps reads its configuration through 4 channels, apart from the Mink settings it shares with Mink: `base_url`, `files_path` and each `browserkit_http` session's `http_client_parameters`. Each channel answers a different question:

| Channel | Answers | Scope |
| --- | --- | --- |
| [Suites and context arguments](#1-suites-and-context-arguments) | Which scenarios run against which surface, with which steps | Per suite |
| [The `behat_steps` extension key](#2-the-behat_steps-extension-key) | How the package talks to the site | Per profile |
| [Tags](#3-tags) | What a single scenario opts into | Per scenario or feature |
| [Environment variables](#4-environment-variables) | What differs between one machine and another | Per run |

## 1. Suites and context arguments

This section covers the 2 settings that live per suite: which feature files run with which contexts, and the constructor arguments a context takes.

### Suite per surface

A suite pairs a set of feature files with a set of contexts. The [quick start](../quick-start.md) uses 1 suite with your `FeatureContext` in it, and many projects never need another. When you test more than 1 surface, such as pages in a browser and an API, you can give each surface a suite of its own. Every suite registers the same `FeatureContext`:

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
- A surface can offer only the steps that work on it. Register a narrower context there instead of `FeatureContext`, [composed from `WebRawContext`](contexts.md#compose-your-own-context): an API suite built on `RestTrait`, `JsonTrait` and `ResponseTrait` never loads a trait that expects a rendered page. Behat then reports a step that can't work on the surface as undefined, instead of running it into a confusing failure.

### Context constructor arguments

The extension key in section 2 is read once per profile. Pass a value that has to differ between suites as a context constructor argument instead.

`WebRawContext` declares 1 argument of its own, `config`, and every shipped context and every context of yours inherits it without redeclaring a constructor. `config` carries the [trait options](#trait-options) that differ between 2 suites of the same profile:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class, [
    'config' => ['wait' => ['ajax_timeout' => 10]],
  ]);
```

Behat passes a context only the arguments on its own registration, so `config` goes on the registration of `FeatureContext`, the class your suite registers. An argument registered for `DrupalContext` or `WebContext` never reaches a `FeatureContext` that extends it.

Each key of `config` is a group of options, named after the trait that declares it. A context accepts only the groups of its own traits, counting the traits of the classes it extends. Any other group fails the context at construction, with an error that names the groups it accepts.

`WebContext` takes the groups of the web traits, such as `wait` and `javascript`. `DrupalContext` adds the groups of the Drupal traits, such as `watchdog`, `big_pipe`, `cache`, `queue` and `email`, so a `FeatureContext` that extends it takes them all.

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

Behat matches the arguments to the constructor by name, so the array keys are the parameter names. This `ui` suite passes both:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(FeatureContext::class, [
    'fixtures_path' => '%paths.base%/tests/behat/fixtures',
    'config' => ['javascript' => ['fail_on_errors' => FALSE]],
  ]);
```

Behat 4 reads PHP configuration only, from `behat.php` or, when there's no `behat.php`, from `behat.dist.php`. Behat 3 reads the same settings from `behat.yml`. Prefer `behat.php`: both majors accept it, and it's the only format Behat 4 accepts.

On Behat 3, the `ui` suite with its `fixtures_path` argument and the `api` suite look like this in `behat.yml`. A `config` argument goes beside `fixtures_path` the same way:

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

[behat.dist.php](../../../behat.dist.php) in the Behat Steps repository sets every extension option, plus the trait options most projects set, as a reference.

## 2. The `behat_steps` extension key

Settings under this key configure how the package reaches the site. They're read once per profile and set the following:

- Which backends a scenario can resolve, and in what order
- How each backend connects
- What the login form looks like
- Which CSS selector each named region resolves to
- What each trait option defaults to

`behat_steps` is the name the extension gives its settings, and you don't write it anywhere. In `behat.php`, the settings are the array you pass to `new Extension()` with `BehatStepsExtension::class`, as the following examples show. In `behat.yml`, they're the map under the extension's class name.

HTTP connection settings are the exception. Certificate checks, a proxy or DNS pinning live on Mink's `browserkit_http` session as `http_client_parameters`, and requests that steps send from PHP, such as a file download, share them with the page. [HTTP clients](http-clients.md) covers how.

For example, this profile allows 2 backends, tells the Drupal backend where Drupal lives and names 1 region:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['drupal', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'regions' => ['content' => '#content'],
]));
```

[Extension options](configuration-reference.md#extension-options) lists every option under the key, with its type and default.

### Backend resolution

5 authorities decide which backend runs a step, and each decides exactly 1 thing:

| Authority | Decides | Where |
| --- | --- | --- |
| Extension | Which backends exist at all, and in what order | `'backends' => [...]` |
| Profile | Which backends exist per environment | `behat -p remote` |
| Scenario tag | Preference among the backends that exist | `@backend:NAME` |
| Step | Which capability it needs | `backendFor(X::class)` |
| Capability interface | Which backends are even eligible | `instanceof` during the walk |

The `backends` list is both the allowlist and the precedence order. Each entry names a backend the extension registers. A keyed entry gives the backend a name of its own, so the same feature file can run against a different backend in another profile. In this profile, `api` names the backend registered as `acme-jsonapi`:

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

`'blackbox' => NULL` changes nothing, since the blackbox backend is always registered. Aliasing a backend as `api` lets a profile swap the implementation behind the name without touching any Gherkin:

```php
// Default profile: "api" is the in-process backend.
'backends' => ['api' => 'drupal', 'blackbox'],

// "behat -p staging": "api" is the real HTTP API, and Drupal is unreachable.
'backends' => ['api' => 'acme-jsonapi', 'blackbox'],
```

A name is unique, and it holds only letters, digits, `_` and `-` so that `@backend:NAME` is a valid tag. A name the extension doesn't register fails the container build. A configuration that declares no list gets every registered backend, in registration order.

`acme-jsonapi` here stands for a backend of your own. [A backend of your own](../extending/backend.md#a-backend-of-your-own) shows how to write and register one.

To restrict a run to fewer backends, give it a profile of its own. The package reads nothing from a suite's own settings, such as its `paths`.

At step time the resolution is:

1. Start from the configured order.
2. Move every name a `@backend:` tag promotes to the front: the names on the scenario line first, then those on the feature line. Each group keeps the configured order, so the order the tags are written in never changes the result.
3. Walk that order and return the first backend implementing the capability the step asked for, bootstrapping only that one.

For example, with the `backends` list `drupal`, `api` and `blackbox` that the `acme-jsonapi` example sets, this scenario promotes `api`:

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

`@backend:NAME` takes a name as the `backends` list writes it: the key of a keyed entry, such as `api`, or the bare entry itself. The tag reorders the configured list and never adds to it. A name outside the list stops the whole run at that scenario's start, so a typo can't silently run the wrong backend. No tag can hand a Drupal backend to a `smoke` profile that lists only `blackbox`. When no backend in the order provides the capability a step asked for, the step fails with an `UnsupportedBackendActionException` naming the capability and the resolved order.

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

`wait.ajax_timeout` is in seconds and `big_pipe.wait_timeout` in milliseconds, as each trait's page lists.

An option resolves through 5 layers, each overriding the one before it:

1. The default the trait declares.
2. `behat_steps.steps`, for the whole profile.
3. The context's `config` argument, for 1 context.
4. A tag on the feature.
5. A tag on the scenario.

These tags set an option for their scenario or feature:

- `@behat-steps-skip:TRAIT` sets the `enabled` option of the trait it names to `FALSE`. It names the trait by its class name, such as `WatchdogTrait`, not by its group.
- `@js-errors` sets `javascript.fail_on_errors` to `FALSE`.
- `@error` sets `watchdog.fail_on_errors` to `FALSE`.
- An `@accessibility:VALUE` threshold sets `accessibility.failure_threshold`, and `@accessibility:strict` sets `accessibility.fail_on_incomplete` to `TRUE`.

Apart from the `@accessibility:` tags, these tags only switch an option off, so no tag switches back on what the profile or a context's `config` switched off.

The 2 levels, `steps` and a context's `config`, differ in how strictly they're read:

- `steps` is permissive. A context ignores a group or an option there that it doesn't declare, so 1 profile can carry the defaults of every suite. A value of the wrong type for an option the context does declare still fails it.
- A context's `config` is strict. It's checked against the traits that context composes, so an unknown group, an unknown option or a value of the wrong type is an error naming what the context accepts.

2 options recur, and they're deliberately separate switches:

| Option | Effect of `FALSE` |
| --- | --- |
| `enabled` | The trait's hooks switch off, and its steps still work. Equivalent to `@behat-steps-skip:TRAIT` on every scenario. |
| `fail_on_errors` | The trait still collects the errors it checks for, such as JavaScript errors or Watchdog entries, and doesn't fail the scenario on them. |

`fail_on_errors` only covers what a trait collected. A trait whose prerequisites don't hold fails the scenario at its start, whatever `fail_on_errors` is set to. `WatchdogTrait` does that on a site without the core `dblog` module, so switch it off with `enabled` there. [Switch a trait off](usage.md#switch-a-trait-off) has the details.

Where a trait offers a `<trait>Get<Noun>()` method for an option, overriding it in your context sits outside these layers and replaces the resolution entirely. Use that override for anything the configuration can't express.

[Extending the step vocabulary](../extending/steps.md#declare-options-for-a-trait-of-your-own) covers declaring options for a trait of your own, and [Extending the extension layer](../extending/extension.md#replace-option-resolution) covers replacing the resolution itself.

## 3. Tags

A tag configures 1 scenario or 1 feature. A tag's value follows a colon, and a scenario can carry several tags:

```gherkin
@module:redirect @behat-steps-skip:WatchdogTrait
Scenario: Editor publishes a page
```

A tag on the `Feature:` line applies to every scenario in that feature. Where a scenario and its feature carry the same kind of tag, they combine as follows:

- A flag tag, such as `@download`, takes no value and takes effect from either line.
- `@behat-steps-skip:VALUE`, `@email:VALUE`, `@watchdog:VALUE`, `@disable-config-override:VALUE` and `@behat-steps-entity-cleanup-skip:VALUE` add up across both lines.
- `@module:VALUE` for the same module, `@breakpoint:VALUE` and an `@accessibility:VALUE` threshold take the scenario's value over the feature's. `@accessibility:strict` applies from either line. A `!` before a module's name uninstalls it. A scenario's `@module:!redirect` wins over its feature's `@module:redirect`, so the scenario runs with the module uninstalled.
- `@backend:VALUE` promotes from both lines, the scenario's names ahead of the feature's, as [backend resolution](#backend-resolution) describes.

[Tags](configuration-reference.md#tags) lists every tag and what it does, with the rules for writing a tag's value.

`@javascript` is Mink's own tag: Mink runs a scenario that carries it on Mink's JavaScript session. [Backend resolution](#backend-resolution) and the `@backend:NAME` tag decide which backend a step runs against.

## 4. Environment variables

An environment variable changes 1 run, or 1 machine, without changing any committed configuration. Behat Steps reads 3 of its own. `BEHAT_STEPS_DISABLE_CLEANUP`, for example, keeps what a scenario created so you can inspect it. [Environment variables](configuration-reference.md#environment-variables) lists all 3.

`BEHAT_PARAMS` is Behat's own variable. It holds a JSON object of settings that Behat merges with your configuration file. It can only add a setting the file leaves out, because Behat loads `BEHAT_PARAMS` first and the file after it.

For example, when the `chrome` session in `behat.php` has no `api_url`, this run supplies one:

```bash
BEHAT_PARAMS='{"extensions": {"Behat\\MinkExtension": {"sessions":
  {"chrome": {"chrome": {"api_url": "http://chrome:9222"}}}}}}' \
  vendor/bin/behat
```

The extension key can be Mink's short name, `Behat\MinkExtension`, or the class name `behat.php` registers, `Behat\MinkExtension\ServiceContainer\MinkExtension`. Both reach the same settings, and JSON writes each backslash in them twice.

To change a value the file does set, for 1 run, add a profile that overrides it and pick that profile with `-p`. A profile other than `default` starts from `default`, so it names only what differs. [Installation](../installation.md#watch-the-browser-in-a-headed-chrome) shows one for a headed Chrome.
