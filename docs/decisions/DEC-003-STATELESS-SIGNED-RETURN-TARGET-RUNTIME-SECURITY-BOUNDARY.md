# DEC-003 — Stateless Signed Return Target Runtime and Security Boundary

## Decision Metadata

- **Decision ID:** `DEC-003`
- **Title:** Stateless Signed Return Target Runtime and Security Boundary
- **Status:** `PROPOSED`
- **Date:** `2026-09-28`
- **Decision Authority / Deciders:** Project Owner
- **Scope / Concern:** Return-target runtime architecture, Public Contract, security boundary, crypto integration, extensibility boundary, and persistence boundary
- **Supersedes:** None
- **Superseded By:** None
- **Canonical Contract / Current Owner:** None — proposal only; no implementation authority until ACTIVE

> This record is a proposal. It is not Owner approval, a Runtime implementation authority, or an acceptance decision. No implementation may rely on this record until the record is explicitly changed to `ACTIVE` through the applicable decision-governance process.

## Context

`maatify/php-return-target` is currently a `Development / Unpublished` standalone package. It has no Public Runtime API implemented, no materialized `src/` directory, and no persistence or database behavior. `DEC-002` has closed the source topology as `Single Capability`.

Before the first Runtime Work Unit, the material architecture, security boundary, Public Contract, crypto integration boundary, extensibility boundary, and persistence boundary must be recorded. Earlier implementations in Host projects are evidence for discussion only; they are not this package's contract.

The proposal is designed to consume stable reusable capabilities rather than duplicate them:

- `maatify/crypto` stable `v1.0.0`;
- `maatify/shared-common` stable `v1.0.0`; and
- `maatify/exceptions` stable `v1.0.0`.

## Proposed Decision

### 1. Package Capability

`maatify/php-return-target` will be a standalone, framework-agnostic library responsible for this bounded flow:

```text
Untrusted internal return target
→ safety validation
→ signed expiring transport token
→ verification
→ verified internal return target
```

The package will not execute redirects.

### 2. Dual Integration Path

The package exposes two official integration paths.

#### Path A — Canonical HMAC Implementation

The package provides `HmacReturnTargetService` implementing `ReturnTargetServiceInterface`. This is the recommended canonical implementation.

Its protocol and security mechanics are fixed package-owned behavior. They are not a configurable strategy system.

The Host supplies only the external inputs required by the canonical boundary: configuration, key material through `KeyProviderInterface`, clock, and an optional restrict-only Host policy.

#### Path B — Host-Supplied Implementation

A Host may replace the canonical implementation completely by providing its own implementation of `ReturnTargetServiceInterface`:

```php
final class CustomReturnTargetService implements ReturnTargetServiceInterface
{
}
```

A Host-supplied implementation may use different token formats, cryptographic mechanisms, key-management semantics, persistence models, validation internals, TTL duration policies, and internal architecture.

It remains a drop-in implementation only while it preserves the shared Public behavioral floor defined below.

#### Common Public Behavioral Floor

Every implementation claiming conformity with `ReturnTargetServiceInterface`, whether canonical or Host-supplied, shares the following behavioral contract:

- `accepts(string $target): bool` returns `true` only when the target is an internal return target currently accepted by that implementation. It returns `false` for normal target rejection.
- `issue(string $target): ?string` returns an opaque transport token string for a target currently accepted by that implementation. Normal target rejection returns `null`. The service never substitutes an internal fallback target.
- `verify(string $token): ?VerifiedReturnTargetDTO` succeeds only when the token is currently accepted, its returned target is currently accepted as an internal return target, and the result has not expired.
- `VerifiedReturnTargetDTO::$expiresAt` is the authoritative Unix expiration timestamp in seconds for the verified result.
- Verification must not succeed when `current time >= expiresAt`.
- Normal malformed, untrusted, expired, or otherwise rejected tokens return `null`.

Every conforming implementation therefore has bounded-lifetime semantics.

The exact canonical TTL range, token format, cryptographic mechanism, validation algorithm, key lifecycle, audience rules, and resource limits are not part of this shared behavioral floor unless explicitly stated as such elsewhere. They belong to `HmacReturnTargetService`.

### 3. Security Guarantee Boundary

The shared Public behavioral floor applies to every conforming `ReturnTargetServiceInterface` implementation.

The following guarantees belong specifically to `HmacReturnTargetService`:

