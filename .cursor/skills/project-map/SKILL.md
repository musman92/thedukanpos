---
name: project-map
description: >-
  Loads the DukanPOS project map for orientation. Use when starting a new chat,
  onboarding to the codebase, or when the user types /project-map or asks for
  project structure, conventions, or where code lives.
disable-model-invocation: true
---

# Project map

## Instructions

1. Read [`docs/project-map.md`](../../../docs/project-map.md) fully before searching or editing.
2. Summarize for the user in 3–6 bullets: product, stack, where their task likely lives, and any matching convention (e.g. Brands template, nav naming).
3. If the task is a catalog master, also read `.cursor/rules/catalog-master-module.mdc`.
4. Do not dump the map verbatim unless asked.

## Example

User: `/project-map` or “Where do orders live?”

Agent: Reads the map, then points to `SaleService`, `OrderController`, `Admin/Orders/`, and related flows.
