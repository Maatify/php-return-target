# DEC-004 — Query-Space Boundary Supersession of DEC-003

## Decision Metadata

- **Decision ID:** `DEC-004`
- **Title:** Query-Space Boundary Supersession of DEC-003
- **Status:** `ACTIVE`
- **Date:** `2026-10-02`
- **Decision Authority / Deciders:** Project Owner
- **Scope / Concern:** Return-target runtime architecture, Public Contract, security boundary, crypto integration, extensibility boundary, and persistence boundary (the full `DEC-003` scope), including the canonical decoded-inspection safety boundary for U+0020 SPACE
- **Supersedes:** `DEC-003`
- **Superseded By:** None
- **Canonical Contract / Current Owner:** [RETURN_TARGET_PACKAGE_REFERENCE.md](../../RETURN_TARGET_PACKAGE_REFERENCE.md) — current implemented public/runtime/behavioral contract; this `ACTIVE` Decision Record remains the durable architecture, security-boundary, and rationale authority
- **Tracking:** Issue #21; target release `v1.0.0-rc.2` (not yet published when this record was written)

> This record is Owner-approved and `ACTIVE`. It is the implementation authority for its recorded scope. Any material boundary change requires formal reopen or supersession under the Decision Governance Standard.

## Context

[DEC-003](DEC-003-STATELESS-SIGNED-RETURN-TARGET-RUNTIME-SECURITY-BOUNDARY.md) closed the canonical generic target safety contract so that the single-decoded inspection view is rejected when it contains raw whitespace, regardless of which URI component produced it. As a consequence, a target such as `/p?q=two%20words` was rejected even though `%20` is the standard percent-encoding of a space inside a query component and the original representation is syntactically valid RFC 3986.

The Owner formally reopened the `DEC-003` boundary for this single point and targeted `v1.0.0-rc.2`. `v1.0.0-rc.1` was already published under `DEC-003` and remains a historical release.

## Decision

### 1. Component-aware decoded SPACE rule

The canonical decoded-inspection rule for U+0020 SPACE is:

```text
raw U+0020 SPACE
→ rejected everywhere

single-decoded U+0020 SPACE originating from the path component
→ rejected

single-decoded U+0020 SPACE originating from the query component
→ accepted
```

The component of a decoded byte is the component of the raw byte (or raw percent escape) that produced it. The split between path and query is made on the first raw `?` of the original target. A `%3F` that decodes to `?` therefore stays in the component it was written in.

Accepted examples: `/p?q=two%20words`, `/p?q=a%20b%20c`.
Rejected example: `/p%20x?q=1`.

### 2. Carried-forward `DEC-003` contract

`DEC-003` is superseded as a whole record so that no two `ACTIVE` Decisions overlap in scope. Every other `DEC-003` decision, including its Sections 1–21 and 23–35 and every part of Section 22 other than the decoded-SPACE rule above, is carried forward unchanged as `ACTIVE` authority under this record. The historical text of `DEC-003` remains the preserved wording of those carried-forward decisions and its rationale; this record does not restate or reinterpret them.

In particular, and without exception, the following remain locked:

- raw whitespace rejection (including raw SPACE in the query);
- NUL, CR/LF, every other ASCII control, DEL, backslash, and decoded `#` rejection (in both path and query);
- authority-form, scheme-form, and external-target rejection;
- raw and single-decoded path dot-segment rejection;
- second-stage valid percent-escape rejection (for example `%2520`);
- exactly one percent-decoding inspection pass, with a literal `+` remaining a literal `+`;
- exact original-representation preservation in the token and in the verified result;
- restrict-only Host policy semantics, with the policy invoked exactly once per acceptance evaluation and receiving the single-decoded inspection representation;
- Host ownership of redirect execution and fallback behavior;
- the 2048-byte target bound, the 4096-byte token bound, the `rt1` token protocol, and the crypto, key, TTL, and audience contracts;
- the Public API surface and the absence of any new strategy, configuration option, or public interface.

### 3. Boundaries of this change

The decoded SPACE inspection value that reaches `ReturnTargetRestrictionPolicyInterface` contains the single-decoded query SPACE. The policy cannot widen canonical acceptance; it can only reject.

This decision does not relax raw `[` or `]`, does not change the acceptance of `ids[]=1`, and does not introduce normalization, recursive decoding, query parsing into key/value structures, `application/x-www-form-urlencoded` decoding, or any conversion of `+` to SPACE or of `%20` to `+`. It does not change the token format, crypto, TTL, or Host fallback behavior.

After successful `verify()`, the returned target is the exact original representation, for example `/p?q=two%20words`. A Host that decodes or transforms it before redirecting owns re-validation of the transformed value, as already recorded by `DEC-003`.

## Rationale

`%20` inside a query is ordinary, standards-conformant encoding and is a common real-world return-target shape (for example search terms). Rejecting it forced Hosts to fall back for legitimate internal targets without any security benefit, because a SPACE in the query component cannot alter the path, the authority, or the internal-target structure that the validator protects.

The same is not true in the path, where a decoded SPACE can change how downstream routers and logs interpret the segment. The rule is therefore component-aware rather than a general relaxation of decoded whitespace: every other decoded unsafe value stays rejected, and the path stays as strict as before.

Supersession rather than amendment keeps the `DEC-003` historical text truthful: it correctly records the boundary that governed `v1.0.0-rc.1`.

## Consequences

- `DEC-003` becomes `SUPERSEDED` and `DEC-004` becomes `ACTIVE`; the Decision Index records both in the same change.
- `CanonicalReturnTargetValidator` distinguishes decoded path from decoded query and accepts U+0020 only in the latter.
- The Package Reference describes the new boundary as the current Public/behavioral contract.
- Targets such as `/p?q=two%20words` are accepted, issued, verified, and returned byte-for-byte; no new public API exists.
- This is an externally observable behavioral/security-contract change. It is delivered in `v1.0.0-rc.2`; `v1.0.0-rc.1` consumers keep the earlier, stricter behavior until they upgrade.
- Unit, System, and Consumer Verification coverage protect the new boundary and the unchanged rejections.
- Any further material boundary change requires formal reopen or supersession.

## Decision Index Relationship

This record is indexed under `Active Decisions` and supersedes `DEC-003`, which is indexed under `Superseded Decisions`. `DEC-001` and `DEC-002` remain unchanged and `ACTIVE`.