- canonical generic internal-target validation;
- raw and single-decoded security inspection;
- dot-segment and second-stage decoding-ambiguity rejection;
- the 2048-byte target bound;
- the 4096-byte token bound;
- `rt1` token framing;
- canonical Base64URL encoding;
- HMAC-SHA256 signing;
- constant-time signature comparison;
- `maatify/crypto` key lifecycle integration;
- `StrictSingleActiveKeyPolicy`;
- HKDF key derivation;
- the `return-target:token:v1` HKDF context;
- `kid` transport semantics;
- canonical audience format and binding;
- canonical TTL range `1..3600`;
- canonical configuration and crypto failure mapping;
- `ReturnTargetRestrictionPolicyInterface` semantics; and
- exact original-target representation preservation.

Host-supplied implementations are Host-owned and do not automatically inherit these implementation-specific guarantees.

This distinction is an ownership boundary, not a security rating of Host-supplied implementations.

### 4. No Strategy Explosion Inside the Canonical Implementation

`HmacReturnTargetService` does not expose pluggable strategies for changing its canonical protocol or cryptographic behavior.

`HmacReturnTargetService` is a `final` concrete class. The canonical implementation is not an inheritance extension point. A Host that requires different service behavior implements `ReturnTargetServiceInterface` separately.

The canonical implementation does not introduce contracts such as:

```text
SignerInterface
EncoderInterface
TokenParserInterface
ExpiryStrategyInterface
CryptoStrategyInterface
TokenFormatInterface
GenericValidatorOverrideInterface
```

Changing canonical protocol, crypto, key-lifecycle, normalization, or token-format semantics requires replacing the complete `ReturnTargetServiceInterface` implementation.

The only bounded Host extension inside `HmacReturnTargetService` is the restrict-only policy defined below.

### 5. Restrict-Only Host Extension

The canonical implementation supports exactly one Host-specific policy extension:

```php
interface ReturnTargetRestrictionPolicyInterface
{
    public function allows(string $inspectionTarget): bool;
}
```

This policy is optional and restrict-only.

It may reject targets for Host-specific reasons such as `/login`, `/logout`, `/2fa`, or `/admin`.

It cannot make a target acceptable when canonical generic validation has rejected it.

The policy receives only the canonical validated single-decoded security-inspection target and is invoked exactly once per acceptance evaluation.

### 6. Restrict-Only Policy Rule

The canonical acceptance order is:

```text
Original target
→ raw canonical validation
→ single percent-decoded security inspection
→ decoded canonical safety validation
→ dot-segment and second-stage ambiguity checks
→ optional ReturnTargetRestrictionPolicyInterface::allows($inspectionTarget)
→ accepted
```

Every package-owned validation step must pass before the optional Host restriction policy is consulted.

The restriction policy cannot widen the canonical safety boundary.

The restriction policy is called exactly once and receives the validated single-decoded inspection representation only.

### 7. Acceptance and Policy Semantics

For `HmacReturnTargetService`, `accepts()` represents effective current acceptance.

A target is accepted only when canonical generic validation succeeds and the optional `ReturnTargetRestrictionPolicyInterface` allows the validated inspection target.

`issue()` uses the same acceptance path before creating a token.

After cryptographic verification, `verify()` re-runs the same canonical validation and restriction-policy path against the recovered target before returning a result.

Changing Host restriction policy may therefore make an existing token cryptographically valid but currently unacceptable. In that case `verify()` returns `null`.

The restriction policy is not applied to the original raw representation separately. It receives the validated single-decoded inspection representation exactly once.

The exact original representation remains the representation stored in the token and returned after successful verification.

### 8. Stateless and Persistence Boundary

The package will be stateless. It will not own or require PDO, SQL, MySQL/MariaDB, Redis, a database, schema, migrations, a Repository, token persistence, nonce persistence, session persistence, or a one-time-token registry. It will not depend on `maatify/persistence`.

A token may be verified more than once while valid. A return-target token is not an authentication or authorization credential. One-time consumption is outside v1.

### 9. Host Ownership

The Host owns the HTTP request and response, router, middleware, session, cookies, authentication, login, 2FA or step-up flow, authorization, actual redirect execution, redirect status, query-parameter name, fallback destination, route existence, Host-specific route restrictions, secret loading, `.env`/vault/KMS integration, construction of the crypto key provider, and selection between canonical and custom `ReturnTargetServiceInterface` implementations.

The package does not know Host route names.

### 10. Canonical Package Ownership

The canonical HMAC path is composed of two package-owned responsibilities.

