# ADR-0028: Accounts navigation and one formatting authority

Status: Accepted, 2026-10-10, under the user-authorized whole-repository interview audit.

## Problem and owner

Sixteen PHP files at the Accounts root obscure the distinction between credentials, authenticated access and the account lifecycle. An empty Logout directory suggests a use case implementation that does not exist. The unused PHP-CS-Fixer dependency and active PER configuration offer a competing formatter despite the adopted native PhpStorm MOI standard.

Accounts retains all identity, credentials and permission authority. Access and Credentials are logical subareas inside Accounts, not new modules, contexts, public owners or dependency layers. Governance 1.7.1 STR-16/17 and the explicit user request govern this placement.

## Decision and invariants

Move six related access types into `Accounts/Access/`: SessionIdentity, CurrentAccount, SignedInAccount, RequireSignedIn, RequireAdmin and AccessDenied. Move shared credential rules, bcrypt and their policy rejections into `Accounts/Credentials/`. Keep Account, Role, Accounts, AccountLifecycle and UsernameTaken at the owner root; keep Register, Login, CreateAdmin and Persistence discoverable by purpose.

Update namespaces, every first-party consumer and the module export manifest together. PHP module FQNs change; HTTP routes, JSON, error messages, role decisions, session behavior and activity contracts do not. This project has no published PHP package API or separately released PHP consumers, so forwarding compatibility classes would add unneeded indirection. Existing HTTP consumers remain compatible.

Remove the empty Logout directory and obsolete placeholders in populated CowOffer and Downloads directories. Logout remains AccountLifecycle::logout because audit and session revocation form one meaningful outcome; a forwarding Logout object would hide coordination without adding an invariant.

Remove the unused PHP-CS-Fixer dependency and configuration. Composer retains the exact versions of surviving dependencies. Native PhpStorm MOI remains the single visual formatting authority; the parser-based MOI gate checks the separately agreed nullable/postfix syntax only. Rector remains an explicit advisory modernization scan, never an automatic formatter or release requirement.

Correct meaningful escaping exception documentation along the affected Accounts call paths, including PDO failures and malformed stored roles/timestamps. Preserve runtime behavior and original operational exceptions.

## Alternatives, costs and failure behavior

- Keep all files flat: least churn, but does not meet the requested navigation improvement.
- Introduce Domain/Application/Infrastructure or Implementations umbrellas: technical grouping would obscure the interview behaviors and add hierarchy without a new owner.
- Add one class/interface per verb or retain old FQN forwarding classes: increases indirection with no independent consumer or mechanism.

The chosen change adds two purpose-named directories, zero production classes and zero interfaces. Public PHP imports move, so incomplete updates could break autoloading or authorization; strict Composer PSR-4 validation, PHPStan, owner-manifest tests and live/browser journeys exercise those callers. Login, registration and logout retain existing transaction, audit, compensation, clock and CSRF guarantees.

## Sources and proof

Original PDFs read directly: Ousterhout, A Philosophy of Software Design, second edition, physical pp41–42 (information hiding); Fowler, Refactoring, physical pp53/55/57–58 (behavior-preserving changes, published interfaces and the simplest working design); Evans, Domain-Driven Design, inspected Final Manuscript April15,2003, physical pp79–82 (conceptual module cohesion); Khononov, Learning Domain-Driven Design, physical pp67–68 (logical versus ownership boundaries).

Compass originals: How to Balance Coupling and Cohesion; How to Choose the Smallest Adequate Architecture; How to Refactor Safely. Current canonical LLD: PHP-L01/L04/L08/L10/L14/L17 and PHP-D01/D02/D07. Jeffallan original php-pro and code-reviewer guidance is advisory; its framework, universal DTO/service and PSR-12 templates do not supersede project MOI or the requirement for proportionate OOP.

PHP bcrypt escaping failure documentation follows the [official password_hash manual](https://www.php.net/manual/en/function.password-hash.php): on PHP8.3+ salt failure chains its RandomException under ValueError.

The executed release matrix and limitations are recorded in `../reviews/final-interview-audit/FINAL-ACCEPTANCE.md`. Accepted prior ADRs remain historical; this record updates the current layout and tooling application, without changing their semantic ownership decisions. Revisit when independently released PHP consumers or genuinely separate access/credential ownership emerges.
