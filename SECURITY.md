# Security Policy

## Supported versions

Security fixes are released for the `3.x` series only. Earlier series are available on the `1.x` and `2.x` branches but receive no security updates, so upgrade to `3.x` to stay supported. [MIGRATION.md](MIGRATION.md) documents the step changes between `2.x` and `3.x`.

| Version | Supported |
| --- | --- |
| 3.x | Yes |
| 2.x | No |
| 1.x | No |

## Reporting a vulnerability

Report vulnerabilities privately. Do not open a public issue, because an issue discloses the problem before a fix is available.

Use either channel:

- [Report a vulnerability](https://github.com/drevops/behat-steps/security/advisories/new) through GitHub Security Advisories. This requires a signed-in GitHub account, because the link redirects to sign-in otherwise.
- Email the maintainer at alex@drevops.com. Use this channel if you do not have a GitHub account or prefer not to use one.

Include the affected version, the steps or configuration needed to reproduce the problem, and the impact you expect. A minimal Behat scenario that demonstrates the issue is the most useful reproduction.

You will receive an acknowledgement of the report. If the report is accepted, the fix and the advisory are published together, and the release notes credit the reporter unless anonymity is requested. If the report is declined, you will be told why.

## Scope

This package is a test-only library: consumers install it under `require-dev` and run it against their own test environments, never in production. Reports are most relevant where a step definition could expose data outside the test environment, execute unintended commands on the machine running the suite, or write outside the paths a test controls.

Findings in the fixture Drupal site under `build/`, in the test suite, or in development dependencies are not vulnerabilities in this library. Report those as ordinary issues.