`HmacReturnTargetService` is the final public orchestration service. It owns the public workflow across target acceptance, clock use, optional Host restriction, token issuance, token verification, and final result acceptance. It does not catch dependency exceptions.

A package-internal final `HmacReturnTargetTokenCodec` owns canonical token and crypto mechanics:

- `rt1` framing and parsing;
- token serialization;
- Base64URL encoding and canonical decoding checks;
- HMAC-SHA256 signing and verification;
- HKDF key derivation;
- `StrictSingleActiveKeyPolicy` and `KeyRotationService` composition;
- key-resolution failure classification;
- canonical audience verification;
- canonical expiry verification;
- canonical token-size enforcement; and
- conversion of the explicitly classified dependency failures defined by this decision.

`HmacReturnTargetTokenCodec` is not a Public API, has no package interface, is not Host-replaceable, and is not a strategy extension point.

Successful codec verification returns an internal `VerifiedTokenPayloadDTO`, not the Public `VerifiedReturnTargetDTO`.

`VerifiedTokenPayloadDTO` represents only successful canonical token, signature, audience, and expiry verification. It does not claim that the recovered target has passed the current generic target validator or Host restriction policy.

Generic target validation and decoded security inspection remain package-owned validation responsibilities. Host customization remains limited to `ReturnTargetRestrictionPolicyInterface` or complete replacement of `ReturnTargetServiceInterface`.

### 11. Canonical Crypto Integration

`HmacReturnTargetService` depends at Runtime on `maatify/crypto ^1.0`.

The Host supplies only:

```text
Maatify\Crypto\KeyRotation\KeyProviderInterface
```

The canonical internal token codec owns:

```text
Maatify\Crypto\KeyRotation\Policy\StrictSingleActiveKeyPolicy
Maatify\Crypto\KeyRotation\KeyRotationService
Maatify\Crypto\HKDF\HKDFService
Maatify\Crypto\HKDF\HKDFContext
```

The exact HKDF context is:

```text
return-target:token:v1
```

The derived signing-key length is exactly 32 bytes.

Canonical issuance performs:

```text
StrictSingleActiveKeyPolicy::validate(KeyProviderInterface)
→ activeEncryptionKey()
→ HKDF with context return-target:token:v1
→ HMAC-SHA256
→ canonical token
→ canonical token-size check
```

Canonical verification performs:

```text
parse canonical token
→ StrictSingleActiveKeyPolicy::validate(KeyProviderInterface)
→ KeyProviderInterface::find(kid) existence probe
→ KeyRotationService::decryptionKey(kid)
→ HKDF with context return-target:token:v1
→ HMAC verification
→ audience verification
→ expiry verification
→ internal VerifiedTokenPayloadDTO
```

Verification must validate the `StrictSingleActiveKeyPolicy` invariant before resolving the verification key. Zero or multiple ACTIVE keys are configuration failures and must not be treated as token rejection.

The Host cannot inject another `KeyRotationPolicyInterface` into the canonical path. A Host requiring different key-lifecycle behavior uses another `ReturnTargetServiceInterface` implementation.

#### Canonical Key-Resolution Failure Classification

Canonical unknown-key classification uses only the documented `KeyProviderInterface` contract and does not inspect the internal `previous` chain produced by `maatify/crypto`.

Verification performs a direct `KeyProviderInterface::find(kid)` existence probe after the strict single-active-key invariant has been validated.

The exact classification is:

- direct `KeyProviderInterface::find(kid)` throwing `KeyNotFoundException` → normal unknown-key token rejection → `null`;
- any other throwable from the direct provider lookup → propagate unchanged;
- after the direct lookup succeeds, `KeyRotationService::decryptionKey(kid)` throwing `DecryptionKeyNotAllowedException` → normal token rejection → `null`;
- after the direct lookup succeeds, `KeyRotationService::decryptionKey(kid)` throwing `KeyNotFoundException` → `ReturnTargetCryptoConfigurationException`, preserving that exception as `previous`, because the key existed during the immediately preceding public provider lookup and the canonical key-resolution state is no longer internally consistent;
- `NoActiveKeyException` or `MultipleActiveKeysException` → `ReturnTargetCryptoConfigurationException`, preserving the original exception as `previous`;
- HKDF configuration or key-material exceptions → `ReturnTargetCryptoConfigurationException`, preserving the original exception as `previous`;
- unknown external throwables are not blanket-wrapped and propagate unchanged.

