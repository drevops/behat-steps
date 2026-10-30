---
title: HTTP clients
sidebar_position: 7
description: The 4 clients a step sends its own requests through, where their settings come from and how to change them.
---

<!-- cspell:ignore Metatag -->

# HTTP clients

Most steps read the page the Mink session holds. A few send HTTP requests of their own from PHP:

- `FileDownloadTrait` downloads a file.
- `MetatagTrait` fetches the hreflang alternates of a page.
- `AccessibilityTrait` fetches its engine script.
- `RestTrait` sends API requests, and the JSON and XML steps read their responses.

Every one of those requests goes through 1 of 4 clients. This page covers which is which, where their settings come from and how to change them.

Read it when your site needs special connection settings, such as a self-signed certificate or a proxy. Read it also when you write a step that sends a request of its own.

This page uses 3 terms that [CONTRIBUTING.md](../../../CONTRIBUTING.md#backend-browser-driver-and-http-client) defines for the whole codebase:

- A backend is Drupal, Drush or Blackbox under `src/Backend`, reached with `backendFor()`. This page doesn't cover backends.
- A browser driver is Mink's driver behind the session (BrowserKit, Selenium2 or Chrome), reached through an adapter with `browserDriverFor()`. [Browsers](browsers.md) compares the 3.
- An HTTP client is 1 of the 4 clients that [The 4 clients](#the-4-clients) describes. None of them is a backend or a browser driver.

## The 4 clients

Each client starts from a different state, and its response goes to a different place:

| Client | `WebRawContext` method | What it starts with | Its response | Used by |
| --- | --- | --- | --- | --- |
| REST | `httpRestSession()` | A Mink session of its own, with the basic-auth credentials and no cookies | Stays in the REST session for the REST, JSON and XML steps | `RestTrait`, `JsonTrait`, `XmlTrait` |
| Page | `httpPageClient()` | The Mink session's own browser, with its cookies, history and credentials | Becomes the page | None |
| Detached | `httpDetachedClient()` | A fresh browser holding the scenario's identity: the session's cookies, the headers steps set, the basic-auth credentials | Stays inside the step | `FileDownloadTrait`, and the hreflang return-link check in `MetatagTrait` |
| Bare | `httpBareClient()` | A fresh browser with an empty cookie jar | Stays inside the step | The engine fetch in `AccessibilityTrait` |

`httpRestSession()` returns a Mink session, and the other 3 return BrowserKit's `AbstractBrowser`. Only the instance differs, and the state it starts with:

```php
// REST: the response stays in the REST session.
$this->httpRestSession()->getDriver()->getClient()->request('POST', $url, [], [], $server, $body);

// Page: the response becomes the page.
$this->httpPageClient()->request('POST', $url, [], [], $server, $body);

// Detached: a separate instance carrying the scenario's identity.
$browser = $this->httpDetachedClient();
$browser->request('GET', $url);
$content = $browser->getInternalResponse()->getContent();

// Bare: a blank instance with the site settings only.
$browser = $this->httpBareClient(['timeout' => $timeout]);
$browser->request('GET', $engine_url);
```

Picking one comes down to what the response is for:

- If API steps assert against it, use the REST session. It runs beside the browser under every browser driver, so the browser keeps its page and its login.
- If it has to become the browser's page, use the page client. Only a BrowserKit session has one. Under Selenium or Chrome, `httpPageClient()` throws `UnsupportedDriverActionException`, because a real browser has no PHP client to lend out.
- If the request is the visitor's own but its response isn't the page, such as a file behind a login or another page to compare against, use the detached client.
- If the request isn't the visitor's at all, such as a script from a CDN or a third-party service, use the bare client.

`request()` takes no connection options, so a step that needs a different timeout asks for a client built with it, as the bare example does. Those options merge over the site's.

## Where the settings come from

Connection settings come from 1 place, and per-trait settings from another. Select the diagram to zoom in.

[![Where HTTP client settings come from: the browserkit_http session's options reach the page client through Mink and the detached and bare clients through 1 shared transport, and the steps options reach the detached and bare clients per request](http-client-settings.svg#gh-light-mode-only)](http-client-settings.svg)
[![Where HTTP client settings come from: the browserkit_http session's options reach the page client through Mink and the detached and bare clients through 1 shared transport, and the steps options reach the detached and bare clients per request](http-client-settings.dark.svg#gh-dark-mode-only)](http-client-settings.dark.svg)

Connection settings are `http_client_parameters` on a `browserkit_http` session. They're the Symfony HttpClient options Mink documents, such as `verify_peer`, `proxy`, `resolve`, `headers` and `timeout`. `BehatStepsExtension` registers its own factory for the `browserkit_http` browser driver with Mink's extension. The factory extends Mink's own and keeps its configuration tree. It receives each session's options when Mink builds that session. Because it builds the session exactly as Mink does, the page client applies the options to every host.

The factory also records the options. `BehatStepsExtension` turns them into 1 shared transport, the `behat_steps.http_client` service, and the REST session and the detached and bare clients send through it. So a setting that lets the page load also lets the download and the API request through.

To give the REST session settings of its own, such as for an API on another host, declare a Mink session named `rest`. The REST, JSON and XML steps then use it as declared, and the shared transport ignores its options:

```php
'default_session' => 'default',
'sessions' => [
  'default' => ['browserkit_http' => [
    'http_client_parameters' => ['verify_peer' => FALSE],
  ]],
  'rest' => ['browserkit_http' => [
    'http_client_parameters' => ['timeout' => 60, 'auth_basic' => ['api', 'secret']],
  ]],
],
```

Set `default_session`, because Mink otherwise picks the first session as the browser's. A declared `rest` session gets nothing from `base_url` credentials, so put any basic-auth credentials it needs in its own options.

From your side this looks like stock Mink: you register only Mink's own extension and `BehatStepsExtension`, and never see the factory.

Per-trait settings stay in the per-trait options under `behat_steps: steps`, applied to each request a step makes:

| Option | Default | Applies to |
| --- | --- | --- |
| `file_download.timeout` | `120` | How long each download may wait for data, in seconds |
| `accessibility.fetch_timeout` | `10` | How long each attempt at fetching the engine may wait for data, in seconds |
| `accessibility.fetch_attempts` | `3` | How many times the engine fetch is attempted |

Both timeouts are idle timeouts: a slow download that keeps receiving data never hits one. The hreflang return-link check waits up to 30 seconds for data from each alternate.

### The rules the clients follow

- The page client applies the settings to every host, as stock Mink does. It's Mink's own client, so a redirect to a single sign-on provider on another domain gets the same `verify_peer`, `proxy` and `headers` as the site. So does a visit to another domain of a multi-domain site.
- The detached and bare clients apply them to the site only. The options reach a request whose scheme, host and port match `base_url`. Credentials in the URL and the letter case of the host don't matter. A request to any other host gets Symfony's defaults plus the step's own options, such as its timeout. So `verify_peer: false` for a staging site never relaxes certificate checks on a CDN, and a header meant for the site never reaches a third party.
- A proxy that every host needs belongs in the environment. Symfony reads `HTTPS_PROXY`, `HTTP_PROXY` and `NO_PROXY` for any request that carries no `proxy` option. When a suite reaches the internet only through a proxy, set those variables, and the accessibility engine's fetch from the CDN goes through that proxy too.
- A step's own options win over the site's. A download from the site waits `file_download.timeout` for data even when the session declares a `timeout` of its own.
- A `base_url` without a host applies the options to every request, and the detached client sends no step headers or credentials.
- A suite without a `browserkit_http` session sends every request with Symfony's defaults and the step's own options.
- Every `browserkit_http` session but `rest` has to declare the same options. Mink lets a suite declare any number of named sessions, and more than 1 can use the `browserkit_http` browser driver. The detached and bare clients send with 1 set of options, so the sessions can't carry different ones. Behat stops at startup when they do, so no set of options wins silently. Key order doesn't count as a difference. This suite fails:

  ```php
  'sessions' => [
    'default' => ['browserkit_http' => [
      'http_client_parameters' => ['verify_peer' => FALSE],
    ]],
    'slow' => ['browserkit_http' => [
      'http_client_parameters' => ['timeout' => 60],
    ]],
    'selenium2' => ['selenium2' => []],
  ],
  ```

  Behat stops with this error:

  ```text
  The 2 "browserkit_http" sessions declare different "http_client_parameters". Behat Steps sends its own requests with 1 set of connection options, so give every "browserkit_http" session the same options, apart from a session named "rest".
  ```

## What the detached client carries

The detached client acts as the scenario's visitor, so it carries whatever makes the site recognize that visitor:

- The session's cookies, read through the browser driver's cookie capability, so they come across under BrowserKit, Selenium and Chrome alike. Before the scenario opens a page there are none.
- The headers the steps set: the `X-Config-No-Override` header `ConfigOverrideTrait` sets, and the header the credentials step records.
- Basic-auth credentials, from the `base_url` userinfo (`https://user:pass@staging.example.com`) or from `I am using basic authentication with the username :username and the password :password`. That step hands the credentials to the browser driver, which has no way to give them back. So the step also records them as an `Authorization` header for the detached client. An `Authorization` header a step set takes precedence over the credentials.

None of it leaks off the site: headers and credentials go to the site's own host only, and cookies go only to the host they were read from and its subdomains. A download link or an hreflang alternate on another domain gets the request with none of the scenario's identity. The cost is that a multi-domain site whose other domains sit behind the same basic auth still returns 401 there.

## What the REST session carries

The REST session calls an API, so it carries no browser cookies, and the browser keeps its login. Each REST request carries these, and the first one wins when 2 set the `Authorization` header:

1. The headers from `the REST header :name has the value :value`, sent with REST requests only.
2. The headers the other steps set, such as the `Authorization` header the credentials step records and the `X-Config-No-Override` header.
3. The basic-auth credentials from the `base_url` userinfo, sent to the site's own host only. A declared `rest` session sends its own `auth_basic` option instead.

A request carries 1 `Authorization` header. A site behind basic auth whose API also wants `Authorization: Bearer ...` gets only the bearer token, so the basic-auth layer has to let the API paths through.

## JavaScript scenarios: the sidecar client

In a `@javascript` scenario, 2 things talk to the site:

[![The sidecar client: in a JavaScript scenario, visits, clicks and AJAX go through the browser, while a file download or the hreflang check goes through the detached client in PHP](http-client-sidecar.svg#gh-light-mode-only)](http-client-sidecar.svg)
[![The sidecar client: in a JavaScript scenario, visits, clicks and AJAX go through the browser, while a file download or the hreflang check goes through the detached client in PHP](http-client-sidecar.dark.svg#gh-dark-mode-only)](http-client-sidecar.dark.svg)

The browser makes the page's requests, and its capabilities or flags configure them. Some steps still send requests from PHP while the scenario runs, because doing them in the browser would either put the result out of PHP's reach or navigate away from the page. `FileDownloadTrait` is the clearest case: it reads the browser's cookies and downloads the file itself.

Those PHP requests take their settings from `http_client_parameters`, which only a `browserkit_http` session holds. So a JavaScript-only suite testing a site with a self-signed certificate needs 2 settings: `acceptInsecureCerts` in the browser's capabilities so the browser accepts the certificate, and `verify_peer: false` so PHP does. `verify_peer: false` goes on a `browserkit_http` session, so declare one, even if no scenario browses with it. Mink builds every session it's given, and building the session hands the options over:

```php
->withExtension(new Extension(MinkExtension::class, [
  'base_url' => 'https://staging.example.com',
  'default_session' => 'selenium2',
  'javascript_session' => 'selenium2',
  'sessions' => [
    'selenium2' => ['selenium2' => [
      'wd_host' => 'http://localhost:4444/wd/hub',
    ]],
    'browserkit_http' => ['browserkit_http' => ['http_client_parameters' => [
      'verify_peer' => FALSE,
      'verify_host' => FALSE,
    ]]],
  ],
]))
```

The Selenium2 browser driver doesn't need that session; the requests that steps send from PHP do.

## Where content lives

Each kind of content lives in its own place:

- The page lives in the Mink session: the browser's DOM under JavaScript, the last response under BrowserKit. Page visits and the page client replace it, and page assertions read it.
- The API response lives in the REST session. Only the next REST request replaces it, and only the REST, JSON and XML steps read it. A REST request never touches the page.
- The downloaded file lives in `FileDownloadTrait`'s own state. Only the next download replaces it, and only the `the downloaded file ...` steps read it. A download never writes to the session.
- Transient fetches (the hreflang alternates and the accessibility engine) live only inside the step that fetches them, and are thrown away when it ends.

So a scenario can mix page steps and download steps freely:

```text
When I visit "/reports"                       writes the page (browser DOM)
And I download the file from the link "CSV"   writes the downloaded file
Then the downloaded file should contain:      reads the downloaded file
And I should see "Reports"                    reads the page, still /reports
```

After a download, `Then the response status code should be 200` still reads the page's response. The download step checks its own status and fails on 400 or above.

## How a step reaches each client

The page client is a browser capability, the REST session is a Mink session, and the other 2 come from a factory:

[![How a step reaches each HTTP client: httpPageClient() resolves a browser capability that only BrowserKit provides, while httpDetachedClient() and httpBareClient() ask the HTTP client factory for a fresh browser](http-client-methods.svg#gh-light-mode-only)](http-client-methods.svg)
[![How a step reaches each HTTP client: httpPageClient() resolves a browser capability that only BrowserKit provides, while httpDetachedClient() and httpBareClient() ask the HTTP client factory for a fresh browser](http-client-methods.dark.svg#gh-dark-mode-only)](http-client-methods.dark.svg)

- Whether a page client exists depends on the browser driver, so `httpPageClient()` resolves `HttpClientCapabilityInterface` through `browserDriverFor()`, like any other capability. The BrowserKit adapter provides it; the Selenium2 and Chrome adapters don't.
- `httpRestSession()` returns the Mink session named `rest`. When the configuration declares none, the first call builds 1 from `createDetached()` with no cookies and registers it with Mink, which resets it before each scenario.
- The detached and bare clients exist under every browser driver, so there's nothing to resolve for each one. `HttpClientFactoryInterface` builds them, and the context initializer injects the `behat_steps.http_client_factory` service into `WebRawContext`, the same way it injects the option resolver factory. The scenario's identity reaches the factory as an `HttpIdentity` value object: the cookies, the URL they were read from, the headers and the credentials.

`HttpClientFactoryInterface` declares 3 methods:

```php
interface HttpClientFactoryInterface {

  public function createBare(array $options = []): AbstractBrowser;

  public function createDetached(
    HttpIdentity $identity,
    array $options = [],
  ): AbstractBrowser;

  public function withTransport(callable $decorator): static;

}
```

Traits never call the factory. They call `httpRestSession()`, `httpPageClient()`, `httpDetachedClient()` or `httpBareClient()`, so the choice is visible where it's made. A context Behat hasn't initialized, such as one built by hand in a unit test, builds a standalone factory on first use. That factory applies no connection options.

## Changing it

The following sections go from the lightest change to the heaviest.

### Configuration: a staging site with a self-signed certificate behind Docker's DNS

To reach a staging site whose certificate is self-signed and whose name only Docker resolves, accept the certificate and pin the name to the container's address on the `browserkit_http` session:

```php
->withExtension(new Extension(MinkExtension::class, [
  'base_url' => 'https://staging.example.com',
  'sessions' => [
    'default' => ['browserkit_http' => ['http_client_parameters' => [
      'verify_peer' => FALSE,
      'verify_host' => FALSE,
      'resolve' => ['staging.example.com' => '172.18.0.5'],
    ]]],
  ],
]))
```

The same in `behat.yml`, for Behat 3:

```yaml
default:
  extensions:
    Behat\MinkExtension:
      base_url: https://staging.example.com
      sessions:
        default:
          browserkit_http:
            http_client_parameters:
              verify_peer: false
              verify_host: false
              resolve:
                staging.example.com: 172.18.0.5
```

Page visits, downloads and the hreflang check all reach the container and accept its certificate. The accessibility engine's fetch from the CDN gets none of these settings, because the bare client applies them only to `base_url`.

### A per-trait option: an export that takes minutes to generate

A server that takes longer than 120 seconds to start sending an export fails the download. Raise the timeout for the whole profile:

```php
->withExtension(new Extension(BehatStepsExtension::class, [
  'steps' => ['file_download' => ['timeout' => 300]],
]))
```

To change it for 1 context only, pass it through that context's `config` argument, like every other [trait option](configuration.md#context-constructor-arguments).

### Changes in code

[A context that retries a flaky server](../extending/steps.md#a-context-override-a-flaky-staging-server) and [a step of your own that sends a request](../extending/steps.md#a-step-that-sends-its-own-request) are in Extending the step vocabulary, and [replacing the client factory for every context at once](../extending/extension.md#the-http-client-factory-and-browser-adapters) is in Extending the extension layer.
