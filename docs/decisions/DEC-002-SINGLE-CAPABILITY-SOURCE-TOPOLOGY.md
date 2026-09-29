# DEC-002 — Single Capability Source Topology

## Decision Metadata

- **Decision ID:** `DEC-002`
- **Title:** Single Capability Source Topology
- **Status:** `ACTIVE`
- **Date:** `2026-09-28`
- **Decision Authority / Deciders:** Project Owner
- **Scope / Concern:** Package source topology
- **Supersedes:** None
- **Superseded By:** None
- **Canonical Contract / Current Owner:** [RETURN_TARGET_PACKAGE_REFERENCE.md](../../RETURN_TARGET_PACKAGE_REFERENCE.md), [PACKAGE_BUILDING_STANDARD.md](../php-engineering-standards/standards/packages/PACKAGE_BUILDING_STANDARD.md) §5

## Context

- The package represents one `return-target` concern.
- No independent Domains or multiple Capabilities are currently established.
- The Package Building Standard prohibits redundant Domain/Capability directories and ceremonial structure.

## Decision

**Source Topology: Single Capability**

The package source tree follows:

```text
src/
└── {Responsibility}/
```

The `src/` structure remains responsibility-based. Responsibilities are represented only where they correspond to an actual package responsibility; this decision does not create a Domain or Capability root.

## Rationale

- The package itself is the current capability boundary.
- Adding a Domain or Capability level now would be redundant and unsupported by current facts.
- This follows the canonical package topology contract.

## Consequences

- No root Domain/Capability directory will be created merely for organization.
- Responsibilities appear only when a real runtime responsibility exists.
- A material transition to Multi Capability or Multi Domain requires reopening this decision under Decision Governance.