The outer exception-cause shape created internally by `maatify/crypto` is not part of the Return Target contract and is never inspected for behavioral classification.

During issuance, failure to resolve a valid active canonical key remains exceptional and never becomes normal target rejection.

### 12. HMAC Ownership

Because `maatify/crypto v1.0.0` does not expose a stable generic signing/HMAC Public API, the canonical implementation owns protocol-specific `HMAC-SHA256` signing and uses `hash_equals()` for verification. It must not create a generic local crypto or signing subsystem.

### 13. Direct Runtime Extensions

The canonical Runtime directly uses the Hash extension for HMAC operations and the JSON extension for canonical token payload serialization/deserialization and the `JsonSerializable` result contract.

If this decision becomes `ACTIVE` and Runtime implementation begins, the package must declare:

```text
ext-hash *
ext-json *
```

as direct Runtime requirements.

This proposal does not modify `composer.json`.

### 14. Clock

The canonical implementation will depend on `maatify/shared-common ^1.0` and consume `Maatify\SharedCommon\Contracts\ClockInterface`. It will not create a local Clock abstraction, and canonical expiry behavior will not use `time()` as its source.

### 15. Exceptions

The package exposes:

```php
interface ReturnTargetExceptionInterface extends \Throwable
{
}
```

Invalid canonical package configuration uses:

```php
final class InvalidReturnTargetConfigurationException
    extends \Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException
    implements ReturnTargetExceptionInterface
{
}
```

This preserves the stable `INVALID_ARGUMENT` error-code behavior owned by `maatify/exceptions`.

Canonical crypto/key-configuration failures use:

```php
final class ReturnTargetCryptoConfigurationException
    extends \Maatify\Exceptions\Exception\System\SystemMaatifyException
    implements ReturnTargetExceptionInterface
{
    protected function defaultErrorCode(): \Maatify\Exceptions\Contracts\ErrorCodeInterface
    {
        return \Maatify\Exceptions\Enum\ErrorCodeEnum::MAATIFY_ERROR;
    }
}
```

Normal untrusted-input failures remain non-exceptional and return `false` or `null` according to the public method contract.

Only the dependency failures explicitly classified by this decision are converted to package exceptions. When conversion occurs, the original throwable is preserved as `previous`.

Unknown external or infrastructure throwables are not blanket-wrapped and are not swallowed.

### 16. Proposed Public Service Contract

The shared integration boundary is:

```php
interface ReturnTargetServiceInterface
{
    public function accepts(string $target): bool;

    public function issue(string $target): ?string;

    public function verify(string $token): ?VerifiedReturnTargetDTO;
}
```

This interface is the common substitution boundary for `HmacReturnTargetService` and conforming Host-supplied implementations.

Its behavioral meaning is defined by the Common Public Behavioral Floor in this decision.

### 17. Verified Result DTO

The shared verified-result contract is:

```php
final readonly class VerifiedReturnTargetDTO implements \JsonSerializable
{
    public function __construct(
        public string $target,
        public int $expiresAt,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'target' => $this->target,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
```

The JSON keys are exactly:

```text
target
expiresAt
```

No additional fields are part of the proposed v1 result contract.

### 18. Canonical Configuration

`HmacReturnTargetService` uses the following package configuration contract:

```php
final readonly class ReturnTargetConfig
{
    public function __construct(
        public string $audience,
        public int $ttlSeconds,
    ) {
    }
}
```

`ReturnTargetConfig` is configuration, not a DTO.

`ReturnTargetConfig` validates its own constructor contract.

Construction throws `InvalidReturnTargetConfigurationException` when:

- `audience` length is outside `1..64` bytes;
- `audience` contains characters outside `[A-Za-z0-9._-]`; or
- `ttlSeconds` is outside `1..3600`.

A successfully constructed `ReturnTargetConfig` is therefore valid canonical configuration and does not require service-level exception handling for these invariants.

Its future canonical placement is:

```text
src/Config/ReturnTargetConfig.php
```

It does not contain:

```text
mode
driver
algorithm
signer
encoder
secret
root key
active key
verification keys
rotation state
custom validator
custom crypto policy
```

The configuration describes canonical Return Target policy values only. It does not select architecture.

A Host-supplied `ReturnTargetServiceInterface` implementation is not required to use `ReturnTargetConfig`.

### 19. Canonical Service Boundary

The canonical public implementation is:

```php
final class HmacReturnTargetService implements ReturnTargetServiceInterface
```

