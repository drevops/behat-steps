# HTTP clients

Most steps read the page the Mink session holds. A few send HTTP requests of their own from PHP: `FileDownloadTrait` downloads a file, `MetatagTrait` fetches the hreflang alternates of a page, `AccessibilityTrait` fetches its engine script, and `RestTrait` sends a request whose response becomes the page. Every one of those requests goes through 1 of 3 clients. This page covers which is which, where their settings come from, and how to change them.

3 terms stay apart throughout, and [CONTRIBUTING.md](../CONTRIBUTING.md#backend-browser-driver-and-http-client) holds them for the whole codebase:

- **Backend** - Drupal, Drush or Blackbox under `src/Backend`, reached with `backendFor()`. Nothing on this page touches them.
- **Browser driver** - Mink's driver behind the session (BrowserKit, Selenium2 or Chrome), reached through an adapter with `browserDriverFor()`.
- **HTTP client** - one of the 3 clients below. None of them is a backend or a browser driver.

## The 3 clients

| Client | `WebRawContext` method | What it starts with | Its response | Used by |
| --- | --- | --- | --- | --- |
| Page | `httpPageClient()` | The Mink session's own browser, with its cookies, history and credentials | Becomes the page | `RestTrait` |
| Detached | `httpDetachedClient()` | A fresh browser holding the scenario's identity: the session's cookies, the headers steps set, the basic-auth credentials | Stays inside the step | `FileDownloadTrait`, and the hreflang return-link check in `MetatagTrait` |
| Bare | `httpBareClient()` | A fresh browser with an empty cookie jar | Stays inside the step | The engine fetch in `AccessibilityTrait` |

All 3 return BrowserKit's `AbstractBrowser`, so the calls are the same whichever one a step picks. Only the instance differs, and the state it starts with:

```php
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

- **The next steps assert against it** - use the page client. Only a BrowserKit session has one. Under Selenium or Chrome, `httpPageClient()` throws `UnsupportedDriverActionException`, because a real browser has no PHP client to lend out, and a REST step can't run there anyway.
- **The request is the visitor's own, but its response isn't the page** - a file behind a login, another page to compare against - use the detached client.
- **The request isn't the visitor's at all** - a script from a CDN, a third-party service - use the bare client.

`request()` takes no connection options, so a step that needs a different timeout asks for a client built with it, as the bare example does. Those options merge over the site's.

## Where the settings come from

Connection settings come from 1 place, and per-trait settings from another:

```
 Mink: sessions.<name>.browserkit_http          behat_steps: steps
       http_client_parameters                     file_download.timeout
               │                                  accessibility.fetch_timeout
               ▼                                             │
 BrowserKitFactory::buildDriver()                            │
        │                   │                                │
        ▼                   ▼                                │
 Mink's own client   behat_steps.http_client                 │
 settings for        1 transport; settings                   │
 every host          for base_url only                       │
        │                   ├──────────────────┐             │
        ▼                   ▼                  ▼             │
   page client       detached client      bare client        │
   HttpBrowser       HttpBrowser          HttpBrowser        │
   in the session    + scenario           settings only      │
                     identity                                │
                            ▲                  ▲             │
                            └──────────────────┴─────────────┘
                              per-trait options, per request
```

**Connection settings** are `http_client_parameters` on a `browserkit_http` session, the Symfony HttpClient options Mink documents: `verify_peer`, `proxy`, `resolve`, `headers`, `timeout` and the rest. `BehatStepsExtension` registers its own factory for the `browserkit_http` browser driver with Mink's extension. The factory extends Mink's own, keeps its configuration tree, and receives each session's options when Mink builds that session. It builds the session exactly as Mink does, so the page client applies the options to every host. It also records them, and `BehatStepsExtension` turns them into 1 shared transport, the `behat_steps.http_client` service, which the detached and bare clients send through. So a setting that lets the page load also lets the download through.

To a project this looks like stock Mink: it registers Mink's own extension and `BehatStepsExtension`, nothing else, and never sees the factory.

**Per-trait settings** stay in the per-trait options under `behat_steps: steps`, applied to each request a step makes:

| Option | Default | Applies to |
| --- | --- | --- |
| `file_download.timeout` | `120` | How long each download may wait for data, in seconds |
| `accessibility.fetch_timeout` | `10` | How long each attempt at fetching the engine may wait for data, in seconds |
| `accessibility.fetch_attempts` | `3` | How many times the engine fetch is attempted |

Both timeouts are idle timeouts: a slow download that keeps receiving data never hits one. The hreflang return-link check waits up to 30 seconds for data from each alternate.

### The rules the clients follow

- **The page client applies the settings to every host, as stock Mink does.** It's Mink's own client, so a redirect to a single sign-on provider on another domain, or a visit to another domain of a multi-domain site, gets the same `verify_peer`, `proxy` and `headers` as the site.
- **The detached and bare clients apply them to the site only.** The options reach a request whose scheme, host and port match `base_url`. Credentials in the URL and the letter case of the host don't matter. A request to any other host gets Symfony's defaults, so `verify_peer: false` for a staging site never relaxes certificate checks on a CDN, and a header meant for the site never reaches a third party.
- **A proxy that every host needs belongs in the environment.** Symfony reads `HTTPS_PROXY`, `HTTP_PROXY` and `NO_PROXY` for any request that carries no `proxy` option, so a suite that reaches the internet only through a proxy sets those, and the accessibility engine's fetch from the CDN goes through it too.
- **A step's own options win over the site's.** A download from the site waits `file_download.timeout` for data even when the session declares a `timeout` of its own.
- **A `base_url` without a host** applies the options to every request.
- **A suite without a `browserkit_http` session** sends every request with Symfony's defaults.
- **Every `browserkit_http` session declares the same options.** Mink lets a suite declare any number of named sessions, and more than 1 can use the `browserkit_http` browser driver. The detached and bare clients send with 1 set of options, so the sessions can't carry different ones, and Behat stops at startup rather than pick 1 set silently. Key order doesn't count as a difference. This suite fails:

  ```php
  'sessions' => [
    'default' => ['browserkit_http' => ['http_client_parameters' => ['verify_peer' => FALSE]]],
    'slow' => ['browserkit_http' => ['http_client_parameters' => ['timeout' => 60]]],
    'selenium2' => ['selenium2' => []],
  ],
  ```

  ```
  The 2 "browserkit_http" sessions declare different "http_client_parameters". Behat Steps sends its own requests with 1 set of connection options, so give every "browserkit_http" session the same options.
  ```

## What the detached client carries

The detached client acts as the scenario's visitor, so it carries whatever makes the site recognise that visitor:

- **Cookies** - the session's cookies, read through the browser driver's cookie capability, so they come across under BrowserKit, Selenium and Chrome alike. Before the scenario opens a page there are none.
- **Headers the steps set** - the ones from `the REST header :name has the value :value`, the `X-Config-No-Override` header `ConfigOverrideTrait` sets, and the header the credentials step records.
- **Basic-auth credentials** - from the `base_url` userinfo (`https://user:pass@staging.example.com`), or from `the basic authentication has the username :username and the password :password`. That step hands the credentials to the browser driver, which has no way to give them back, so it also records them as an `Authorization` header for the detached client. An `Authorization` header a step set takes precedence over the credentials.

None of it leaks off the site. Headers and credentials go to the site's own host only, and cookies go only to the host they were read from and its subdomains. A download link or an hreflang alternate on another domain gets the request with none of the scenario's identity. The cost is that a multi-domain site whose other domains sit behind the same basic auth still returns 401 there.

## JavaScript scenarios: the sidecar client

In a `@javascript` scenario, 2 things talk to the site:

```
@javascript scenario
  visits, clicks, AJAX  ──► browser ────────────► site   set by the browser's capabilities
  file download,        ──► detached client ────► site   set by http_client_parameters
  hreflang fetch            (PHP, the sidecar)
```

The browser makes the page's requests, and its capabilities or flags configure them. Some steps still send requests from PHP while the scenario runs, because doing them in the browser would either put the result out of PHP's reach or navigate away from the page. `FileDownloadTrait` is the clearest case: it reads the browser's cookies and downloads the file itself.

Those PHP requests take their settings from `http_client_parameters`, which only a `browserkit_http` session holds. So a JavaScript-only suite testing a site with a self-signed certificate needs 2 settings: `acceptInsecureCerts` in the browser's capabilities so the browser accepts the certificate, and `verify_peer: false` so PHP does. The second one has nowhere to live unless the suite declares a `browserkit_http` session, even one no scenario ever browses with. Mink builds every session it's given, and building it is what hands the options over:

```php
->withExtension(new Extension(MinkExtension::class, [
  'base_url' => 'https://staging.example.com',
  'default_session' => 'selenium2',
  'javascript_session' => 'selenium2',
  'sessions' => [
    'selenium2' => ['selenium2' => ['wd_host' => 'http://localhost:4444/wd/hub']],
    'browserkit_http' => ['browserkit_http' => ['http_client_parameters' => ['verify_peer' => FALSE, 'verify_host' => FALSE]]],
  ],
]))
```

The Selenium2 browser driver doesn't need that session; the requests steps send from PHP do.

## Where content lives

A step never has to guess which content it reads, because each kind lives in its own place:

- **The page** lives in the Mink session: the browser's DOM under JavaScript, the last response under BrowserKit. Page visits and the page client replace it, and page assertions read it.
- **The downloaded file** lives in `FileDownloadTrait`'s own state. Only the next download replaces it, and only the `the downloaded file ...` steps read it. A download never writes to the session.
- **Transient fetches** - the hreflang alternates and the accessibility engine - live only inside the step that fetches them, and are thrown away when it ends.

So a scenario can mix page steps and download steps freely:

```
When I visit "/reports"                       writes the page (browser DOM)
And I download the file from the link "CSV"   writes the downloaded file
Then the downloaded file should contain:      reads the downloaded file
And I should see "Reports"                    reads the page, still /reports
```

One side effect to know about: `Then the response status code should be 200` after a download still reads the page's response. The download step checks its own status and fails on 400 or above.

## How a step reaches each client

The page client is a browser capability, and the other 2 come from a factory:

```
WebRawContext
  │
  ├─ httpPageClient() ───────► browserDriverFor(HttpClientCapabilityInterface)
  │                              └─► the session's HttpBrowser, through the BrowserKit adapter
  │                                  (Selenium2, Chrome: UnsupportedDriverActionException)
  │
  ├─ httpDetachedClient() ───► HttpClientFactoryInterface::createDetached(HttpIdentity)
  │                              └─► a fresh HttpBrowser holding the scenario's identity
  │
  └─ httpBareClient() ───────► HttpClientFactoryInterface::createBare()
                                 └─► a fresh HttpBrowser with an empty cookie jar
```

- **The page client is a browser capability.** Whether one exists depends on the browser driver, so `httpPageClient()` resolves `HttpClientCapabilityInterface` through `browserDriverFor()`, like any other capability. The BrowserKit adapter provides it; the Selenium2 and Chrome adapters don't.
- **The detached and bare clients come from a factory.** They exist under every browser driver, so there's nothing to resolve for each one. `HttpClientFactoryInterface` builds them, and the context initializer injects the `behat_steps.http_client_factory` service into `WebRawContext`, the same way it injects the option resolver factory. The scenario's identity reaches the factory as an `HttpIdentity` value object: the cookies, the URL they were read from, the headers and the credentials.

```php
interface HttpClientFactoryInterface {

  public function createBare(array $options = []): AbstractBrowser;

  public function createDetached(HttpIdentity $identity, array $options = []): AbstractBrowser;

  public function withTransport(callable $decorator): static;

}
```

Traits never see the factory. They call 1 of the 3 methods, so the choice is visible where it's made. A context Behat hasn't initialized, such as one built by hand in a unit test, builds a standalone factory on first use, which applies no connection options.

## Changing it

From lightest to heaviest.

### Configuration: a staging site with a self-signed certificate behind Docker's DNS

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

### A context override: a flaky staging server

Decorate the transport in the context, and every detached and bare client it builds retries a failed request:

```php
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeatureContext extends DrupalContext {

  public function getHttpClientFactory(): HttpClientFactoryInterface {
    return parent::getHttpClientFactory()->withTransport(static fn(HttpClientInterface $transport): HttpClientInterface => new RetryableHttpClient($transport));
  }

}
```

That covers downloads, the hreflang check and the engine fetch for every trait in that context. `RetryableHttpClient` retries a request that failed in transit or came back with a status such as 429 or 503, up to 3 times. The decorator receives the shared transport, so the site's settings still apply. Page requests aren't affected, because Mink builds the page client.

### Writing a step of your own

A domain step that needs a response without disturbing the page asks for the detached client, exactly as the shipped traits do. The browser starts with no history, so it takes an absolute URL:

```php
#[Then('the sitemap should list the path :path')]
public function sitemapAssertListsPath(string $path): void {
  $browser = $this->httpDetachedClient();
  $browser->request('GET', $this->locatePath('/sitemap.xml'));
  $content = $browser->getInternalResponse()->getContent();

  if (!str_contains($content, $this->locatePath($path))) {
    throw new ExpectationException(sprintf('The sitemap does not list the path "%s".', $path), $this->getSession()->getDriver());
  }
}
```

The page stays where the scenario left it, and the sitemap is fetched as the scenario's visitor, so it works behind basic auth or a login.

### Package authors: every context at once

2 heavier extension points are meant for package authors rather than projects.

**Replace `behat_steps.http_client_factory`** from a Behat extension to change every context at once, for example with a tracing client for debugging or a mock client for offline tests. The service takes the shared transport and the base URL, so extending `HttpClientFactory` keeps the rules above:

```php
public function process(ContainerBuilder $container): void {
  $container->getDefinition('behat_steps.http_client_factory')->setClass(AcmeHttpClientFactory::class);
}
```

**Register a browser adapter** that implements `HttpClientCapabilityInterface` to give another browser driver a page client:

```php
$this->getBrowserCapabilityResolver()->registerAdapter(AcmeDriverAdapter::class);
```

[Capabilities of the browser driver](../MIGRATION.md#capabilities-of-the-browser-driver) covers writing the adapter itself.
