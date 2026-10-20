---
title: Architecture
description: How Behat Steps works inside, from the 3 layers and the flow of a run to a trait taken apart, the public API and what extends what.
---

# Architecture

These pages show how Behat Steps works inside. You don't need any of it to write scenarios. It's here for when you write a trait or extend the library, or want to know why a step behaves the way it does. The section holds the following pages:

- [Logical architecture](logical-architecture.md) shows the parts and which way they depend on each other.
- 3 pages take 1 layer each:
  - The [Backend layer](backend-layer.md) reaches the site and picks a backend for every step by capability.
  - The [Extension layer](extension-layer.md) plugs the library into Behat and Mink and gives traits their options.
  - The [Steps layer](steps-layer.md) holds every step and helper.
- [Data flow](data-flow.md) shows what runs when, and whose code it is.
- [Anatomy of a trait](anatomy-of-a-trait.md) takes a trait apart, line by line.
- [Public API and versioning](versioning.md) lists what semantic versioning covers, and the exceptions a failing step throws.
- [Class inheritance](class-inheritance.md) shows what extends what.

An AI agent maintains these pages and their diagrams through the `update-architecture-docs` skill in [.claude/skills/update-architecture-docs/SKILL.md](../../../.claude/skills/update-architecture-docs/SKILL.md), which draws them with the [drawio-skill](https://github.com/Agents365-ai/drawio-skill). Everything here is traced from the source code, and where the 2 disagree, the code wins.

Looking for a reference instead? [Web steps](../web-steps/README.md) and [Drupal steps](../drupal-steps/README.md) list the steps, [HELPERS.md](../../../HELPERS.md) lists the helpers behind them and [Configuration reference](../guides/configuration-reference.md) lists the options and tags.

## Diagram sources

Each diagram is a draw.io source beside 2 SVGs its page embeds: 1 for the light color mode and 1 for the dark, so the site shows the one that matches. All 3 are committed, so a reviewer sees the source diff and the rendered result. The following table lists every diagram of this section:

| Diagram | Page | Source | Renders |
| --- | --- | --- | --- |
| Logical architecture | [Logical architecture](logical-architecture.md) | `logical-architecture.drawio` | [light](logical-architecture.svg), [dark](logical-architecture.dark.svg) |
| Backend selection | [Backend layer](backend-layer.md) | `backend-selection.drawio` | [light](backend-selection.svg), [dark](backend-selection.dark.svg) |
| Extension layer | [Extension layer](extension-layer.md) | `extension-layer.drawio` | [light](extension-layer.svg), [dark](extension-layer.dark.svg) |
| Steps layer | [Steps layer](steps-layer.md) | `steps-layer.drawio` | [light](steps-layer.svg), [dark](steps-layer.dark.svg) |
| Data flow | [Data flow](data-flow.md) | `data-flow.drawio` | [light](data-flow.svg), [dark](data-flow.dark.svg) |
| Class inheritance | [Class inheritance](class-inheritance.md) | `class-inheritance.drawio` | [light](class-inheritance.svg), [dark](class-inheritance.dark.svg) |

The 3 diagrams of the [HTTP clients](../guides/http-clients.md) guide live beside it in `guides/`, drawn and checked the same way.

To change a diagram by hand, open its source in [draw.io](https://www.drawio.com/), save it, then export every SVG again from the repository root:

```bash
php scripts/draw-diagram.php --export docs/content/architecture docs/content/guides
```

Each SVG records the hash of the source it came from, and `php scripts/draw-diagram.php --check docs/content/architecture docs/content/guides`, which the unit suite runs too, fails an SVG exported from an older source and a diagram missing either of its SVGs.

After a structural change, ask the AI agent to "update architecture docs". A new layer, namespace, backend or capability counts, and so does a change to an inheritance tree or to the scenario lifecycle. The agent re-traces the affected diagrams and pages from the current code and redraws them through the `update-architecture-docs` skill.