Its public construction boundary is:

```php
HmacReturnTargetService(
    ReturnTargetConfig $config,
    KeyProviderInterface $keyProvider,
    ClockInterface $clock,
    ?ReturnTargetRestrictionPolicyInterface $restrictionPolicy = null,
)
```

The service internally composes the package-owned validator and the package-internal final `HmacReturnTargetTokenCodec`.

The internal token codec conceptually owns:

```php
issue(string $target, int $expiresAt): string

verify(string $token, int $nowTimestamp): ?VerifiedTokenPayloadDTO
```

The internal token-verification result is:

```php
/** @internal */
final readonly class VerifiedTokenPayloadDTO implements \JsonSerializable
{
    public function __construct(
        public string $target,
        public int $expiresAt,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'target' => $this->target,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
```

`VerifiedTokenPayloadDTO` is package-internal and is not part of the Public API.

For public `verify()`:

```text
HmacReturnTargetTokenCodec verification
→ VerifiedTokenPayloadDTO
→ canonical generic target validation
→ single decoded security inspection
→ optional ReturnTargetRestrictionPolicyInterface
→ VerifiedReturnTargetDTO
```

`HmacReturnTargetService` constructs the Public `VerifiedReturnTargetDTO` only after every current target-acceptance gate has passed.

The codec has no public interface and is not a Host extension point.

`HmacReturnTargetService` performs orchestration only and does not catch dependency exceptions. Dependency-exception classification defined by this decision belongs to `HmacReturnTargetTokenCodec`.

### 20. Public Failure Semantics

For `HmacReturnTargetService`:

- `accepts()` returns `true` only when the target passes canonical generic validation and the optional Host restriction policy.
- `accepts()` returns `false` for normal target rejection.
- `issue()` returns a signed token for a currently accepted target.
- `issue()` returns `null` for normal target rejection.
- `verify()` returns `VerifiedReturnTargetDTO` only for a valid, currently accepted, non-expired token.
- `verify()` returns `null` for normal malformed, untrusted, expired, policy-rejected, or otherwise invalid token input.
- configuration or crypto setup failures use the typed exception contract defined by this decision.

The service never substitutes an internal fallback target.

### 21. Behavioral Requirements for Host-Supplied Implementations

A Host-supplied implementation may change:

```text
token format
cryptographic mechanism
key-management mechanism
key-rotation policy
storage model
persistence model
validation internals
TTL duration
internal architecture
signing or encryption mechanism
```

A Host-supplied implementation is a conforming drop-in `ReturnTargetServiceInterface` implementation only while it preserves the shared behavioral floor:

```text
accepts()
→ current internal-target acceptance

issue()
→ opaque token string for an accepted target
→ null on normal target rejection

verify()
→ currently accepted, non-expired VerifiedReturnTargetDTO
→ null on normal token rejection
```

`VerifiedReturnTargetDTO::$expiresAt` remains the actual Unix expiration timestamp in seconds.

A Host implementation that changes these shared meanings is not a drop-in implementation of the same Public Contract.

The package-specific `rt1`, HMAC, HKDF, audience, `kid`, canonical TTL range, canonical validation algorithm, canonical resource limits, and canonical restriction-policy semantics do not apply to a Host-supplied implementation unless that Host intentionally adopts them.

### 22. Canonical Generic Target Safety Contract

The canonical implementation accepts only an absolute-path reference with an optional query. Examples include `/`, `/orders`, `/orders/15`, and `/orders/15?tab=payment`.

The original target must first pass raw validation. It must be non-empty, no more than 2048 bytes, begin with exactly one `/`, not begin with `//`, contain no raw backslash, raw fragment marker (`#`), ASCII control character, NUL, DEL, or raw whitespace, and contain only percent escapes where `%` is followed by exactly two hexadecimal digits. It must satisfy valid RFC 3986 absolute-path plus optional-query syntax.

After raw syntax and percent validation succeeds, the canonical implementation creates one security-inspection view by percent-decoding the original representation exactly once. This view exists only for security inspection; it is not a replacement representation and is never recursively decoded. If the single-decoded view contains a newly formed valid percent escape that could require a second decoding pass to reveal URI structure, the target is rejected. For example, a class of inputs such as `%25xx` is rejected when the first decoding produces a second-stage escape ambiguity.

