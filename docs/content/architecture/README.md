---
title: Architecture
description: How Behat Steps works inside, from the 3 layers and the flow of a run to a trait taken apart, the public API and what extends what.
---

# Architecture

These pages show how Behat Steps works inside. Read them when you write a trait, extend the library or want to know why a step behaves the way it does. Writing scenarios needs none of them. The section holds the following pages:

- [Logical architecture](logical-architecture.md) shows the parts and which way they depend on each other.
- 3 pages cover 1 layer each:
  - [Backend layer](backend-layer.md): the layer that reaches the site and picks a backend for every step by capability.
  - [Extension layer](extension-layer.md): the layer that plugs the library into Behat and Mink and gives traits their options.
  - [Steps layer](steps-layer.md): the layer that holds every step and helper.
- [Data flow](data-flow.md) shows what runs when, and whose code it is.
- [Anatomy of a trait](anatomy-of-a-trait.md) takes a trait apart, line by line.
- [Public API and versioning](versioning.md) lists what semantic versioning covers, and the exceptions a failing step throws.
- [Class inheritance](class-inheritance.md) shows what extends what.

An AI agent maintains these pages and their diagrams through the `update-architecture-docs` skill in [.claude/skills/update-architecture-docs/SKILL.md](../../../.claude/skills/update-architecture-docs/SKILL.md). The agent draws the diagrams with the [drawio-skill](https://github.com/Agents365-ai/drawio-skill). Everything here is traced from the source code, and where the pages and the code disagree, the code wins.

To look something up, use the references:

- [Web steps](../web-steps/README.md) and [Drupal steps](../drupal-steps/README.md) list the steps.
- [HELPERS.md](../../../HELPERS.md) lists the helpers behind them.
- [Configuration reference](../guides/configuration-reference.md) lists the options and tags.

## Diagram sources

Each diagram has a draw.io source and 2 exported SVGs, 1 for the light color mode and 1 for the dark. The diagram's page embeds both, and the site shows the SVG that matches the color mode. All 3 files are committed, so a reviewer sees the source diff and the rendered result. The following table lists every diagram of this section:

| Diagram | Page | Source | Renders |
| --- | --- | --- | --- |
| Logical architecture | [Logical architecture](logical-architecture.md) | `logical-architecture.drawio` | [light](logical-architecture.svg), [dark](logical-architecture.dark.svg) |
| Backend selection | [Backend layer](backend-layer.md) | `backend-selection.drawio` | [light](backend-selection.svg), [dark](backend-selection.dark.svg) |
| Extension layer | [Extension layer](extension-layer.md) | `extension-layer.drawio` | [light](extension-layer.svg), [dark](extension-layer.dark.svg) |
| Steps layer | [Steps layer](steps-layer.md) | `steps-layer.drawio` | [light](steps-layer.svg), [dark](steps-layer.dark.svg) |
| Data flow | [Data flow](data-flow.md) | `data-flow.drawio` | [light](data-flow.svg), [dark](data-flow.dark.svg) |
| Class inheritance | [Class inheritance](class-inheritance.md) | `class-inheritance.drawio` | [light](class-inheritance.svg), [dark](class-inheritance.dark.svg) |

The 3 diagrams of the [HTTP clients](../guides/http-clients.md) guide sit beside the guide in `docs/content/guides/`, drawn and checked the same way.

To change a diagram by hand:

1. Open its source in [draw.io](https://www.drawio.com/), edit it and save it.
2. From the repository root, export every SVG again:

   ```bash
   php scripts/draw-diagram.php --export \
     docs/content/architecture docs/content/guides
   ```

Each SVG records the hash of the source it came from. `php scripts/draw-diagram.php --check docs/content/architecture docs/content/guides` exits with an error when an SVG was exported from an older source or a diagram lacks either of its SVGs. The unit suite runs the same check.

After a structural change, ask the AI agent to "update architecture docs". Structural changes include a new layer, namespace, backend or capability. A change to an inheritance tree or the scenario lifecycle counts too. The agent re-traces the affected diagrams and pages from the current code and redraws them through the `update-architecture-docs` skill.
