---
title: "Example Page Title"
summary: "One-line description."
doc_type: "general"
status: "draft"
audience: [package-developer]
introduced_in: "v0.x"
requires: ["php:^8.3", "laravel:^10|^11|^12"]
tested_with: ["postgres", "mysql"]
tags: [actions, builders, dto]
related: [architecture/index.md]
last_reviewed: 2026-03-16
owner: "@team"
example_ready: false
---

---

Scope

---

State what this page covers, and one explicit non-goal.

## Readiness Checklist

- [ ] Scope includes at least one explicit non-goal.
- [ ] All links resolve to existing docs or code files.
- [ ] Example has been executed locally and `example_ready` is set correctly.
- [ ] Failure modes are documented before happy path details.
- [ ] Security/access implications are explicitly documented.
- [ ] Performance implications are explicitly documented.
- [ ] Related pages list has at most 5 entries.

## Introduction

A short paragraph about why this topic matters in ApiManager.

## Contract

Describe the stable contract readers can rely on.

- What this feature guarantees.
- Which parts are extension points.
- Which parts are implementation details and may change.

## Inputs

List important inputs (DTOs, builder methods, config keys, actor context).

## Outputs

List produced outputs (created metadata, rows, events, return objects, side effects).

## Failure Modes

Document expected errors and validation failures first.

- Invalid state transitions.
- Missing dependencies.
- Permission and visibility failures.

## How To Use

Provide a short numbered walkthrough when this topic is task-oriented.

## Example

A concise, copy-paste-ready example using ApiManager classes.

```php
<?php

use Ympact\ApiManager\Builders\RequestProfileBuilder;

$profile = RequestProfileBuilder::make()
    ->path('/v1/items')
    ->method('GET')
    ->query(['per_page' => 100])
    ->toRawArray();
```

## Validation

Include at least one command readers can run to verify behavior.

```bash
./vendor/bin/pest --parallel
```

## Performance Notes

Call out query/index/caching behavior that matters at scale.

## Security And Access Notes

Document actor-level and resource-level access implications.

## Extending

Explain how to extend via contracts and registries.

## References

### Commands

List relevant `composer` and `artisan` commands with purpose.

### Configuration

Link directly to config and source locations.

- See config: [config/api-manager.php](../../config/api-manager.php)

### Hooks And Events

List relevant events/hooks and when they are fired.

### Diagram (optional)

Use a small Mermaid diagram only when it clarifies flow.

## Troubleshooting / FAQ

Provide 4 to 6 common problems with diagnostics.

## Related Pages

List up to 5 relevant pages.

---

Authoring Notes

---

- Filename style: use kebab-case (for example, `create-dataset.md`).
- Tone: concise, developer-focused, active voice.
- Keep examples to <= 40 lines with inline comments on non-obvious lines.
- Prefer runnable examples using package APIs (Actions, DTOs, Builders, Registries).
- On first mention of a core concept, link to its canonical docs page.
- If a linked page does not exist yet, add a stub under `docs/`.
- Prefer relative links so IDE navigation works.
- Keep the "Readiness Checklist" in page drafts and complete it before marking status as stable.