The single-decoded inspection view must be rejected if it contains NUL, an ASCII control character, DEL, raw backslash, raw whitespace, or a fragment marker; no longer represents an internal absolute-path plus optional-query structure; begins with `//`; becomes an external authority or scheme form; or otherwise violates the generic internal-target safety floor. The package rejects these cases rather than normalizing them.

The path is also rejected when either the raw path or the single-decoded inspection path contains a complete segment exactly equal to `.` or `..` after splitting on `/`. This includes encoded forms that become `.` or `..` after the single decoding. The package does not perform dot-segment normalization.

Validation performs no trimming, mutation, normalization, or recursive decoding. After canonical raw and decoded security validation succeeds, the optional `ReturnTargetRestrictionPolicyInterface` is invoked exactly once with the validated single-decoded inspection target.

The restriction policy is not invoked separately with the original encoded representation.

This gives Host route restrictions one deterministic semantic representation and prevents percent-encoding from bypassing Host-specific restrictions.

The exact original target representation remains unchanged and is the representation written to the token payload and returned after successful verification. The canonical service contains no Host-route denylist.

Successful issuance and verification preserve the original representation exactly: the exact original target is issued, stored in the token payload, recovered, and returned. The decoded inspection view is never stored instead of the original and is never returned to the consumer.

After successful `verify()`, the Host must treat the returned target as the validated representation. If the Host percent-decodes, normalizes, resolves, rewrites, or otherwise transforms it before redirect execution, the Host owns re-validation of the transformed value before using it as a redirect target.

### 23. Token Confidentiality Boundary

The canonical token is signed, not encrypted. Its purpose is integrity, authenticity, bounded lifetime, and context isolation—not confidentiality. A return-target token must not carry passwords, credentials, session secrets, API secrets, confidential tokens, sensitive PII, or other confidential information.

### 24. Canonical Token Protocol v1

The exact token form is:

```text
rt1.{kidSegment}.{payloadSegment}.{signatureSegment}
```

There are exactly four non-empty dot-separated segments.

The version is `rt1`; any other version causes `verify()` to return `null`. `kidSegment` is the exact Crypto key ID encoded as unpadded Base64URL. Canonical issuance requires the active Crypto key ID to be non-empty before Base64URL encoding. An empty active key ID is a canonical crypto-configuration failure and throws `ReturnTargetCryptoConfigurationException`; issuance must not produce a token with an empty `kidSegment`. `payloadSegment` is unpadded Base64URL for a JSON payload with exactly these keys and no extras:

```json
{
  "aud": "admin-auth",
  "t": "/orders/15?tab=payment",
  "exp": 1790000000
}
```

The signature input is `rt1.{kidSegment}.{payloadSegment}`. The signature is raw `HMAC-SHA256` output using the 32-byte HKDF-derived signing key, encoded as unpadded Base64URL.

### 25. Base64URL Contract

The canonical implementation uses the RFC 4648 URL-safe alphabet without `=` padding. Verification rejects invalid alphabets, empty required segments, invalid decoding, and non-canonical representations. For each segment, decoding followed by package encoding must reproduce the exact received segment.

### 26. Token Size

The canonical token-size limit is 4096 bytes.

`verify()` returns `null` immediately for an input token larger than 4096 bytes.

Canonical issuance must also enforce the same bound on the fully serialized token before returning it. A generated canonical token larger than 4096 bytes is a canonical crypto/configuration failure and throws `ReturnTargetCryptoConfigurationException`.

`HmacReturnTargetService` therefore never returns a canonical token that its own verifier rejects solely because of the canonical token-size bound.

### 27. Audience Contract

Audience is mandatory and has length 1–64 bytes. Allowed characters are `A-Z`, `a-z`, `0-9`, `.`, `_`, and `-`. There is no hidden default. A wrong audience causes `verify()` to return `null`. Audience isolation does not make the package an authentication system.

### 28. TTL and Expiry

`ttlSeconds` is mandatory and must be in the range 1–3600 seconds. There is no hidden default. Issuance sets `exp = clock.now.timestamp + ttlSeconds`. Verification rejects when `now >= exp`; there is no grace period or hidden clock skew.

### 29. Canonical Key Rotation Semantics

`HmacReturnTargetTokenCodec` composes `KeyRotationService` internally using `StrictSingleActiveKeyPolicy` and the Host-supplied `KeyProviderInterface`.

Both issuance and verification validate the `StrictSingleActiveKeyPolicy` invariant before resolving a key.

New canonical tokens use:

```text
KeyRotationService::activeEncryptionKey()
```

Canonical verification uses:

