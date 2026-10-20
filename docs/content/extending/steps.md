---
title: Extending the step vocabulary
sidebar_label: Step vocabulary
sidebar_position: 1
description: Write steps of your own on the helpers, override a trait's seams and a context's HTTP client, declare options for a trait of your own and follow the rules Behat applies when a context composes or overrides steps and hooks.
---

# Extending the step vocabulary

The step vocabulary is the step and helper traits under `src/Steps` and the 3 contexts that compose them: `WebRawContext`, `WebContext` and `DrupalContext`. [Usage](../guides/usage.md) explains how to use it as it ships. This page covers what you add on top:

- Steps of your own.
- Overrides for what no option reaches.
- Options for a trait of your own.
- The rules Behat applies when your context composes or overrides what it inherits.

You'll need it when a flow deserves a step in your project's own words, or a trait needs a value its options can't give it.

## Writing a step of your own

A step of your own is a method with a Behat step attribute on a context of your own, built on the same helpers the shipped steps call. [HELPERS.md](../../../HELPERS.md) lists every one of them, and the [quick start](../quick-start.md#7-add-a-step-of-your-own) shows where the class lives and how Behat finds it. [Contexts](../guides/contexts.md#your-own-steps-in-traits-of-your-own) shows how to group your steps into traits of your own, and [Anatomy of a trait](../architecture/anatomy-of-a-trait.md) walks through a trait with options and hooks.

If you write your own Drupal steps, compose the helper trait for the concern they touch. `EntityLifecycleTrait` brings entity creation and the `AfterScenario` pass that removes what was created:

```php
<?php

use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\WebRawContext;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;

class SpecContext extends WebRawContext {

  use EntityLifecycleTrait;

  #[When('I publish a page titled :title')]
  public function publish(string $title): void {
    $this->entityLifecycleCreateNode(new EntityStub('node', 'page', [
      'title' => $title,
    ]));
  }

}
```

`WebRawContext` also composes 3 of the web helper traits (`LastStepTrait`, `RequestHeadersTrait` and `StringTrait`), so your own step definitions reach them on `$this`. Composing 1 of those helper traits in a step trait as well shares the same state rather than duplicating it.

### Register what it creates

The `entityLifecycleCreate*()` helpers register each entity they create, so the end-of-scenario pass in [Automatic cleanup](../guides/usage.md#automatic-cleanup) deletes it. An entity created through a capability directly isn't registered until the step does it.

A step of your own registers what it created, passing the stub the capability returned and the key its entity type stores the ID under, `mid` for a media item:

```php
$stub = new EntityStub('media', 'document', ['name' => 'Annual report']);
$created = $this->backendFor(ContentCapabilityInterface::class)
  ->createEntity($stub);
$this->entityLifecycleRegister($created, 'mid');
```

### A step that sends its own request

[HTTP clients](../guides/http-clients.md) covers the 3 clients a step sends its own requests through.

A domain step that needs a response without disturbing the page uses the detached client, exactly as the shipped traits do. The browser starts with no history, so it takes an absolute URL:

```php
#[Then('the sitemap should list the path :path')]
public function sitemapAssertListsPath(string $path): void {
  $browser = $this->httpDetachedClient();
  $browser->request('GET', $this->locatePath('/sitemap.xml'));
  $content = $browser->getInternalResponse()->getContent();

  if (!str_contains($content, $this->locatePath($path))) {
    $message = sprintf('The sitemap does not list the path "%s".', $path);
    throw new ExpectationException($message, $this->getSession()->getDriver());
  }
}
```

The page stays where the scenario left it, and the sitemap is fetched as the scenario's visitor, so it works behind basic auth or a login.

## Go further than the options

An option covers the values a trait expects to vary. For anything else, override the trait's own seam in your context:

```php
class UiContext extends WebRawContext {

  use ModalTrait;

  public function modalGetSelectors(): array {
    return ['.acme-dialog'];
  }

}
```

If your suite registers `WebContext`, override the same seam by extending it and redeclaring the method, without repeating any Behat attribute:

```php
class AcmeWebContext extends WebContext {

  public function modalGetSelectors(): array {
    return ['.acme-dialog'];
  }

}
```

An override replaces the resolution entirely, so the option, the `steps` default and any tag no longer reach that value. [HELPERS.md](../../../HELPERS.md) lists every seam a context can override.

### A context override: a flaky staging server

A context also overrides how its traits send their own requests. `getHttpClientFactory()` returns the factory behind the detached and bare clients, as [How a step reaches each client](../guides/http-clients.md#how-a-step-reaches-each-client) describes.

Decorate the transport in the context, and every detached and bare client it builds retries a failed request:

```php
use DrevOps\BehatSteps\DrupalContext;
use DrevOps\BehatSteps\Extension\Http\HttpClientFactoryInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeatureContext extends DrupalContext {

  public function getHttpClientFactory(): HttpClientFactoryInterface {
    return parent::getHttpClientFactory()->withTransport(
      static fn(HttpClientInterface $transport): HttpClientInterface
        => new RetryableHttpClient($transport),
    );
  }

}
```

That covers downloads, the hreflang check and the engine fetch for every trait in that context. The decorator receives the shared transport, so the site's settings still apply. Page requests aren't affected, because Mink builds the page client.

`RetryableHttpClient` retries a request up to 3 times. Its default strategy retries a request that failed in transit only for an idempotent method such as `GET`, but it retries a 429 or 503 response whatever the method, so a `POST` a step sends can reach the server twice. For a context whose steps send writes, pass `RetryableHttpClient` a `GenericRetryStrategy` that limits 429 and 503 to `GenericRetryStrategy::IDEMPOTENT_METHODS`.

## Declare options for a trait of your own

A configurable trait of your own declares each option as a `DrevOps\BehatSteps\Extension\Config\Option` returned from a `<prefix>ConfigSchema()` method, where `<prefix>` is the prefix the trait's other members carry:

```php
protected function acmeConfigSchema(): array {
  return [
    new Option(
      'enabled',
      default: TRUE,
      description: 'Whether the Acme hook runs.',
    ),
    new Option(
      'wait_timeout',
      default: 5000,
      description: 'How long to wait, in milliseconds.',
      tags: ['slow' => 30000],
    ),
  ];
}
```

The declared default carries the option's type, and your trait reads it at that type with `getOptionBool()`, `getOptionInt()`, `getOptionFloat()`, `getOptionString()` or `getOptionArray()`. A default of `NULL` names no type, and `getOption()` reads it.

Behat Steps finds the method by its name, so the trait needs no registration. The group is the method's prefix in snake case: `acmeConfigSchema()` declares the `acme` group, and its options resolve through the layers [Trait options](../guides/configuration.md#trait-options) lists, from `steps` and the context's `config` to the tags.

## The rules that govern composition

Behat's attribute inheritance works in opposite directions for a trait that's composed and a class that's extended:

| What the project does | Result |
| --- | --- |
| Registers 2 contexts composing the same trait | Fatal, `RedundantStepException` |
| A subclass re-composes a trait its parent already has | Fatal, every step in it registers twice |
| A subclass overrides an inherited step, no attribute | Works, the subclass body runs, the step text is inherited |
| A subclass overrides an inherited step and repeats the attribute | Fatal, the pattern registers twice |
| A subclass overrides an inherited step with a different pattern | Both patterns register, both run the subclass body |
| A composed trait's method is redeclared without the attribute | The step is silently removed |
| A composed trait is aliased `as protected` and the attribute redeclared | Works, wraps the original |
| A subclass overrides an inherited hook, no attribute | Works, the subclass body runs |
| A subclass overrides an inherited hook and repeats the attribute | Runs twice, silently |

The last row is the least discoverable: `HookRepository` doesn't deduplicate, so a hook whose attribute is repeated on the override fires once for the parent declaration and once for the subclass one. Override an inherited hook without repeating its attribute.

## Where to next

- [Scenario styles](../guides/scenario-styles.md) explains when a domain step pays off, and when the shipped vocabulary is the better fit.
- [Extending the extension layer](extension.md) covers replacing the services behind these helpers for every context at once.
- [Extending the backend layer](backend.md) covers the field handlers and creation aliases an entity you create passes through.
