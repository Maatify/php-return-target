# DEC-001 — Proprietary Package Licensing

## Decision Metadata

- **Decision ID:** `DEC-001`
- **Title:** Proprietary Package Licensing
- **Status:** `ACTIVE`
- **Date:** `2026-09-28`
- **Decision Authority / Deciders:** Project Owner
- **Scope / Concern:** Repository and package licensing
- **Supersedes:** None
- **Superseded By:** None
- **Canonical Contract / Current Owner:** [composer.json](../../composer.json), [LICENSE](../../LICENSE)

## Context

`maatify/php-return-target` is a new, unpublished package. Its licensing model required an Owner Decision, and the Owner selected `proprietary`.

## Decision

`maatify/php-return-target` uses proprietary licensing.

- `composer.json` records `proprietary`.
- `LICENSE` contains the Maatify Proprietary License.
- Public repository visibility does not convert the package to open source.

## Rationale

- The Owner explicitly selected proprietary licensing for this package.
- No open-source license has been approved.

## Consequences

- Composer metadata and `LICENSE` must remain synchronized.
- Any future license change requires a new or superseding Owner Decision under Decision Governance.