```text
KeyRotationService::decryptionKey(kid)
```

The canonical implementation does not accept a Host-supplied `KeyRotationPolicyInterface`.

The active, inactive, retired, encryption, and decryption semantics enforced by `StrictSingleActiveKeyPolicy` remain owned by `maatify/crypto`.

Unknown-key versus provider/infrastructure failure is classified only by the exact causal rules in `Canonical Key-Resolution Failure Classification`; the outer `KeyNotFoundException` type alone is never sufficient to convert a failure to `null`.

A Host requiring different rotation semantics must use a Host-supplied `ReturnTargetServiceInterface` implementation.

### 30. No Secret Ownership in `ReturnTargetConfig`

`ReturnTargetConfig` must not own secrets, root keys, active keys, verification keys, key status, key stores, or rotation state. These remain owned by the Host and the Crypto boundary.

### 31. Proposed Canonical Runtime Dependencies

If this decision becomes `ACTIVE` and the canonical implementation is built, the proposed Runtime requirements are:

```text
php ^8.4
ext-hash *
ext-json *
maatify/crypto ^1.0
maatify/exceptions ^1.0
maatify/shared-common ^1.0
```

`maatify/crypto v1.0.0` itself requires `ext-openssl` and `ext-sodium`; those must not be declared as direct Return Target requirements unless the Return Target source uses them directly.

### 32. Explicit Non-Goals

The canonical package implementation does not own HTTP, PSR-7, framework integration, middleware, controllers, routers, sessions, cookies, authentication, authorization, 2FA, redirect responses, fallback selection, route-existence checks, database, cache, persistence, one-time-token consumption, OAuth state replacement, POST/body replay, confidential-payload encryption, secret loading, or a generic crypto/signing framework.

### 33. Source Topology

This proposal does not change `DEC-002`. If activated, the package remains `Source Topology: Single Capability`. Future responsibilities such as the following may be materialized only when supported by real Runtime responsibilities:

```text
src/
├── Config/
├── DTO/
├── Exception/
├── Service/
├── Token/
└── Validation/
```

`Token/` is the internal responsibility owning `HmacReturnTargetTokenCodec`. Interfaces will be placed in the responsibility that owns them according to the Package Building Standard, rather than in a root generic `Contract/` directory by default. This Work Unit creates no source files.

The internal `VerifiedTokenPayloadDTO` belongs to the same `Token/` responsibility and does not expand the Public DTO contract.

### 34. Alternatives Considered

#### A. Host owns all signing

Not preferred as the canonical path because protocol and integrity logic would be repeated across Hosts. It remains permitted only through a custom `ReturnTargetServiceInterface` implementation.

#### B. Pluggable crypto strategies inside `HmacReturnTargetService`

Rejected because guarantees would become conditional and the canonical implementation would suffer abstraction explosion.

#### C. Return Target owns raw key arrays

Rejected because it duplicates key lifecycle management owned by `maatify/crypto`.

#### D. Local HKDF and local key rotation

Rejected because it duplicates stable Maatify shared capabilities.

#### E. Encrypt the canonical token

Not selected for v1 because confidentiality is not an established requirement.

#### F. Database-backed one-time tokens

Not selected because it introduces state and persistence without an established domain need.

#### G. Host route denylist inside the generic validator

Rejected because it couples the generic package to Host-specific routes.

#### H. Fully closed library with no custom implementation path

Not selected because interface-level replacement supports Hosts with materially different requirements without weakening the canonical secure implementation.

## Rationale

The package remains standalone and framework-agnostic so its return-target capability can be reused without coupling it to a Host application's HTTP, routing, authentication, or session model. A canonical implementation is necessary to prevent each Host from duplicating security-sensitive return-target logic. Stable Maatify capabilities—`maatify/crypto`, `maatify/shared-common`, and `maatify/exceptions`—are reused rather than duplicated.

The canonical security protocol remains locked so its guarantees stay understandable and testable. Extensibility is placed at the service boundary instead of turning the canonical implementation into a strategy or plugin framework. A restrict-only Host policy provides Host-specific routing restrictions without weakening generic package safety.

The stateless design is appropriate because a return-target token is not an authentication or authorization credential and there is no established domain need for one-time persistence. The signed, non-encrypted design is intentional because the requirement is integrity, authenticity, and bounded lifetime, not confidentiality. Host ownership of HTTP, authentication, sessions, redirects, and secrets prevents framework and application coupling. Finally, a shared behavioral floor gives `ReturnTargetServiceInterface` one consistent meaning when a Host replaces the implementation.

