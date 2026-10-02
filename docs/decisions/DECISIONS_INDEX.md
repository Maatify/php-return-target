# Repository Decision Index

This is the canonical current-state index for durable engineering decisions in `Maatify/php-return-target`.

## Active Decisions

| Decision ID | Title | Status | Scope / Concern | Decision Record | Canonical Contract / Current Owner | Supersedes | Superseded By |
|---|---|---|---|---|---|---|---|
| `DEC-001` | Proprietary Package Licensing | `ACTIVE` | Repository and package licensing | [DEC-001-PROPRIETARY-LICENSE.md](DEC-001-PROPRIETARY-LICENSE.md) | [composer.json](../../composer.json), [LICENSE](../../LICENSE) | None | None |
| `DEC-002` | Single Capability Source Topology | `ACTIVE` | Package source topology | [DEC-002-SINGLE-CAPABILITY-SOURCE-TOPOLOGY.md](DEC-002-SINGLE-CAPABILITY-SOURCE-TOPOLOGY.md) | [RETURN_TARGET_PACKAGE_REFERENCE.md](../../RETURN_TARGET_PACKAGE_REFERENCE.md), [PACKAGE_BUILDING_STANDARD.md](../php-engineering-standards/standards/packages/PACKAGE_BUILDING_STANDARD.md) | None | None |
| `DEC-004` | Query-Space Boundary Supersession of DEC-003 | `ACTIVE` | Return-target runtime architecture, Public Contract, security boundary, crypto integration, extensibility boundary, and persistence boundary, including the canonical decoded-inspection safety boundary for U+0020 SPACE | [DEC-004-QUERY-SPACE-BOUNDARY-SUPERSESSION.md](DEC-004-QUERY-SPACE-BOUNDARY-SUPERSESSION.md) | [RETURN_TARGET_PACKAGE_REFERENCE.md](../../RETURN_TARGET_PACKAGE_REFERENCE.md) | `DEC-003` | None |

## Superseded Decisions

Historical records only; not current implementation authority.

| Decision ID | Title | Status | Scope / Concern | Decision Record | Canonical Contract / Current Owner | Supersedes | Superseded By |
|---|---|---|---|---|---|---|---|
| `DEC-003` | Stateless Signed Return Target Runtime and Security Boundary | `SUPERSEDED` | Return-target runtime architecture, Public Contract, security boundary, crypto integration, extensibility boundary, and persistence boundary | [DEC-003-STATELESS-SIGNED-RETURN-TARGET-RUNTIME-SECURITY-BOUNDARY.md](DEC-003-STATELESS-SIGNED-RETURN-TARGET-RUNTIME-SECURITY-BOUNDARY.md) | [RETURN_TARGET_PACKAGE_REFERENCE.md](../../RETURN_TARGET_PACKAGE_REFERENCE.md) | None | `DEC-004` |

## Proposed Decisions

None.