The canonical path also owns its `StrictSingleActiveKeyPolicy`, `KeyRotationService` composition, and `HKDFService` composition so the Host cannot silently change canonical cryptographic semantics while still using the canonical implementation. The Host retains ownership of key material and key sourcing through `KeyProviderInterface`. Hosts requiring different crypto or key-lifecycle behavior use the complete service-replacement path.

The shared method name `accepts()` is intentionally broader than a generic safety predicate because the result includes both package safety and current implementation policy. `ReturnTargetRestrictionPolicyInterface` is intentionally narrow and receives one validated inspection representation exactly once. `ReturnTargetConfig` is configuration rather than a result DTO, while `VerifiedReturnTargetDTO` remains a true result snapshot. `HmacReturnTargetService` names the canonical mechanism precisely without assigning a security rating to alternative conforming implementations.

The canonical service is final because the supported customization boundary is substitution through `ReturnTargetServiceInterface`, not inheritance from the canonical implementation. Token and crypto exception handling belongs to the internal `Token/` responsibility so the public Service remains orchestration-only under the Package Building Standard.

Verification validates the selected key-rotation invariant before key resolution, and unknown-key rejection is distinguished from provider failure through the preserved exception cause rather than the outer `KeyNotFoundException` type alone. Canonical issuance also guarantees that every returned token satisfies the canonical non-empty key-ID and token-size constraints.

Unknown-key classification relies only on the public `KeyProviderInterface` contract and not on dependency-private exception-chain structure. The canonical Runtime declares every PHP extension it directly uses. Internal token verification produces an internal token result, while the Public `VerifiedReturnTargetDTO` is created only after the recovered target passes the current generic and Host-specific acceptance gates.

### 35. Consequences

- The package has a recommended canonical implementation with fixed security semantics.
- `HmacReturnTargetService` is the recommended canonical implementation.
- The canonical implementation owns `StrictSingleActiveKeyPolicy`, `KeyRotationService` composition, and `HKDFService` composition.
- The Host supplies canonical key material through `KeyProviderInterface`.
- Host-specific restriction is available only through the narrow `ReturnTargetRestrictionPolicyInterface`.
- Full behavioral customization occurs by replacing `ReturnTargetServiceInterface`, not by injecting strategies into `HmacReturnTargetService`.
- `ReturnTargetConfig` is a configuration contract, while `VerifiedReturnTargetDTO` is a result DTO.
- `HmacReturnTargetService` is final; canonical customization is not performed through inheritance.
- `HmacReturnTargetTokenCodec` is an internal final non-Service responsibility and is not a Host extension point.
- Canonical verification validates the strict single-active-key invariant before key resolution.
- Provider/infrastructure failures are not silently converted into unknown-token rejection.
- Canonical issuance never returns an empty-`kid` or over-4096-byte token.
- Package exception hierarchy and stable error-code behavior are fixed before Runtime implementation.
- Unknown-key classification does not depend on the internal nested-exception shape of `maatify/crypto`.
- Canonical Runtime requirements include both `ext-hash` and `ext-json`.
- Internal token verification returns `VerifiedTokenPayloadDTO`; only the public Service creates `VerifiedReturnTargetDTO` after final target acceptance.
- The package does not become a strategy or plugin framework.
- Host-specific restrictions are possible through a restrict-only policy.
- Hosts with materially different requirements can replace the service implementation entirely.
- Canonical security guarantees do not automatically extend to custom implementations.
- The first Runtime source must be a real implementation, not a placeholder.
- `FILE-05` may be closed only with real Runtime code and PHPStan configuration.
- Composer dependencies remain unchanged until this decision is activated and Runtime implementation begins.
- The Package Reference becomes the canonical current Public Contract after approval and implementation.
- README, usage examples, CI, and testing must follow the actual implemented behavior.
- Any material boundary change after activation requires formal reopen or supersession.

## Decision Index Relationship

This record must be indexed under `Proposed Decisions`, not `Active Decisions`. `DEC-001` and `DEC-002` remain unchanged. This proposal does not supersede either active decision.

## Activation Boundary

Until an explicit Owner decision changes this record to `ACTIVE`, it does not authorize Runtime implementation, Composer changes, Public Contract publication, or any dependent architecture. Activation requires the corresponding Record and Index status updates together and must follow the repository's Decision Governance Standard.
