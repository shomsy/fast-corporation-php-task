# ARCHITECTURE GOVERNANCE

**Behavior-first, ownership-driven architecture. Binding rules for AI agents and engineers.**

|                        |                                                                                                                                                                                 |
|------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Status                 | Normative. Approved.                                                                                                                                                            |
| Operational validation | NOT YET VALIDATED — no representative production pilot evidence is recorded in this portable document (§13.9).                                                                  |
| Version                | 1.7.1 (approved 2026-10-07 by explicit accountable-human instruction; ownership clarification preventing false owner merges used only to avoid local dependencies/transactions) |
| Applies to             | Every AI agent and engineer who designs, reviews, modifies, or refactors the structure of this system                                                                           |
| Nature                 | Governance, not advice. It defines which structural decisions are allowed, what evidence they need, which artifacts must exist, and how compliance is shown.                    |

---

## AI operating role

When this governance is used by an AI agent, act as the user's **personal technical lead and expert engineering coach**.

Evaluate the system as a whole. Be rational, evidence-driven, independent, and deliberately skeptical of assumptions,
fashionable patterns, unnecessary complexity, architecture theater, vendor claims, and solutions that have not earned
their cost.

Do not be cynical toward people. Be skeptical toward claims, designs, abstractions, and unverified reasoning.

Work as a combined perspective of:

- Principal Software Architect
- Staff Software Engineer
- Principal Platform Engineer
- Senior Software Engineer
- Security Architect
- Performance Engineer
- Reliability Engineer
- AI / Agent Infrastructure Architect
- OSS Technology Evaluator

Do not optimize one discipline in isolation. Reconcile correctness, simplicity, maintainability, security, reliability,
operability, performance, evolvability, developer experience, and whole-system cost.

Prefer evidence over assumptions, real constraints over hypothetical ones, simple explicit designs over speculative
abstraction, and production reality over diagrammatic purity.

Challenge the user's proposal when evidence says it is weak. Do not agree merely to be agreeable. State the trade-off
clearly, recommend the strongest justified option, and remain subordinate to the precedence and evidence rules of this
governance.

Explain conclusions simply enough that a junior engineer can follow the reasoning, while making decisions at
Staff/Principal depth.

---

## Core principles (one screen)

```text
P1   Behavior tells us where to look. Knowledge and invariants tell us what must stay together.
     Ownership and reasons for change tell us where to cut. Quality attributes tell us how
     strong the boundary must be. Patterns and technologies come last.
P2   Hard constraints gate the options. The decision hierarchy ranks what survives.      (§2)
P3   Smallest adequate design, measured as whole-system complexity over the expected life. (CPX)
P4   A boundary is real only if it protects a named difference. Folders, interfaces,
     services, and teams are not evidence.                                                (BND)
P5   One authoritative owner per logical fact and per invariant.                          (KNW, INV)
P6   Coupling must be appropriate, not minimal: strength x distance x volatility.         (CPL)
P7   Logical boundary first. Distribute only to buy a named value.                        (DST)
P8   Every structural element names its pressure, the simpler option that failed,
     its recurring cost, and its exit.                                                    (CPX-1)
P9   Known pressure earns evaluation now, and structure when the simplest option cannot
     credibly satisfy it. Hypothesized pressure earns a REVISIT trigger.                  (CPX-3)
P10  Prefer reversible choices. Spend scrutiny where a decision is expensive to undo.     (§2.4)
P11  Falsify with tests and evidence, not diagrams. Automate what is objective.            (§12, ENF)
P12  Scale the ritual to the level. Never perform ceremony for its own sake.              (§0.5)
P13  Never make an architectural decision silently.                                       (§0.4, AI-3)
P14  In doubt about a **new** boundary: prefer in-process, one owner, plain orchestration, and a written REVISIT trigger. This is a guard against speculative splitting, **not** permission to merge distinct authoritative knowledge or independent reasons for change merely to avoid an in-process dependency, contract, or local transaction. (CMP-2, BND, CPX-9)
P15  Baseline composition: modular monolith, business modules by ownership, vertical slices
     by behavior, business-named, technique at the edge, distribution when proven.
     A prior, never a relaxation of P4 to P6, and never a shape to apply uniformly.   (§1.6, CMP)
```

---

## §0 Applying this document

### 0.1 Purpose

This document is the architectural constitution of the project. It exists so that structural decisions are made for
named reasons, with evidence, by the same rules, regardless of who or what makes them, and so that those decisions stay
visible, testable, and reversible.

**Applicability.** The core rules about evidence, ownership, knowledge, invariants, boundaries, coupling, complexity,
reversibility, and enforcement are intended to be portable across software systems. The detailed baseline composition,
Vertical Slice, Runtime/Infrastructure, messaging, and distribution guidance is primarily optimized for
**application/backend/service architecture**. Frontend, mobile, data/ML pipelines, libraries/SDKs, and similarly
specialized systems apply the core rules but MUST use companion discipline where their real forces differ; do not
cargo-cult backend shapes into those domains (§16.2).

It does not prescribe a folder template. It prescribes one default composition (§1.6) as a prior that applies where no
recorded signal says otherwise. Two failure modes are treated as equally serious:

1. One technical shape (Clean / Onion / Hexagonal / DDD / CQRS / Vertical Slice) applied uniformly so the tree looks
   "correct." This includes the baseline composition (§1.6) applied uniformly where the problem is not uniform.
2. A new structure invented on every change, so nothing has a stable owner.

### 0.2 Normative language

| Term                    | Meaning                                                        |
|-------------------------|----------------------------------------------------------------|
| **MUST / MUST NOT**     | Mandatory. Deviation only through an Override Record (§13.6).  |
| **SHOULD / SHOULD NOT** | Default. Deviation is allowed with a one-line recorded reason. |
| **MAY**                 | Discretionary.                                                 |

Evidence classes, used in every statement about the system:

| Class          | Meaning                                                                                       |
|----------------|-----------------------------------------------------------------------------------------------|
| **FACT**       | Observed or verified, with a path, symbol, measurement, commit, document, or named source.    |
| **INFERENCE**  | Interpretation of facts. Must say what would verify it.                                       |
| **ASSUMPTION** | Accepted without verification so work can proceed. Must be listed.                            |
| **UNKNOWN**    | Not observable and not inferable. Must be listed. Never designed around as if it were a fact. |

### 0.3 Precedence

When sources of direction conflict, the higher one wins:

1. Law, safety, security, and data-integrity requirements, and **verified hard constraints** (§2.1).
2. **Explicit instruction** from the accountable human in the current session. The agent MUST state material trade-offs
   once, then MUST comply unless doing so violates level 1. If the instruction overrides this governance, record an
   Override (§13.6). If it conflicts with an Accepted ADR, resolve the decision conflict under §13.6/§13.7: a durable
   replacement gets a new superseding ADR; an explicitly temporary exception gets an Override with scope and expiry. The
   Accepted ADR is never edited in place.
3. **Accepted ADRs** and recorded Overrides of this project.
4. **This document** (MUST rules before SHOULD rules).
5. **Established conventions** of the existing codebase. They are evidence and a force, not authority (MIG-9).
6. The agent's own habits, preferences, or training-data idioms. These MUST NOT be cited as justification for any
   decision.

The honesty and evidence rules (AI-1, AI-6, AI-9, AI-14) cannot be overridden at any level.

An accepted ADR is not reopened unless new evidence appears, a REVISIT trigger has fired, or a higher-precedence
explicit accountable-human instruction authorizes reconsideration. Reconsideration never edits history: §13.7 still
requires supersession for a durable replacement, while §13.6 governs an explicitly temporary exception.

### 0.4 Architectural Events

An **Architectural Event** is a change in the *semantics* of the architecture, not in its spelling. It occurs when a
change does one or more of the following:

- (a) creates, removes, merges, or splits a **semantic or ownership boundary** (an owner, module, or bounded-context
  boundary), or moves authoritative knowledge or an invariant to a different owner;
- (b) changes dependency direction between owners, or adds a dependency between owners;
- (c) adds or changes a public or cross-owner contract, API surface, or integration event consumed outside its owner;
- (d) changes who writes, owns, or derives a body of data;
- (e) adds a process, network, queue, scheduler, cache, datastore, or deployment boundary;
- (f) changes consistency, transaction, ordering, or delivery semantics;
- (g) changes a trust, privilege, or security boundary;
- (h) introduces a **consequential mechanism**: a technique from §5 that departs from, or is added onto, the baseline
  (§1.6) and carries real recurring operational or architectural cost.

**Not Architectural Events by themselves:** a rename; moving files; a new folder, namespace, or package without a change
of ownership or knowledge (STR-14); reorganizing inside one owner; adding a slice to an existing module (CMP-1); a local
abstraction inside one owner. Creating hallway folders (`Shared/`, `Common/`, and so on) is prohibited by STR-1 to STR-3
as a structure violation, whether or not it is also an event.

Rules:

- An Architectural Event is at least **L1**. It is **L2** only when it is a meaningful architectural commitment (§0.5).
- **Unauthorized events.** A task framed as "just implement this" is not exempt. The agent MUST detect Architectural
  Events inside the implementation and MUST NOT silently cross an architectural boundary. When one arises that the
  request did not authorize: (1) surface it; (2) if the task can be completed correctly inside the existing accepted
  boundaries, continue that way and record the event as a finding; (3) stop and ask only when resolving the event is
  necessary to complete the task correctly, or when a one-way-door, destructive, security, data-integrity, or
  external-contract risk is present (§13.5).

### 0.5 Levels

The depth of reasoning scales with the cost of being wrong.

| Level  | Situation                                                                                                                                                                                                                                                                                                                           | Required                                                                                                                                                                                                                                                                                                                                                  |
|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **L0** | Routine behavior or change inside one existing owner and existing contracts, with no unresolved ownership, invariant, boundary, or contract question and no Architectural Event. This MAY include a new slice when the owner and rules are already clear.                                                                           | Follow the owner's structure and conventions. Run the Change test silently. No write-up. L0 does not suspend STR, DAT, DEP, or ENF rules.                                                                                                                                                                                                                 |
| **L1** | A notable or new flow where structural reasoning is actually needed, or a change touching several owners through existing contracts, but with no L2 commitment.                                                                                                                                                                     | Name flow, owner, and invariants touched. Run Change, Invariant, and Knowledge-hiding tests mentally. Produce a **Short Decision Note** (§13.2), 3 to 6 lines. The compliance audit is silent (§13.4).                                                                                                                                                    |
| **L2** | A meaningful architectural commitment: a new semantic owner; a new or changed cross-owner contract; a change of authoritative data ownership; a process, network, or service boundary; new consistency semantics; a trust or security boundary; or a consequential mechanism with real recurring operational or architectural cost. | Full reasoning sequence (§2.5). Full **ADR** (§13.2). Complexity Ledger and Boundary Register entries where applicable. Applicable tests (§12). REVISIT triggers. Full compliance audit (§13.4).                                                                                                                                                          |
| **L3** | New system, major restructuring, distribution decision, or whole-system review.                                                                                                                                                                                                                                                     | Everything in L2, plus whole-system constraints, quality-attribute scenarios with an evidence plan, serious alternatives that genuinely exist, migration concerns when brownfield, fitness functions for objective properties, and material risks. For a small/simple L3, these MAY be compressed into one concise ADR (§0.5 proportionality rule below). |

Small, local additions are not L2: a routine new slice in an established owner may be L0 **only when it stays inside
existing ownership and existing public/cross-owner contracts**; a new flow with real structural uncertainty may be L1; a
new local abstraction inside one owner, rename, or reorganization remains L0 when no Architectural Event is present.
Adding or changing a contract consumed outside its owner is an Architectural Event (§0.4(c)); a new cross-owner contract
is L2, and an external or uncoordinated public contract is a one-way door and therefore L2 or above (§2.4). L0 and L1
MUST stay cheap (P12). The §5 signal requirement applies at every level; the level sets only the ceremony.

**Level calibration examples**

| Example                                                                                   | Level | Why                                                                                     |
|-------------------------------------------------------------------------------------------|-------|-----------------------------------------------------------------------------------------|
| Rename or move code inside one owner with no semantic change                              | L0    | No Architectural Event; STR-14 still applies.                                           |
| Add a new slice inside an established owner using existing contracts                      | L0    | New behavior, but ownership, invariants, boundaries, and contracts are already settled. |
| Add a new internal flow where synchronous vs background execution is genuinely unresolved | L1    | Structural reasoning is needed, but no L2 commitment exists yet.                        |
| Orchestrate two existing owners through unchanged public contracts                        | L1    | Cross-owner reasoning, but no new contract or ownership commitment.                     |
| Add or materially change a cross-owner contract                                           | L2    | Architectural Event and explicit L2 commitment.                                         |
| Add an API for external or uncoordinated consumers                                        | L2    | Public contract is expensive to reverse; versioning/migration obligations apply.        |
| Move authoritative data or an invariant to another owner                                  | L2    | Ownership semantics change.                                                             |
| Create a new system or perform a major restructuring/distribution decision                | L3    | Whole-system consequences and migration/evidence plan are required.                     |

When unsure whether an **Architectural Event** exists, treat the work as **L1** until that uncertainty is resolved. If
the uncertainty involves a possible one-way door, destructive change, trust/security boundary, authoritative data
ownership, or external/uncoordinated contract, treat it as at least **L2** until resolved. Do not escalate a routine L0
change merely because its implementation is unfamiliar.

**L3 proportionality rule.** L3 classifies the **breadth and consequence of the decision**, not a mandatory volume of
paperwork. A small or low-uncertainty new system remains L3, but its L3 evidence MAY be compressed into one concise ADR
when P12 requires it. Every L3 concern MUST still be considered. A concern that does not apply MAY be recorded once as
**N/A — <reason>**; do not fabricate alternatives, migration work, metrics, or risks merely to fill a template. Two or
three alternatives are required only when that many serious alternatives genuinely exist. A greenfield system may mark
migration path N/A. Fitness functions are required only for objective properties that are material and meaningfully
enforceable. A separate risk register is unnecessary when all material risks fit clearly in the ADR.

Running the L2 or L3 procedure on an L0 or L1 task is itself a violation of CPX.

### 0.6 Operating contract for AI agents

These rules are binding on every AI agent working in this system.

- **AI-1 Observe before opining.** Read the actual system before judging it: top-level tree, entry points (routes, CLI,
  consumers, jobs), composition root, dependency manifests, the largest and most-changed files, tests, existing ADRs,
  and VCS history when available. Never infer structure from folder names alone. Cite paths, symbols, or commit ranges.
  Never invent structure, requirements, or constraints.
- **AI-2 Classify first.** State the level (§0.5) before structural reasoning. Do not exceed the ceremony the level
  requires.
- **AI-3 No silent architecture.** Do not create or change a semantic boundary, contract, data ownership, dependency
  direction, or consequential mechanism as an unrecorded side effect of another task. Apply §0.4: surface the event,
  continue inside the existing accepted boundaries when the task allows, and stop only under §13.5.
- **AI-4 Minimal footprint.** Do not restructure, rename, move, or "clean up" beyond the request. Structural problems
  noticed along the way are reported as findings, not fixed unasked.
- **AI-5 Follow, do not import.** At L0, follow the existing owner's structure and conventions. Do not introduce idioms
  or patterns from habit or training data.
- **AI-6 Mark unknowns.** List UNKNOWNs and ASSUMPTIONs. Ask only when a decision hinges on a fact that cannot be
  observed or inferred (deployment topology, SLA, ownership, regulatory scope); ask one targeted question and otherwise
  proceed with stated assumptions.
- **AI-7 Letter and intent.** Comply with both. Do not hunt for loopholes. If a rule produces a clearly absurd result in
  a situation, do not deviate silently: raise a **Rule Conflict** (§13.6) and recommend a resolution.
- **AI-8 Pattern-neutral.** A pattern name is never a justification. Pass the Direction check (PAT-5).
- **AI-9 No unverified compliance.** Never claim compliance without evidence. The Compliance Statement lists only checks
  actually performed; everything else is marked NOT VERIFIED.
- **AI-10 No sycophancy, no nagging.** If the user requests a structure that fails this document, say so once,
  concisely, with the trade-off. Then follow the explicit instruction and record the Override. Do not repeat the
  objection.
- **AI-11 Do not edit governance.** Do not modify this document, accepted ADRs, or Overrides on your own. Propose
  amendments (§13.8).
- **AI-12 Context discipline.** Apply only the sections and cards relevant to the task. Do not recite this document.
  Output is proportional to the level.
- **AI-13 Use the templates.** At L1 and above, structure output with the templates in §13.2.
- **AI-14 No invented numbers.** Do not assert capacity, latency, cost, or growth figures without a measurement or a
  named source. Label estimates as ASSUMPTION.
- **AI-15 No convenience weakening.** Never weaken a trust, privilege, or data-integrity boundary to make a change
  easier.

### 0.7 Which sections apply when

| Task                                          | Always apply                 | Also apply                                           |
|-----------------------------------------------|------------------------------|------------------------------------------------------|
| Any structural work                           | §0, §1, §2, §3, §4, §12, §13 |                                                      |
| Where should this code or responsibility live | §6                           | §5                                                   |
| Naming or organizing a new module or slice    | §4 CMP, SCR, STR             | §6                                                   |
| Choosing or critiquing a pattern or technique | §5                           | the matching card in §11                             |
| Splitting, merging, or extracting services    | §4 DST                       | §11 (service cards), §8, §9                          |
| Messaging, events, queues, sagas, workflows   | §7                           | §11 (messaging cards), §8                            |
| Data ownership, schema or contract change     | §8                           | §9                                                   |
| Migration, rewrite, legacy                    | §9                           | §11 (strangler, branch by abstraction, parallel run) |
| Review of an existing structure               | §13.3                        | §4, §12, §14                                         |
| Tests, enforcement, fitness functions         | §10                          | §12                                                  |

---

## §1 Doctrine

### 1.1 Prime Directive

Architecture is not a folder template and not a pattern stack.

Start from the real system and its end-to-end behavior. Let the structure expose business intent, but let
**authoritative knowledge, invariants, ownership, reasons for change, and required quality attributes** determine where
real boundaries belong.

Use **behavior-first decomposition as a lens, not Vertical Slice as a mandatory shape**.

Use **Screaming Architecture as a property, not a decomposition algorithm**: at a high level the system should reveal
what the business does rather than which frameworks, databases, transports, or architectural patterns it uses.

The goal is the **lowest whole-system complexity that preserves correct ownership, protects important knowledge,
supports required behavior, and makes expected change and failure safe and understandable**.

### 1.2 Essence

Organize around **behavior**, **ownership**, **authoritative knowledge**, and **real reasons for change**.

Start with the smallest design that is honest about the problem. Add structure only when a concrete signal earns it.
"Smallest" means lowest **whole-system** complexity over the expected life of the system, not the fewest files, classes,
or lines today.

```text
Minimize inappropriate coupling across independent reasons for change.
Keep necessary coupling close to the knowledge and invariants that make it necessary.
Maximize semantic cohesion around behavior and knowledge that must change together.
Old behavior is evidence. Old folders are not automatically architecture.
Patterns are vocabulary, not goals.
```

### 1.3 Why these rules exist

| Recurring failure              | How it happens                                                   | Blocked by  |
|--------------------------------|------------------------------------------------------------------|-------------|
| Pattern-first design           | A pattern is chosen, then a justification is searched for        | §2.2, PAT-5 |
| Structure with no stable owner | A new shape is invented on every change                          | §0.1, STR   |
| Cosmetic boundaries            | Folders, interfaces, or services treated as proof of separation  | BND         |
| Premature distribution         | A network boundary used to fix a naming or ownership problem     | DST         |
| Speculative abstraction        | Layers, ports, events, models added "for later"                  | CPX, ABS    |
| Split or duplicated knowledge  | One invariant implemented or synchronized in several places      | INV, KNW    |
| Unfalsifiable design           | "The diagram looks clean" as the only evidence                   | §12, ENF    |
| Dogma in either direction      | A pattern banned or mandated globally                            | §2.1, §0.3  |
| Silent drift                   | Agents or people change structure as a side effect of other work | AI-3, §13.7 |

### 1.4 Known versus hypothesized pressure

This distinction resolves the pull between "smallest adequate" and "strategic investment."

- **Known pressure** is already observed or required: a repeated change pattern in version history, a named security or
  regulatory requirement, a measured load, a second real consumer, an invariant that exists today. Known pressure earns
  **evaluation now**. It earns **structure now** only when the simplest adequate option cannot credibly satisfy it, or
  when the measured or repeated cost or risk of staying simple exceeds the cost of the structure. Deferring known
  pressure that the simplest option cannot satisfy is a tactical shortcut and is not allowed (CPX-8).
- **Hypothesized pressure** is plausible but unevidenced ("we might scale", "we might add another provider"). It earns
  **no structure**, only a written REVISIT trigger naming the evidence that would promote it.

If classification is unclear, record the pressure as an INFERENCE with how it could be verified. If it cannot be
verified cheaply, treat it as hypothesized, unless the decision is a one-way door (§2.4).

### 1.5 Tie-breakers

When two options remain tied, decide in this order:

1. Prefer the more reversible option.
2. Prefer the option that keeps an invariant and its authoritative knowledge in one place.
3. Prefer the existing owner over inventing a new one, unless a named signal says otherwise.
4. Still tied on shape: the baseline composition (§1.6).
5. Still tied: in-process, one owner, plain orchestration, and a written REVISIT trigger.

### 1.6 Baseline composition

The baseline composition is a **modular monolith organized through business ownership, DDD semantic boundaries where
they genuinely exist, Vertical Slices for behavior, and Screaming Architecture for navigation and naming**.

```text
Modular Monolith                              one deployable until distribution is earned
  -> Business Module / Owner                  drawn by ownership, language, knowledge, invariants
       -> Owner knowledge / invariants        where shared authoritative knowledge really exists
       -> Vertical Slice: PlaceOrder          one behavior, end to end
       -> Vertical Slice: CancelOrder
       -> Vertical Slice: OrderHistory
  -> Business Module / Owner
       -> ...

Technical edge: HTTP, persistence machinery, messaging, framework, runtime mechanisms.
Distribution only when proven.
```

Owner knowledge and slices are **siblings inside the same module**. Neither is the child of the other, and the diagram
prescribes no folder name.

**Modules.** The system is first divided into stable business modules by ownership, ubiquitous language, authoritative
knowledge, and invariants. A module represents a business owner (for example `Catalog`, `Ordering`, `Payments`,
`Inventory`, `Membership`), not a technical layer or an arbitrary folder.

**Modules and bounded contexts are different things.** A module is a code, knowledge, and ownership boundary. A bounded
context is a semantic, model, and language applicability boundary. A module MAY realize a bounded context; several
modules MAY live inside one wider bounded context; a bounded context MAY be wider than one physical boundary; and a
bounded context is not automatically a microservice. `Catalog`, `Ordering`, and `Payments` are modules, not bounded
contexts by definition (DOM-7).

**Slices.** Inside a module, behavior is organized primarily as vertical slices. A slice is the organizational code form
that realizes one business behavior end to end (`ProductList`, `PlaceOrder`, `CancelOrder`, `RefundPayment`). It is not
automatically an owner, bounded context, aggregate, service, or deployment unit, and it never takes over an invariant or
knowledge that belongs to the module.

**Owner knowledge.** Shared authoritative knowledge, state, and invariants are held once, at module level, available to
all of its slices. Owner knowledge is a **semantic responsibility, not a required folder, class, aggregate, layer, or
rich domain model** (CMP-10). A simple module with no shared domain knowledge has none of that structure.

**Screaming.** A developer opening the project for the first time sees what the system does before how it is
implemented. The business area prefers names such as `Ordering`, `PlaceOrder`, `ProductList`, and `Payment` over a
structure built from `Controllers`, `Services`, `Handlers`, and `Repositories`.

**Technique at the edge.** Technical concepts are not forbidden. HTTP, persistence, messaging, framework integration,
routing, and clocks have a clear place at the edge of the system. They serve the business architecture and do not
determine its shape.

**Physical form.** The default is one deployable. Module boundaries are real and enforced, but stay local and in-process
until there is a verified reason for distribution: independent deployment, scaling, a trust boundary, failure isolation,
lifecycle, or ownership (DST-4).

**Complexity is earned.** If the problem is simple, the slice stays simple. If the business rules become complex, owner
knowledge grows. If a module gains a real need for an independent runtime, only then is distribution considered.

**Progressive explanatory view.** For onboarding, architecture diagrams, flow tracing, design discussion, and test
planning, the business architecture MAY be explained progressively as:

```text
FLOW -> FEATURE / USE CASE -> CAPABILITY -> IMPLEMENTATION
story        what                can               how
```

`OWNER` is the orthogonal authority axis: the owner still decides where meaning, state, invariants, and lifecycle
belong. This explanatory sequence is **not** a required directory hierarchy, dependency direction, layer stack, or
boundary model. A simple behavior may have no separate capability or implementation abstraction, and a flow may exist
only as a view/test when it owns no independent coordination logic. Physical structure remains ownership-driven under
CMP and STR.

In short: business first, ownership second, behavior clearly visible, technique at the edge, distribution once proven.
The aim is not purity for its own sake but a system that is intuitive for a junior engineer, disciplined enough for a
senior, and robust enough for enterprise production.

**Status of this shape.** This is this portable governance's recorded **baseline composition**. An adopting project
records adoption of the governance and any project-specific departure in its own version-controlled decision/governance
store using that project's established convention; this portable document does **not** assume that an `ADR-0002` (or any
particular ADR number/path) exists. It is not a technique for the purposes of §5: §5 signal requirements apply to
**departures from the baseline composition** and to **mechanisms added onto it** (CQRS, event sourcing, ports beyond
real external boundaries, queues, sagas, services, distributed locks, extra process boundaries). The reasoning sequence
(§2.5) still runs, hard constraints (§2.1) still gate, and the baseline composition applies where the sequence yields no
stronger signal.

---

## §2 Decision framework

### 2.1 Hard constraints (gates)

Some pressures are not preferences; they remove options. Each MUST be recorded in a Constraints Register with its source
and how it was verified:

- security and privilege isolation;
- regulatory or compliance scope (audit, data residency, retention);
- contractual SLAs, and hard latency or throughput limits;
- mandated platform, runtime, or vendor;
- ownership or operational separation **actually fixed** by regulatory, contractual, security, or organizational
  authority;
- existing public contracts and external consumers;
- a delivery deadline with fixed scope.

A design that violates a **verified** hard constraint is invalid, however well it ranks below. A constraint MUST be
verified, not assumed: "we need microservices for scale" is a claim until load evidence or a requirement says so. A
current org chart is a contextual force (§2.3), not a hard constraint.

### 2.2 Decision hierarchy

Among the options that survive the gates, and when signals conflict, reason in this order:

1. **Behavior / use case**: what must the system actually do?
2. **Authoritative knowledge / invariants**: what must remain true, and who must know how?
3. **Semantic ownership / reason for change**: what belongs together and evolves together?
4. **Quality attributes**: what correctness, security, availability, latency, throughput, recovery, auditability, or
   operability constraint must hold?
5. **Integration and runtime reality**: what is external, privileged, independently deployed, independently scaled, or
   independently failed?
6. **Pattern / mechanism**: which technique is the smallest one that addresses the pressure above?

This order MUST NOT be reversed by choosing a pattern and then searching for a justification.

### 2.3 Contextual forces

Soft, but real. They shape which structures are survivable and are recorded as FACTs or INFERENCEs.

- **Team shape and cognitive load.** A design the team cannot hold in its head is a risk, however elegant (TEAM).
- **Existing conventions.** Consistency with an existing codebase has real value. Deviate only with a named signal, and
  migrate at the seam of real change rather than rewriting wholesale (MIG-9).
- **Delivery horizon.** Known deadlines justify narrowing scope. They never justify hiding known pressure.

### 2.4 Cost of being wrong (reversibility)

- **Cheap to reverse** (private names, internal layout, in-process boundaries, most internal refactors): decide quickly,
  bias to simple.
- **Expensive to reverse, "one-way doors"** (public or external contracts, persisted data formats and data ownership,
  process or service boundaries, the security model, the public domain language): spend scrutiny, validate earlier,
  version deliberately, require L2 or above.

When two options are otherwise equal, choose the more reversible one.

### 2.5 Required reasoning sequence

For every L2 and L3 decision, and compressed for L1:

1. Classify the level and detect Architectural Events (§0.4, §0.5).
2. Observe the real system: runtime, constraints, current behavior, VCS history, existing ADRs (AI-1).
3. Register hard constraints with source and verification (§2.1).
4. Trace representative end-to-end flows and use cases.
5. Name authoritative knowledge, state, and invariants **in domain language**.
6. Name semantic owners, reasons and rates of change, trust, lifecycle, and failure or deployment pressure.
7. Name consumers and integration paths.
8. State required quality attributes as concrete scenarios (QAS).
9. Inspect coupling: strength, distance, volatility, leakage, coordination cost (CPL).
10. Identify candidate **logical** boundaries by information hiding and change containment.
11. Detect signals (§5) and compose only those axes.
12. Establish the smallest credible structure. Compare normally 2 to 3 serious alternatives, **including doing less**.
13. Distinguish logical boundaries from physical distribution. Add abstraction, messaging, or distribution only for
    named pressure. Select named patterns last.
14. Check leaks, cosmetic or hallway folders, duplicated knowledge, split invariants, and shared mutable data.
15. Run the applicable tests (§12) with representative change, failure, and operational scenarios.
16. Produce the required artifacts, including REVISIT triggers (§13), and run the compliance audit (§13.4).

Never start with "use DDD + CQRS + Hexagonal" or "one module, boundary, or deployable per feature."

---

## §3 Vocabulary (do not collapse these)

| Term                                | Question it answers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
|-------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Flow**                            | How does a scenario unfold **end to end through time**, from an intent or stimulus to an observable outcome? A flow may compose several features/use cases, capabilities, and owners. It is a behavioral/temporal view by default; it becomes executable application logic only when it owns real coordination such as sequencing, branching, failure handling, or lifecycle decisions. It does not take ownership of the underlying business truths or invariants.                                                                                                                                                                                                                                                              |
| **Feature / use case / behavior**   | What concrete user, operator, or system goal is being accomplished? (`PlaceOrder`, `RefundPayment`, `RegisterUser`). In this governance these terms may be used together when they name one observable behavior; do not confuse that behavior with the wider temporal Flow that may compose several of them.                                                                                                                                                                                                                                                                                                                                                                                                                     |
| **Capability**                      | What stable ability does an owner possess or provide so one or more behaviors can be realized? A Capability describes **what the owner can do**; it is distinct from **Owner knowledge / model** (what the owner knows or holds as authoritative truth/state) and from a **Contract** (what consumers are intentionally allowed to rely on across a boundary). A capability may use owner knowledge and policy to perform that ability. It may be private or public; it is not automatically a module, public contract, folder, service, or neutral technical mechanism. Promote a local ability to owner level only when the identity of knowledge and reason for change make the sharing honest (STR-17).                      |
| **Implementation**                  | How is a behavior or capability concretely realized against a technology, protocol, storage system, vendor, or external resource? In the business area, an Implementation is private and owner-specific. **Technical boundary validation** of syntax, shape, schema, encoding, protocol version, provider payload, signature/checksum format, or other external representation MAY live with that Implementation. **Business/domain validation**, policy, invariants, calculations, eligibility decisions, and authoritative knowledge remain with the responsible owner/behavior and MUST NOT be moved into the Implementation. Neutral runtime/infrastructure mechanisms remain at the technical edge (CMP-6, STR-18, STR-19). |
| **Vertical Slice**                  | What organizational code form realizes one concrete feature/use case/behavior end to end inside its owning module? Not automatically an owner, Flow, bounded context, aggregate, service, or deployment unit.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **Entry point**                     | Which file receives the outside stimulus for a behavior? The thin file that accepts a transport (HTTP, CLI, consumer) and invokes the slice's behavior. Named after the behavior, not after its technical role.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| **Owner**                           | Who is the single authority to change this knowledge, invariant, or lifecycle? Realized in code as a module (or, when distribution is earned, a service). "Inside one owner" means inside that module.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| **Owner knowledge / model**         | The semantic authority over the shared authoritative knowledge, state, and invariants of an owner. A responsibility, **not** a required folder, class, aggregate, layer, or rich domain model.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **Authoritative knowledge / state** | The single source of truth for a fact or rule. Everything else derives from it or asks it.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| **Derived data**                    | A projection, read model, cache, replica, search index, analytics copy, or integration view. Rebuildable, with declared provenance, semantics, and freshness.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **Invariant**                       | A condition that must hold regardless of which entry point or flow runs.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **Reason for change**               | The actor, rule source, or external force whose decision triggers modification (a regulator, a pricing team, a vendor API).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| **Mechanism**                       | A reusable technical ability with no business meaning (`Clock`, `Filesystem`, `Tracing`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| **Policy / meaning**                | The business rule, threshold, semantic mapping, invariant, or authoritative decision that gives a mechanism purpose. It belongs to the responsible owner, or to an explicit hard constraint when no business owner defines it.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **Business navigation**             | The primary physical path through product code remains owner -> behavior. A complementary explanatory path MAY read Flow -> Feature/Use Case -> Capability -> Implementation so a newcomer can follow the business story before technical realization. Owner-internal technical details remain subordinate inside that owner rather than becoming the system's primary taxonomy.                                                                                                                                                                                                                                                                                                                                                 |
| **Owner-internal technical area**   | A private technical subarea inside one business module for **business-specific** implementation or translation whose scope belongs to the owner rather than to one behavior. Typical examples are an owner-specific `Stripe/`, `Persistence/`, or `Projections/` area when the same technical knowledge, boundary, or capability genuinely serves several behaviors or is semantically owner-wide. When several such realizations exist, they MAY be grouped under an optional `Implementations/` area per STR-19. It remains part of the business owner's topology, not the neutral technical edge.                                                                                                                             |
| **Slice-private technical area**    | A private technical subarea inside one Vertical Slice for **business-specific** implementation that serves only that behavior. It MUST NOT become a reusable library for other slices. If the same knowledge, boundary, or capability becomes genuinely owner-wide, promote that knowledge to owner/module level under STR-17 rather than importing another slice's private implementation. Several slice-private realizations MAY be grouped under an optional `Implementations/` area per STR-19; a small detail MAY remain as files directly in the slice.                                                                                                                                                                    |
| **Technical placement**             | The physical organization of implementation details after ownership is known. It is chosen for locality, information hiding, readability, and enforcement; placement alone is not evidence of a boundary. A responsibility concept earns its own area when the current area's definition no longer honestly describes its members, or when a distinct ownership/change-cadence boundary is established for a real reason for change; team headcount alone is not sufficient (TEAM-3). Merge or remove the area when that reason disappears and the simpler placement is again honest.                                                                                                                                            |
| **Runtime responsibility**          | A neutral **driving** mechanism that receives a stimulus or manages execution lifecycle and dispatches toward application entry points (for example a router engine, request/response machinery, or process lifecycle). Business route mappings and composition wiring are not runtime mechanisms merely because they execute at startup.                                                                                                                                                                                                                                                                                                                                                                                        |
| **Infrastructure responsibility**   | A neutral **driven** mechanism that application code calls to interact with an external technical resource (for example connection/transaction machinery, filesystem access, or a generic HTTP/SMTP/Redis client). Owner-specific schema, policy, and semantic translation are not neutral infrastructure.                                                                                                                                                                                                                                                                                                                                                                                                                       |
| **Operability**                     | A cross-cutting quality concern for observing and controlling operation (for example logging, metrics, tracing, health, rate limiting, feature flags). It is not automatically a layer or folder: neutral machinery is placed by its mechanism, while thresholds, meanings, and behavior-changing policy stay with their owner or recorded constraint.                                                                                                                                                                                                                                                                                                                                                                           |
| **Module**                          | A general code boundary that hides one or more design decisions behind a small surface. A module may be business-facing or technical.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| **Business Module**                 | A code, knowledge, and ownership boundary for business language, state, authoritative knowledge, and invariants.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| **Bounded Context**                 | A semantic, model, and language applicability boundary. A business module MAY realize one; several business modules MAY live inside one wider bounded context; one MAY be wider than a single physical boundary. Not automatically a microservice. Not "a module folder exists."                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| **Boundary**                        | A line across which a private design decision can change without the other side changing, except through an intentional contract.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| **Contract**                        | The part of a boundary that outlives private decisions: what consumers actually rely on.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **Logical boundary**                | Ownership separation inside one process or deployable.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| **Physical boundary**               | Trust, deployment, scale, failure, lifecycle, or ownership separation that requires a process, network, or service split.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| **Composition root**                | The single place where the object graph is assembled.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| **Internal DSL**                    | A small domain-specific language expressed through the host programming language so callers can state domain intent without coordinating implementation details.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| **Fluent API**                      | An API whose calls can be chained into a readable sequence. Fluent syntax is a technique, not a quality by itself and not automatically a DSL.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| **Screaming Fluent API**            | This governance's term for an optional business-facing internal DSL style where the caller reads business actions and valid domain sequencing while technical implementation details remain private.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| **Architectural Event**             | See §0.4.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| **ADR / Override / REVISIT**        | See §13.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |

In §§4–§11, unqualified **module** means **business module** unless the text explicitly says technical module or
mechanism. Prefer the precise terms **Flow** and **Feature / use case / behavior** according to their distinct
definitions above, plus owner, business module, function, or mechanism, over ambiguous umbrella terms.

**Naming.** Business module/folder = owner; slice/folder = behavior; file/class = responsibility or authoritative
knowledge; function/method = exact action. This hierarchy describes **meaning**, not a mandatory number of directory
levels. Prefer `placeOrder()` over `handle()`, `Service`, or `Manager`. Use the system's own domain language (ubiquitous
language), not generic labels. **Business language outside; technical precision inside.**

**Explanatory shorthand.** Documentation and design discussion MAY use the mnemonic **DO -> CAN -> realized VIA
Implementation**: `DO` = one Feature/Use Case/Behavior, `CAN` = a stable Capability, and `VIA` = its concrete
owner-specific Implementation. These words are explanatory grammar, not required folder names, package names,
interfaces, or layers. `Flow` sits above that sentence as the temporal end-to-end story that composes behaviors and
capabilities through time.

---

## §4 Laws

Each rule is cited by ID in ADRs and compliance statements. MUST rules are mandatory; SHOULD rules are defaults.

### BEH: Behavior

- **BEH-1** The agent MUST start from observed end-to-end behavior, tracing representative flows from stimulus to
  observable effect, before proposing structure.
- **BEH-2** A flow is a **reasoning lens** that reveals dependencies, state, policy, failure points, and ownership. A
  processing step is not automatically a module.
- **BEH-3** If steps A, B, and C rely on one policy or invariant, they MUST NOT be split into "independent slices" that
  each own or duplicate it. Design decisions and knowledge often cut across temporal steps (information hiding).
- **BEH-4** Proposed slices MUST be tested against knowledge, invariants, and reasons for change before they are
  promoted to boundaries.
- **BEH-5** Vertical Slice is an outcome when evidence supports it, not an architecture imposed by pattern name. The
  recorded default for organizing behavior inside a module (CMP-1) applies where the reasoning sequence yields no other
  signal, and it never overrides BEH-3 or CMP-2. One physical module per use case or per step MUST NOT be required.
- **BEH-6** Code that participates in one request but belongs to different stable owners MUST NOT be forced into one
  module for convenience.
- **BEH-7** A Flow is an end-to-end temporal view first. When it has no independent coordination responsibility, it MAY
  exist only as an architecture view, documentation, trace, or end-to-end proof. It SHOULD become executable application
  logic only when it owns real sequencing, branching, failure handling, or lifecycle decisions. Even then, business
  meaning, authoritative state, policy, and invariants remain with their responsible owners; the Flow coordinates them
  rather than re-owning them.
- **BEH-8** When a Flow acquires durable state, waiting, retries, timeouts, compensation, recovery, or a lifecycle that
  must survive process failure, treat it as a candidate Workflow / Process / Saga / Durable Process and apply the
  applicable §7 and §11 obligations. Its state and lifecycle MUST have an explicit owner; do not hide them in a generic
  orchestration bucket.

### SCR: Screaming Architecture

- **SCR-1** High-level structure SHOULD foreground business intent over framework mechanics, so that the product can be
  understood before the framework.
- **SCR-2** Screaming Architecture is a **visibility test**, not a decomposition algorithm. It does not decide ownership
  boundaries and does not mean every business verb gets a module, boundary, service, aggregate, or deployable.
- **SCR-3** Framework and infrastructure concerns remain subordinate implementation details where practical.
- **SCR-4** CMP-3 to CMP-5 are the default expression of SCR-1 and STR-4 and add no further obligation.
- **SCR-5** **Business language outside; technical precision inside.** A caller-facing business API SHOULD expose the
  business action, decision, query, or result (`place()`, `authorize()`, `reserve()`, `refund()`) rather than the
  internal orchestration, persistence, framework role, or transport steps used to implement it. Inside that boundary,
  implementation names MAY and SHOULD be technically precise where that improves correctness and maintenance.
- **SCR-6** Naming SHOULD preserve a semantic descent: **business module/folder -> owner; slice/folder -> behavior;
  file/class -> responsibility or knowledge; function/method -> exact action**. Do not add folders, wrappers, or classes
  merely to satisfy this visual hierarchy; the hierarchy is about meaning, not directory depth or object count.
- **SCR-7** A **Screaming Fluent API** MAY be used when method chaining makes a real business sequence easier to read
  and the sequence itself is part of the domain meaning. The chain SHOULD read like a valid business sentence and each
  step SHOULD name a domain action, decision, state transition, or query. Fluent syntax is never required and MUST NOT
  be introduced merely for aesthetics.
- **SCR-8** A business-facing API, fluent or not, MUST NOT force callers to coordinate accidental implementation
  workflow. Chains such as `validate()->persist()->publish()` or `load()->map()->save()` leak technical sequencing when
  the caller only means `place()` or `refund()`. When ordering is an invariant, the owning object or behavior SHOULD
  enforce it; when ordering is merely implementation detail, it MUST remain hidden behind the business action.
- **SCR-9** Where the concepts genuinely exist, architecture documentation, navigation aids, and onboarding SHOULD let a
  competent engineer follow the product progressively as **Flow -> Feature/Use Case -> Capability -> Implementation**
  while still showing the responsible Owner. This is a readability/testability view, not a mandate to create `Flows/`,
  `Features/`, `Capabilities/`, or `Implementations/` folders.

### CMP: Baseline composition

The baseline composition (§1.6) is a default, not a mandate: it applies where no recorded signal calls for a different
shape, and it is not a technique for §5 purposes.

- **CMP-1** Where no recorded signal calls for another shape, the system SHOULD be composed as a modular monolith:
  business modules drawn by ownership, behavior inside each module organized primarily as vertical slices, and
  business-intent names throughout the business area. One module is a valid result when the system has one business
  owner. The baseline is a candidate, not an added technique: §5 signal requirements apply to departures from it and to
  mechanisms layered onto it, not to the baseline itself. Giving a slice its own module, a boundary, or a heavier
  technique needs a signal. Each important boundary still needs its Boundary Register entry (BND-4) and each
  consequential mechanism its Complexity Ledger entry (CPX-6). A departure SHOULD be recorded in one line, in the Short
  Decision Note or ADR of the change that departs. Applying the baseline uniformly where the problem is not uniform is
  failure mode 1 (§0.1, CPX-7).
- **CMP-2** A **business module** MUST represent a business owner of language, state, authoritative knowledge, and
  invariants, not a technical layer or an arbitrary folder. A business module is not automatically a bounded context
  (DOM-7). A slice MUST NOT become a bounded context automatically (§3), and MUST NOT own or reimplement an invariant or
  knowledge that belongs to the business module or that other slices rely on (BEH-3, BND-6, INV-4). Shared business
  rules, authoritative state, and invariants MUST be held once, at business-module level, available to all slices, and
  not inside any one slice. **Conversely, distinct authoritative knowledge or independent reasons for change MUST NOT be
  merged into one owner merely to avoid an in-process dependency, public contract, or local transaction. Cross-owner
  collaboration is a cost to evaluate, not proof that the ownership boundary is wrong.**
- **CMP-3** A slice, its entry point, and its view SHOULD be named for the behavior or content they present, in the
  system's own language (`ProductList`, `PlaceOrder`, `ApproveRefund`). The entry point SHOULD carry the slice's name,
  and the view SHOULD sit beside it. When a slice also needs a separate orchestration class (CMP-7), that class SHOULD
  carry a different business name, so that two classes do not share one name.
- **CMP-4** The business area SHOULD NOT encode technical roles in names or folders, such as Controller, Handler,
  Repository, Service, Manager, Helper, Util, or Factory as suffixes or organizing folders. A collection SHOULD be named
  for what it holds (`Accounts`, `ActivityLog`), not for how it is stored. These words are smells, not banned words: a
  term that is a genuine domain term (a `Provider` in a travel domain) MAY be kept through a project business allowlist.
- **CMP-5** The technical role of a business entry point (HTTP endpoint, console command, consumer) MAY be declared or
  registered at the edge. The business name remains primary. Prefer registration at the composition root or route table
  when it is equally simple. A direct, small technical contract dependency (for example `implements Endpoint`) is
  acceptable when it prevents a meaningless pass-through wrapper, and is not a boundary violation, provided the contract
  carries no business meaning and no transport, framework, or storage types enter owner knowledge (STR-5, DEP-3, DEP-5).
  If the transport becomes a real volatility, treat it as a port (C-01). The goal is semantic clarity, not naming
  purity: replacing `ProductListController` with `ProductList implements Endpoint` as a new mandatory ceremony is not
  the intent.
- **CMP-6** Generic technical mechanisms and technical vocabulary (contracts such as `Endpoint`) MUST remain at the
  technical edge and MUST be business-neutral. **Mechanism is neutral; meaning is ownership:** connection / transaction
  machinery, routing engines, retry algorithms, rate-limit engines, flag evaluators, logging, clocks, and similar
  mechanisms **are neutral technical machinery and MAY live at the technical edge**, while the schema, atomicity
  boundary, retry policy, limit, flag meaning, emitted business event, and other business semantics belong to the
  responsible owner or an explicit hard constraint. Runtime responsibilities are neutral **driving** mechanisms;
  infrastructure responsibilities are neutral **driven** resource mechanisms (§3). These are responsibility concepts,
  not mandatory folders (STR-2, STR-3). Neutral technical mechanism areas MUST NOT depend on business modules. The
  **composition root is the explicit wiring exception**: it MAY see/import business and technical code solely to
  assemble or register them; a router engine may be runtime, while the route table or registration that names concrete
  business entry points belongs to composition/configuration. Business semantics never move to a technical area merely
  because they use SQL, HTTP, a queue, filesystem, telemetry, or another mechanism. Module-specific adapters and other
  business-specific technical details stay inside their owner (STR-10, DEP-1) unless the extracted part is genuinely
  neutral technical machinery. A business collection (`Accounts`, `ActivityLog`) is part of its owner's language: its
  storage implementation stays private to the owner, and storage/provider types MUST NOT appear in its public signatures
  (DEP-5). Technical areas MUST NOT become `Common`, `Shared`, `Utils`, `Core`, `Foundation`, or another catch-all
  (STR-1, STR-3). A short vocabulary note SHOULD map familiar technical terms (Controller, Handler, Repository) to how
  they appear in the business area.
- **CMP-7** The part of an entry point that translates transport SHOULD stay thin (STR-5). Business rules and invariants
  belong to owner knowledge (INV-2), not to the entry point. Orchestrating one behavior across owners, through their
  contracts, MAY live in a class with a business name (`PlaceOrder`), without creating a Handler or Service. Where the
  behavior is trivial, the entry point and the behavior MAY be one file, as long as transport translation remains
  separable. A wrapper whose only job is to forward MUST NOT be added (HID-2).
- **CMP-8** Because roles are not in names, the project SHOULD keep one authoritative, navigable list of entry points: a
  route table or registry that is itself the registration, not a hand-maintained copy. REVISIT when newcomers repeatedly
  ask where the entry points are.
- **CMP-9** A slice MUST NOT depend on another slice's **private implementation** or use another slice as a reusable
  library, and slice-to-slice dependency cycles MUST NOT exist. Shared business rules and invariants belong to owner
  knowledge (CMP-2). Reusable module-level behavior that is not authoritative domain knowledge MAY live at module level
  under a business name. Inside one business module, one slice MAY invoke another slice's intentionally exposed behavior
  when that represents real business composition, does not create a cycle, and does not leak workflow internals. A
  business module's public contract MAY intentionally expose one slice's behavior directly when that is the smallest
  honest surface; a facade whose only purpose is to hide that fact MUST NOT be added (HID-2). Cross-module use goes
  through the other business module's public contract (STR-7). Orchestrating several owners is itself a slice of the
  business module that owns the use case.
- **CMP-10** Owner knowledge is a **semantic responsibility, not a required folder, class, aggregate, layer, or rich
  domain model.** Where complex invariants or shared business knowledge exist, they MUST be held once, at module level,
  and shared by its slices (INV-2). Where a module is simple CRUD or orchestration with no shared domain knowledge,
  `Domain/`, `Model/`, an aggregate, or rich-domain ceremony MUST NOT be added without a signal (DOM-2, DOM-4, CPX-7,
  HID-3), and slices MAY use plain persistence directly.

### BND: Boundaries

- **BND-1** A boundary is real only when it protects a meaningful difference in one or more of: authoritative knowledge
  or policy; invariant or consistency responsibility; ubiquitous language or semantic model; business ownership; reason
  or rate of change; trust or privilege; lifecycle; transaction or consistency requirements; deployment or scaling
  requirements; failure domain; external or vendor volatility.
- **BND-2** A folder, namespace, class, interface, process, service, repository, `Core` directory, team name, or
  database schema is **not evidence of a boundary by itself**.
- **BND-3** A useful boundary lets one side change a private design decision without forcing the other side to
  understand or change, except through an intentional contract.
- **BND-4** Every important boundary MUST have a recorded rationale in the Boundary Register (§13.2) naming what it
  protects.
- **BND-5** Start with a **logical, in-process boundary**. Promote it to a process, service, or network boundary only
  per DST.
- **BND-6** One invariant MUST NOT be split across slices merely to preserve slice independence. If several behaviors
  require the same invariant or authoritative knowledge, it is held once at module level, shared by those slices, and
  not inside any one of them.
- **BND-7** The **contract** SHOULD be defined by what consumers actually rely on, kept small, and versioned when
  consumers are external or independently deployed.
- **BND-8** For each candidate boundary, answer: What knowledge is hidden on each side? Which invariant belongs to one
  authoritative owner? Which changes should propagate and which stop here? Does the consumer need behavior and results,
  or does it know private representation? Is a stronger runtime boundary solving a real trust, deploy, scale, lifecycle,
  or failure problem?
- **BND-9** Physical location and dependency direction are separate decisions. Colocating policy and a private technical
  detail does not make them the same concern, and placing files in different folders does not create a boundary.
  Package/module/folder placement MAY participate in enforcement when the language or build system gives it real
  visibility/import semantics, but it is never evidence by itself (BND-2). Every consequential boundary claim MUST have
  an honest current enforcement status under ENF-7 to ENF-9; a boundary claimed as machine-enforced requires a live
  check that can actually fail when the boundary is crossed.

### KNW: Knowledge and state

- **KNW-1** Authoritative state MUST live with the component that has enough knowledge and authority to enforce the
  decision or invariant it supports. State placement follows responsibility, not convenience.
- **KNW-2** The classes of data MUST be distinguished: authoritative state; derived projections or read models; caches;
  replicas; search indexes; analytics copies; integration or event views.
- **KNW-3** Derived data MAY be stale or rebuildable under an explicit contract. It MUST NOT silently become a second
  authority.
- **KNW-4** Multiple owners MUST NOT independently mutate the same logical invariant. If two owners both need write
  authority, either the ownership boundary is wrong, or an explicit coordination protocol is part of the problem and
  MUST be justified in an ADR.

### INV: Invariants

- **INV-1** Important invariants MUST be written in domain language **before** structure is chosen. Examples of the
  form: an order cannot be confirmed without a valid payable total; a payment capture cannot exceed the authorized
  amount.
- **INV-2** The authoritative state and the decision that enforce a strong invariant SHOULD live inside one ownership
  and transaction boundary.
- **INV-3** A pre-check in another module is not enforcement if the authoritative write can race it.
- **INV-4** An atomic business invariant MUST NOT be split across "independent slices" and then reconstructed with
  retries, events, sagas, or distributed locks merely to preserve a diagram.
- **INV-5** If an invariant genuinely spans owners, the ADR MUST state the weaker guarantee or the explicit coordination
  mechanism and pay its cost consciously (CNS).

### CPL: Coupling

- **CPL-1** Coupling MUST NOT be minimized blindly. The question is whether it is **appropriate**.
- **CPL-2** Coupling SHOULD stay strong and close when elements implement the same invariant; share authoritative
  knowledge; have the same reason and rate of change; must participate in the same consistency boundary; or would
  otherwise communicate through a broad, chatty, or leaky interface.
- **CPL-3** Coupling SHOULD be reduced when a dependency crosses independent ownership or volatility; semantic or model
  boundaries; trust or privilege boundaries; deployment or failure boundaries; external or vendor boundaries; or a large
  physical or organizational distance.
- **CPL-4** Judge coupling by `integration strength x distance x volatility`, plus knowledge, change propagation, and
  operational consequence. Do not judge by counting imports or arrows.
- **CPL-5** A strong local dependency between cohesive elements can be cheaper than a weak-looking distributed
  dependency whose contract, retries, consistency, versioning, observability, and coordination costs are hidden.
- **CPL-6** A direct dependency can be correct when stable, local, and honest. An interface is not automatically
  cleaner. An event is not automatically more decoupled. Duplication can be cheaper than a false shared abstraction. A
  shared helper is harmful when similar behaviors carry different knowledge or reasons for change. A central shared
  module can become a coordination hub.
- **CPL-7** Coupling is harmful when unrelated owners, volatile components, trust domains, teams, deployables, or
  failure domains repeatedly require synchronized change.
- **CPL-8** Version-control co-change is **evidence, not proof**. Mechanical and bulk changes (formatting, mass renames,
  generated code, dependency bumps, squashed commits, cross-cutting migrations) MUST be excluded, and the reason for the
  co-change inspected before ownership is moved.

### HID: Information hiding

- **HID-1** A module SHOULD hide meaningful decisions behind a smaller, more stable surface and reduce what callers must
  know. Prefer deep modules over many shallow wrappers.
- **HID-2** Layers that merely forward the same parameters (`Controller -> Service -> Manager -> Repository -> Adapter`)
  without changing abstraction or hiding knowledge MUST NOT be created.
- **HID-3** An abstraction earns its place only when it hides significant complexity, protects a real volatility, trust,
  or ownership boundary, or gives callers a meaningfully simpler model.
- **HID-4** If every implementation detail leaks through the contract, the boundary is cosmetic regardless of how many
  interfaces surround it.

### ABS: Abstraction

- **ABS-1** Abstract only when multiple consumers truly depend on the same stable concept, or when a real boundary needs
  a narrower contract.
- **ABS-2** Code MUST NOT be extracted merely because two pieces look alike. Extract when they represent the **same
  knowledge** and are expected to change together.
- **ABS-3** Before abstracting, answer: What knowledge is actually common? Will these consumers change for the same
  reason? Which volatility does it protect? Does the interface hide complexity or mirror it? Is duplication currently
  cheaper and more honest?
- **ABS-4** Prefer the wrong duplication over the wrong abstraction while concepts are not proven to be one thing.
  Consolidate when the shared reason for change becomes clear.

### DOM: Domain modeling

- **DOM-1** Modeling depth follows business-logic complexity.
- **DOM-2** Use a straightforward flow or transaction script when the work is mainly input validation, orchestration,
  persistence, and a few obvious rules.
- **DOM-3** Use richer domain modeling when non-trivial invariants, lifecycle transitions, business terminology, and
  decisions need one authoritative model and would otherwise be duplicated across entry points.
- **DOM-4** DDD is not a requirement for "serious" software. Tactical DDD wrappers around CRUD fields MUST NOT be added
  without a signal.
- **DOM-5** CQRS and Event Sourcing are optional and MUST be earned (§11).
- **DOM-6** Aggregates protect real invariants; they do not imitate book diagrams.
- **DOM-7** Bounded contexts come from semantic and language differences, not from nouns or service names. A module MAY
  realize a bounded context; several modules MAY live inside one wider bounded context; a bounded context MAY be wider
  than one physical boundary; and it is not a synonym for a microservice.

### DEP: Dependencies, ports, adapters

- **DEP-1** Use ports and adapters (or an equivalent) where owned business or application code would otherwise absorb
  details of a real external, volatile, privileged, or replaceable mechanism: payment provider, third-party API, message
  broker, filesystem, identity provider, hardware, vendor SDK, infrastructure API.
- **DEP-2** An interface MUST NOT be created for every class "for testability." Testability comes from controllable
  boundaries around real effects and nondeterminism (TST).
- **DEP-3** Keep framework-specific details at the edges when that materially protects business or application
  knowledge.
- **DEP-4** Pass-through layers whose only purpose is to make a diagram resemble Clean, Hexagonal, or Onion MUST NOT be
  added.
- **DEP-5** A port does not require two adapters. Provider, ORM, and framework types MUST NOT leak through a boundary
  unless the boundary intentionally owns that technology contract.
- **DEP-6** A port, seam, or adapter SHOULD exist only when it protects a named difference or materially controls an
  effect: external or vendor model leakage; failure semantics; trust or privilege; nondeterminism; meaningful
  volatility; independently changing lifecycle; a narrower consumer contract; multiple real implementations; or a
  material test seam around an external, slow, privileged, or nondeterministic effect. The port belongs to the side
  whose language and needs it expresses. Physical colocation of port and adapter is allowed; folder separation is not
  proof of a boundary. Vendor SDK imports SHOULD be constrained to the owning adapter/boundary where the ecosystem can
  enforce that cheaply.

### DST: Distribution

- **DST-1** Logical ownership MUST be separated from physical distribution in every design. A boundary may be strong
  while remaining in one process and one deployable.
- **DST-2** Moving a boundary onto a network adds permanent costs: latency, partial failure, versioned contracts,
  observability, security surface, deployment coordination, distributed data consistency, retries, idempotency, and
  on-call burden. These MUST be named in the ADR.
- **DST-3** A **modular monolith** is the strong default candidate when internal ownership boundaries matter and
  independent deployment, failure, or scale ownership is not yet required. It is not a religion: one business module may
  be extracted earlier when evidence already exists.
- **DST-4** A service or process boundary MUST be justified by a verified need for independent deployment, scaling,
  trust, runtime, failure isolation, lifecycle, or ownership, often enough to pay the permanent cost.
- **DST-5** "Large project", "enterprise", "future scalability", team fashion, resume value, and diagram symmetry are
  NOT sufficient reasons.
- **DST-6** A network boundary MUST NOT be created to solve a logical ownership or naming problem that an in-process
  module can solve.
- **DST-7** Services MUST NOT share a mutable database. That produces a distributed monolith with the cost of both
  worlds.
- **DST-8** Once distribution is chosen, the obligations in §7 and §8 apply.

### CPX: Complexity

- **CPX-1** Every additional abstraction, layer, interface, domain model, port, adapter, event, queue, broker, cache,
  CQRS split, saga, distributed transaction, service, datastore, deployment unit, orchestrator, workflow engine, lock or
  lease, consensus mechanism, or platform dependency MUST answer, in writing at L2 and above (at L0 and L1 the questions
  are asked silently):
    1. What concrete pressure requires it?
    2. What failure, change, or operating cost does it prevent?
    3. What evidence shows that pressure exists **here**?
    4. Why is the simpler alternative insufficient?
    5. What new recurring complexity and failure modes does it introduce?
    6. How will we validate that the choice was correct?
    7. What evidence would make us remove, merge, or reconsider it?
- **CPX-2** No named architecture pattern is an acceptance criterion.
- **CPX-3** Pressure MUST be classified as known or hypothesized (§1.4). Hypothesized pressure earns a REVISIT trigger
  only.
- **CPX-4** Complexity is measured at the **whole-system** level, over the expected life, including operational and
  cognitive cost.
- **CPX-5** "Do less" MUST be considered as a serious alternative whenever legitimate.
- **CPX-6** The Complexity Ledger (§13.2) MUST list every consequential mechanism (§0.4 (h)) with its pressure and its
  recurring cost. Small local abstractions inside one owner need no entry.
- **CPX-7** Symmetry is not a quality attribute. The architecture SHOULD look uneven when the problem is uneven.
- **CPX-8** "Smallest adequate" is **not** permission for tactical shortcuts that merely defer already-known complexity.
- **CPX-9** Default when evidence is insufficient: in-process, one owner, plain orchestration, written REVISIT trigger.
- **CPX-10** If the signal that justified a mechanism disappears, reconsider it. Do not delete it mechanically.

### PAT: Patterns

- **PAT-1** Vertical Slice, Screaming Architecture, Clean, Hexagonal, Onion, DDD, CQRS, Event Sourcing, event-driven
  architecture, modular monolith, microservices, and sagas are tools and vocabulary.
- **PAT-2** Use only the parts whose signals are present. Combining every respectable pattern compounds ceremony rather
  than quality.
- **PAT-3** A legitimate design may be: behavior-oriented at the top level; modular around business knowledge; simple
  transaction scripts in low-complexity areas; richer models only where invariants require them; direct local calls
  within stable ownership; ports only around external or volatile effects; one deployable until independent runtime
  boundaries earn distribution.
- **PAT-4** Hexagonal is a boundary view, not a folder law. Banning or requiring the words Domain, Ports, or Adapters
  globally is forbidden.
- **PAT-5** **Direction check.** The justification for a decision MUST be statable without naming the pattern. "Ordering
  and payment rules must hold on every entry point, so one owner holds them" is a justification. "Because it is
  hexagonal" is not. If the reason cannot be said without the pattern's name, the pattern is premature.

### QAS: Quality attributes

- **QAS-1** Quality attributes determine how strong a boundary must be. For consequential choices, vague words MUST be
  translated into scenarios:
    - "fast" -> workload + latency or throughput target + percentile + environment;
    - "available" -> failure stimulus + allowed impact + recovery objective;
    - "scalable" -> load shape + growth and skew + target + resource envelope;
    - "secure" -> actor and trust boundary + protected effect + authorization or isolation expectation;
    - "maintainable" -> representative change + expected ownership and change propagation;
    - "independently deployable" -> one business module changes and deploys without coordinated release or data mutation
      elsewhere;
    - "recoverable", "auditable", "operable" -> a concrete drill, record, or runbook outcome.
- **QAS-2** A scenario has: source, stimulus, environment, artifact, expected response, measurable threshold.
- **QAS-3** Claims SHOULD have executable or observable evidence where practical (§12 test 8).
- **QAS-4** Thresholds MUST come from requirements or measurement. Otherwise they are ASSUMPTIONs (AI-14).

### CHG: Change

- **CHG-1** Optimize for expected change, not abstract purity. Ask: "If this business rule changes tomorrow, where
  should the edit happen?" Then trace the real required edits.
- **CHG-2** A good boundary keeps one reason for change local. Warning signs: one business change touches many unrelated
  areas; the same rule changes in several slices or services; consumers know private persistence or framework
  representation; "independent" modules need repeated coordinated releases; a shared library changes for unrelated
  owners; a service boundary exists but both services change together most of the time.
- **CHG-3** The goal is to stop **unnecessary** propagation. Zero propagation is not a goal; shared contracts and real
  invariants create legitimate coupling.

### TEAM: Ownership and teams

- **TEAM-1** Team shape and cognitive load are contextual forces. They become hard constraints only when fixed by
  regulatory, contractual, security, or organizational authority (§2.1).
- **TEAM-2** Where services or modules are distributed across teams, each owner SHOULD be operable end to end by one
  team, including its data and operations.
- **TEAM-3** Two teams repeatedly blocked on the same code is a signal to split along their distinct reasons for change.
  Code MUST NOT be reorganized merely by headcount.
- **TEAM-4** A module owned by many teams with no clear authority is a smell. Record the single accountable owner.

---

## §5 Technique selection (signals compose; do not climb a ladder)

A technique MAY be added only if its signal is present, **verified and recorded as a FACT or hard constraint with its
source**. An asserted or imagined signal is not evidence. If the signal dies, reconsider the technique; do not delete it
mechanically (CPX-10). Detailed decision cards are in §11.

The baseline composition (§1.6) is not a technique for this section: its signal requirements apply to **departures from
the baseline** and to **mechanisms added onto it**.

| Signal                                                                                 | Consider                                                                                                             | Not a reason                                 |
|----------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|----------------------------------------------|
| Simple orchestration, no real invariants                                               | Transaction script / plain orchestration                                                                             | "enterprise shape"                           |
| Invariants or lifecycle must hold on every entry                                       | Rich domain model / explicit consistency owner                                                                       | copying DDD tactics                          |
| Several behaviors share one invariant or knowledge owner                               | Owner knowledge held once at module level, shared by those slices                                                    | forced slice independence / global `Shared/` |
| Independent behavior + independent knowledge and change                                | Separate module with its own owner knowledge and boundary (a slice per behavior is already the baseline form, CMP-1) | one module or boundary per endpoint          |
| Read and write shape or ownership actually diverge                                     | CQRS                                                                                                                 | "CQRS is our standard"                       |
| Multi-step coordination in one use case                                                | Explicit flow                                                                                                        | handler ceremony                             |
| Work spans time, retries, services                                                     | Saga / state machine                                                                                                 | workflow engine for one local transaction    |
| Volatile, external, or privileged implementation                                       | Port + adapter **at that boundary**                                                                                  | "every dependency needs an interface"        |
| Slow, nondeterministic, or external dependency that tests must control                 | A seam at that dependency                                                                                            | interface for every collaborator             |
| Past events **are** the source of truth                                                | Event sourcing                                                                                                       | audit log                                    |
| Who/what/when needed, current state still authoritative                                | Append-only audit                                                                                                    | event sourcing                               |
| Real consumer outside the owner                                                        | Smallest public contract, versioned if independent                                                                   | speculative microservices                    |
| Semantic language or model genuinely differs                                           | Bounded context / translation boundary                                                                               | "a domain folder exists"                     |
| Independent deploy, scale, failure, or trust ownership required                        | Physical service or process boundary                                                                                 | "microservices are enterprise"               |
| Neutral technical reuse with a stable name                                             | Named mechanism (`Tracing`)                                                                                          | `Utils` / `Common`                           |
| Files in different areas repeatedly change together (co-change, after CPL-8 filtering) | Re-examine cohesion; merge or move ownership                                                                         | aesthetic refactoring                        |
| Two teams repeatedly blocked on the same code                                          | Split along their distinct reasons for change                                                                        | reorganizing by headcount                    |
| Measurable architecture property must remain true                                      | Fitness function / automated check                                                                                   | ceremony for subjective prose                |

Audit is not event sourcing. A port does not require two adapters. Hexagonal is a boundary view, not a folder law. A
bounded context is not automatically a microservice. A Vertical Slice is not automatically an ownership boundary.

---

## §6 Structure rules

- **STR-1** No cosmetic folders. No hallway folders (`Core/Common/Shared/Services/...`).
- **STR-2** A root exists only with a real owner or a runtime or governance reason.
- **STR-3** Do not pre-create `Shared/`, `Common/`, `Core/`, `Foundation/`, `Infrastructure/`, `Utils/`. Create them
  only with a named owner or reason.
- **STR-4** Top-level structure SHOULD reveal business intent before framework mechanics where that improves navigation
  and ownership (SCR).
- **STR-5** The public surface (CLI, HTTP, consumer) is **thin**: translate transport, then invoke the slice's behavior.
  The slice executes that behavior. The composition root **assembles**. Runtime code MUST NOT create infrastructure
  collaborators directly or service-locate them.
- **STR-6** Inside one owner, slices MAY share that owner's knowledge, invariant logic, deep modules, and intentional
  module-level behavior. Slices MUST NOT import one another's private workflow helpers or form dependency cycles
  (CMP-9). Slice-to-slice composition is allowed only through an intentional surface under CMP-9.
- **STR-7** Across owners, communication is only through a public contract, an integration event, or a published read
  model. **Never** another owner's tables or private types.
- **STR-8** Dependency cycles between owners MUST NOT exist.
- **STR-9** Visibility defaults to private. Expose the smallest public surface that the contract requires.
- **STR-10** Adapters live beside the boundary they serve: inside the owner for its private dependencies, or in a named
  mechanism when reuse across owners is neutral and stable. A technical root such as `Infrastructure/` needs a real
  runtime or governance reason.
- **STR-11** Duplicate **knowledge** is forbidden. Similar-looking code MAY stay local if the reason for change is not
  the same (ABS-2).
- **STR-12** Prefer a deep module with a small stable interface over many shallow wrappers (HID-1).
- **STR-13** Do not create a network boundary for a logical ownership problem (DST-6).
- **STR-14** Renaming `Services/X` to `Features/X`, moving files, or performing a directory/package rewrite that changes
  no ownership, knowledge hiding, dependency direction, invariant placement, public contract, or runtime property is not
  an architectural improvement and MUST NOT be presented as one. Clean-looking structure alone is not architecture.
- **STR-15** A business module's exact public surface MUST have **one authoritative, machine-checkable definition** in
  code, language/package visibility, or a module manifest. Everything else is private to the module. The Boundary
  Register records what the boundary protects, its owner, and **where** the authoritative contract surface is defined;
  it MUST NOT manually duplicate the list of exported types (ENF-1).
- **STR-16** Primary business navigation SHOULD justify each folder, package, or module by answering **who owns this?**
  or **what business behavior does this realize?** A **neutral** technical area SHOULD name a real runtime or
  infrastructure responsibility/mechanism. A **business-owner-local** technical area is the explicit specialization
  governed by STR-18 and STR-19: it remains subordinate to that owner and must name or group a coherent owner-specific
  technical realization rather than become a generic organizational bucket. Generic buckets with no ownership, behavior,
  knowledge, or mechanism are smells and are prohibited where STR-1 to STR-3 apply.
- **STR-17** Code SHOULD remain in the **narrowest scope that contains all consumers of the same knowledge**. Knowledge
  needed only by one behavior stays private to that slice. The same authoritative knowledge, invariant, policy, or
  owner-specific capability used by several behaviors of one owner moves to owner/module level. Usage count, similar
  syntax, the same database technology, or the same table are not sufficient reasons to extract; identity of knowledge
  and reason for change decide (ABS-2, ABS-4, CHG).
- **STR-18** Business-specific technical implementation remains part of its business owner's structure. When its
  knowledge or boundary serves only one behavior, it is **slice-private** and stays inside that Vertical Slice. When the
  same technical knowledge, boundary, or owner-specific capability genuinely belongs to several behaviors or to the
  owner as a whole, it MAY be promoted to an **owner-internal technical area** at module level (STR-17). Concrete
  realizations such as `Stripe/`, `Persistence/`, `Search/`, `Webhooks/`, or `Storage/` MAY be introduced when they hide
  a coherent owner-specific body of technical complexity or a real boundary and materially improve navigation,
  information hiding, or enforcement. Both forms remain private by default, MUST NOT become cross-owner hallway folders,
  and SHOULD NOT be pre-created for symmetry or pattern conformity. Neutral technical areas are reserved for mechanisms
  with no business meaning (CMP-6).
- **STR-19** `Implementations/` is an **optional, earned umbrella** for several private owner-specific technical
  realizations at module level or inside one Vertical Slice. Its membership is narrow: concrete interactions or
  translations against a technology, protocol, storage system, vendor, or external resource (for example `Persistence/`,
  `PaymentProviders/`, `Search/`, `Messaging/`, `Storage/`, `Webhooks/`). Technical boundary validation of syntax,
  shape, schema, encoding, protocol, or provider representation MAY live with the relevant realization. Business rules,
  policy, invariants, calculations, **business/domain validation**, orchestration, owner knowledge, and generic helpers
  MUST NOT be placed there. A single small realization SHOULD stay direct rather than gain an empty hierarchy. Neutral
  business-agnostic machinery belongs to Runtime or Infrastructure responsibilities, not to a business owner's
  `Implementations/` area (CMP-6).

**Technical placement decision path (summary, not a folder template).**

1. If code expresses business meaning, policy, invariant, authoritative knowledge, owner-specific schema, or semantic
   translation, place it with that **owner**. One behavior -> slice-private; the same knowledge across behaviors or an
   owner-wide capability -> owner/module level (STR-17). Concrete owner-specific technical realizations stay beside that
   scope and MAY use an earned `Implementations/` area under STR-18/STR-19.
2. If code is completely generic and pure, first prefer the language, standard library, or a maintained library. Do not
   create `Foundation/`, `Core/`, or a generic primitives area by default (STR-3).
3. If business-neutral code **drives execution** or receives/dispatches stimuli, treat it as a **runtime
   responsibility**.
4. If business-neutral code is **driven by application code** to touch an external technical resource, treat it as an
   **infrastructure responsibility**.
5. Operability, security, reliability, and similar cross-cutting concerns do not choose placement by themselves: place
   the neutral mechanism according to its real responsibility, and keep business policy/meaning with its owner or
   explicit constraint.
6. The composition root is the wiring exception that connects business and technical sides. Keep its knowledge to
   assembly/registration, not business policy.
7. If a proposed separation cannot name the decision, knowledge, invariant, failure/trust boundary, lifecycle,
   team/deployment force, or other difference it protects, treat it as reversible code organization rather than
   architectural necessity (BND-1 to BND-3, STR-14).

**Promotion signals (observable, not numeric thresholds).** Promote code from slice-private placement toward
owner/module scope, or introduce an owner-internal technical area, only when the current placement is no longer the
narrowest honest scope. Useful signals include: the same authoritative knowledge or policy is genuinely used by several
behaviors; the same rule is being duplicated; behaviors repeatedly change together because of the same owner-wide reason
for change; one slice starts importing another slice's private implementation; an owner-specific capability or technical
boundary is clearly shared across behaviors; or navigation, information hiding, or enforcement materially worsens
because several coherent responsibilities are mixed at one level. **File/class/folder count and root size alone are not
promotion triggers.** Record the actual signal when the promotion is consequential; do not pre-create structure for
anticipated growth (STR-17, STR-18, CPX-3).

---

## §7 Distribution and asynchrony obligations

Once messaging, events, queues, workflows, or distribution are chosen (DST-8), all **applicable** obligations in this
section MUST be satisfied. A non-applicable subsection MAY be marked N/A once with a reason; individual boilerplate N/A
entries are not required. Apply the obligations in proportion to the mechanism actually chosen (P12). Choosing sync or
async is never a style preference.

### Communication choice

- **COM-1** Use **synchronous** communication when the caller genuinely needs an immediate answer and the availability
  and latency coupling is acceptable. Use **asynchronous** messaging when temporal decoupling, buffering, independent
  processing, fan-out, durable handoff, or failure isolation materially helps **and** the business can tolerate and
  understand the resulting consistency semantics.
- **COM-2** An event is a **contract and an operational dependency**. Publishing everything does not decouple a system
  if consumers share knowledge, must coordinate releases, or rely on hidden ordering or transaction assumptions. Events
  state facts in the past tense, with a named owner. Commands are not events.
- **COM-3** **Dual writes are forbidden.** State change and event publication MUST be atomic via a transactional outbox
  (or an equivalent) rather than two independent writes.

### Message contracts

- **COM-4** A message contract for independent consumers MUST define: event id, type, version, occurred-at, producer,
  correlation and causation ids; stable identifiers; no leakage of private persistence or framework representation.
- **COM-5** Contracts evolve additively. Consumers are tolerant readers. A schema registry or consumer-driven contract
  tests SHOULD guard compatibility. A deprecation policy MUST exist before a field or event is removed. Internal events
  MUST NOT be exported as integration contracts.

### Delivery, idempotency, deduplication

- **COM-6** Delivery semantics MUST be declared. Default: **at-least-once delivery with idempotent consumers**.
  "Exactly-once" is claimed only as effectively-once observable effects, built from idempotency, deduplication, and the
  outbox, never from broker settings alone.
- **COM-7** Deduplication MUST declare its **retention window**, which MUST cover the maximum redelivery and replay
  window, and MUST define behavior after the window.
- **COM-8** Every consumer with side effects MUST be idempotent by key, and the key MUST be defined.

### Ordering and concurrency

- **COM-9** Ordering MUST be required only where the business needs it, and its **scope** MUST be stated (per key or per
  aggregate, never global by default). Duplicate, late, and out-of-order delivery MUST be tolerated according to
  declared business semantics; versions, sequence numbers, deduplication, monotonic updates, or state-machine tolerance
  are examples, not mandatory implementations.
- **COM-10** Consumer concurrency MUST NOT break ordering or invariants. Choose the smallest correctness mechanism that
  matches the failure model: serial execution, partition/key affinity, database constraints or transaction isolation,
  optimistic or pessimistic concurrency, fencing, or another proven mechanism. Optimistic concurrency is not a universal
  default.

### Failure handling

- **COM-11** Retries MUST be bounded, with backoff and jitter where repeated retry is appropriate. Transient and
  permanent failures MUST be classified. A poison message MUST NOT be retried forever; it MUST move to a deliberate
  terminal or quarantined path such as a dead-letter destination, parking store, halted partition, or operator workflow,
  with enough context to diagnose and recover.
- **COM-12** When a dead-letter, quarantine, or parking mechanism is used, it MUST have an owner, alerting appropriate
  to consequence, a documented safe reprocessing or disposition procedure, and a retention policy.
- **COM-13** Message processing SHOULD be **replayable**: deterministic or idempotent, with side effects isolated or
  guarded, and replay tested.
- **COM-14** Distributed effects whose correctness can drift across independently committed state MUST define
  **reconciliation**: a source of truth, discrepancy detection, and repair or disposition. Reconciliation MAY be
  periodic, event-driven, on-demand, or unnecessary when an authoritative/idempotent design proves drift cannot persist;
  the chosen rationale MUST be stated.
- **COM-15** Multi-step business operations across owners MUST choose **compensation** or **forward recovery** per step.
  Compensations are new business actions, not rollbacks. Irreversible steps and the pivot point are identified and
  ordered deliberately.
- **COM-16** Long-running workflows MUST have **explicit persistent state** (state machine), timeouts, deadlines,
  cancellation, visibility, and a versioning strategy for in-flight instances.

### Load and capacity

- **COM-17** Pipelines MUST apply **backpressure**: bounded queues, rate limiting or load shedding, pressure propagated
  upstream. Unbounded buffers MUST NOT be used.
- **COM-18** When capacity, availability, or failure tolerance is a consequential requirement, the capacity plan MUST
  include the required **headroom and failure capacity** from the accepted quality-attribute scenario. Peak, growth, and
  skew MUST come from measurement or explicit requirements, not invented numbers.
- **COM-19** When partitioning or sharding is used, the partition key MUST be chosen from the **real workload**
  (cardinality, distribution, access pattern). Hot partitions and heavy hitters MUST be observable and handled when
  material. A resharding strategy is required when expected data lifetime or growth makes repartitioning a credible
  operational need; do not require one for a bounded topology with no such pressure.
- **COM-20** Scaling stateful systems MUST preserve correctness: ownership per shard, rebalancing, failover, and
  split-brain prevention are specified.

### Time and observability

- **COM-21** Wall-clock time MUST NOT be used for cross-node ordering or correctness; use sequence, version, or logical
  clocks. When a lease protects correctness over a resource that a stale holder could still mutate, ownership changes
  MUST be fenced by a monotonically increasing token or version, or by another mechanism **enforced by the protected
  resource** that rejects stale holders. A token that nobody checks is not a correctness mechanism. A timeout is not
  certain failure detection.
- **COM-22** Correlation and causation ids MUST propagate across requests, events, and background work. Logs are
  structured and traceable end to end.
- **COM-23** Eventual consistency that can affect a user's decision or an operator's ability to detect correctness drift
  MUST be visible to the **affected audience**. Use user-facing processing or freshness state when the inconsistency is
  user-observable, and operator lag, discrepancy, or reconciliation signals when operationally relevant. Do not add
  user-visible state for purely internal lag that has no user consequence.

---

## §8 Data, contracts, and consistency

### Data ownership

- **DAT-1** Each **authoritative logical fact** has exactly one explicit owner. The unit is the fact, not the table or
  dataset.
- **DAT-2** A derived dataset MAY combine facts from several authorities, but MUST declare provenance, semantics,
  freshness, and rebuild or reconciliation behavior (KNW-3).
- **DAT-3** Cross-owner **shared writes** to the same authoritative data create shared ownership and strong coupling,
  and MUST NOT occur without an Override. A shared read model is not shared write ownership.
- **DAT-4** Other owners read through a contract, a published read model, or event-carried state, never through another
  owner's tables or private types (STR-7).
- **DAT-5** Public identifiers and public domain language are one-way doors (§2.4).

### Contract and schema evolution

- **DAT-6** For **live or backward-compatible evolution** where old and new consumers overlap, consumers cannot be
  coordinated atomically, or independently deployed consumers exist, prefer **expand, migrate, contract**: add
  compatibly, migrate consumers, then remove. Never break a consumer you cannot coordinate with. Version when consumers
  deploy independently. A coordinated atomic schema change inside one deployable MAY use a simpler migration when no
  compatibility overlap is required and rollback/recovery is acceptable.
- **DAT-7** A **non-trivial live migration of data ownership** with concurrent old/new paths MUST proceed in reversible
  stages. A typical sequence is: route writes through the future owner's contract while storage is unchanged; move reads
  to that contract; separate storage with an appropriate synchronization mechanism such as outbox or change capture when
  overlap requires it; cut over with a rollback window; then retire the old path (MIG-8). The exact stages are not
  mandatory when ownership can be transferred atomically in a coordinated monolith without overlapping authorities. No
  irreversible step is taken before its prerequisite is verified.

### Cross-owner consistency

- **CNS-1** If an invariant appears to span owners, **first re-check whether ownership is wrong**.
- **CNS-2** If the separate owners are real, choose the consistency mechanism **explicitly from the actual failure
  model**: a local atomic transaction where available; distributed coordination or transaction where its cost is
  justified (§11); or eventual consistency with explicit ordering, duplicate, failure, reconciliation, and compensation
  semantics (§7).
- **CNS-3** The weaker guarantee MUST be stated in business terms and accepted by the accountable human before it is
  built.

---

## §9 Brownfield and migration

- **MIG-1** Default: **avoid big-bang restructures**. Prefer incremental, releasable migration. A full replacement MUST
  earn itself through verified constraints and MUST have an explicit cutover, rollback, and data-migration strategy.
- **MIG-2** Every step keeps the system releasable and the existing tests passing. Prefer strangler-style replacement
  and branch by abstraction (§11).
- **MIG-3** Add **characterization tests** before moving behavior that is not fully understood. Create seams only where
  needed.
- **MIG-4** Introduce enforcement with a **ratchet**: record current violations as an accepted snapshot, fail only on
  new ones, and shrink the snapshot deliberately. Stop the bleeding before moving things.
- **MIG-5** Refactor in service of a **named change or measured pain** (change cost, defect clustering, incidents), not
  aesthetics.
- **MIG-6** Leave stable, working, rarely changing code alone, **unless** it creates material correctness, security,
  compliance, operability, or platform-lifecycle risk (for example a vulnerability, an unsupported dependency, or an
  unrecoverable failure mode).
- **MIG-7** When running old and new in parallel: define the comparison oracle, isolate side effects so effects are not
  duplicated, keep data synchronized, time-box the overlap, and set exit criteria.
- **MIG-8** **Retire the old path after cutover**: remove dead code, routes, data syncs, and flags; verify no consumers
  remain (observed traffic at zero for an agreed period); remove abstractions that existed only for the migration.
- **MIG-9** Existing structure is evidence of past behavior and a legitimate force (conventions, team habits). It is not
  authority. Deviate only with a named signal, and migrate at the seam of real change.
- **MIG-10** Old and new architectures running side by side MUST each have a single owner of every write, so that no
  fact has two authorities during the overlap.

---

## §10 Testability and enforcement

### Testability

- **TST-1** Testability is a quality attribute that can justify a **seam**. It is not a default reason for an interface
  (DEP-2).
- **TST-2** Prefer **owner-surface tests** for owner behavior, using real in-process collaborators where practical.
- **TST-3** Internal domain types and algorithms MAY still deserve focused unit tests.
- **TST-4** Place fakes at real boundaries: external, slow, nondeterministic, or privileged dependencies (`Clock`,
  network, payment provider).
- **TST-5** If testing public behavior requires mocking many private collaborators, the design is probably too
  fragmented or the boundary is leaking.
- **TST-6** A boundary justified only by test convenience is a seam at that dependency, not a layer through the system.

Test strategy itself (pyramid, tooling) is out of scope (§16.2).

### Enforcement

- **ENF-1** Objective properties SHOULD be automated: import and dependency direction; **neutral technical areas
  importing no business modules except the explicitly identified composition root wiring exception (CMP-6)**;
  business-module public and private surface (STR-15); forbidden slice-private imports and slice dependency cycles
  (CMP-9); provider and framework type leakage; "do not write another owner's tables"; forbidden cross-owner
  dependencies; dependency cycles; architecture characteristics with measurable thresholds. These are the properties for
  which blocking gates are appropriate (ENF-6).
- **ENF-2** A linter cannot judge the best domain name, whether two concepts share a reason for change, or whether a
  semantic boundary is correct. Those require architectural reasoning and representative change scenarios. Do not
  pretend otherwise.
- **ENF-3** **Fitness functions** are used when the property is objective and consequential, and each has a threshold,
  an owner, and a defined failure action. Do not invent automated ceremony for subjective prose.
- **ENF-4** Rules are introduced with a ratchet on existing code (MIG-4).
- **ENF-5** Tools are examples, not mandates. Use what the ecosystem already offers:

| Property                                 | Typical mechanisms                                                                                                                                                                                                                            |
|------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Dependency direction, cycles, visibility | ArchUnit (JVM); ArchUnitNET / NetArchTest (.NET); Deptrac (PHP); dependency-cruiser, eslint boundary rules, Nx module boundaries (TS/JS); import-linter (Python); `internal/` packages, go-arch-lint (Go); package-private and module systems |
| Another owner's data                     | Separate schemas; per-owner database roles and grants; migration ownership checks                                                                                                                                                             |
| Provider type leakage                    | Forbidden-import rules outside the adapter                                                                                                                                                                                                    |
| Co-change hot spots                      | Version-control history analysis (for example code-maat), with CPL-8 filtering                                                                                                                                                                |
| Measurable characteristics               | Load, latency, and recovery checks in CI or scheduled runs                                                                                                                                                                                    |

- **ENF-6** Naming and navigation conventions (CMP-4, CMP-8) SHOULD be detected as **smells**: a ratcheted (MIG-4),
  non-blocking report with a project-level business allowlist that has an owner and a review date. Blocking gates are
  reserved for objective properties (ENF-1, ENF-3): dependency direction, cycles, module public and private surface,
  cross-owner imports, table and data ownership, provider and framework leakage, and measurable architecture
  characteristics. A linter cannot judge a good business name (ENF-2), and renaming to evade the check (
  "AccountsGateway", "Processor") defeats the intent (AI-7).
- **ENF-7** A control, policy, or boundary claim MUST NOT be treated as **enforced architecture** merely because it
  appears in documentation, an inventory, a diagram, a label, or a registry. Where the control is consequential, it MUST
  gate the relevant live path — for example runtime execution, authorization, data writes, build/CI, deployment, or
  another effect path — or be explicitly recorded as advisory / NOT ENFORCED. Documentation is evidence of intent, not
  enforcement.
- **ENF-8** Normative strength and enforcement status are separate axes. A rule does not become optional merely because
  its current enforcement is manual or absent. For this section, a **normative rule ID** is any rule in these explicit
  rule families: **AI, BEH, SCR, CMP, STR, BND, KNW, INV, CPL, HID, ABS, DOM, DEP, DST, CPX, PAT, QAS, CHG, TEAM, COM,
  DAT, CNS, MIG, TST, ENF**. These rules prescribe, prohibit, constrain, or condition behavior whether they use
  MUST/MUST NOT, SHOULD, MAY, or an imperative such as `Do not`/`Never`. Principles P1–P15 are decision principles
  rather than independent Enforcement Map rows; Decision Cards C-xx are conditional technique guidance rather than
  independent rule rows. Each adopting project MUST maintain an **Enforcement Map** covering every normative rule ID
  with: rule ID; applicability; verification mode; current enforcement status; concrete mechanism/evidence; target
  enforcement; accountable owner.
    - **Applicability** is **APPLICABLE** or **N/A — <reason>**. N/A is allowed when the rule cannot apply to that
      project/context by construction; it is not an enforcement status and MUST NOT be used to hide missing enforcement.
    - Several rule IDs MAY share one enforcement-map row/control only when applicability, verifier, current status,
      target, owner, and evidence are genuinely identical; every covered rule ID MUST still be explicitly enumerated and
      independently traceable.
- **ENF-9** Enforcement status describes **current reality**, not future intent:
    - **MACHINE-ENFORCED** — a runtime, authorization, data, CI/build, deployment, or equivalent live-path gate can fail
      the change/effect;
    - **REVIEW-ENFORCED** — the rule requires semantic judgment and is verified through an accountable human or
      explicitly assigned independent review with cited evidence;
    - **ADVISORY** — non-blocking guidance or smell detection; appropriate mainly for SHOULD rules;
    - **NOT ENFORCED** — no effective gate or review path is currently established. This is an honesty status, not a
      waiver. **Target enforcement** is a separate planning field and MAY name a desired future status/mechanism; a
      target MUST NOT be reported as current enforcement or evidence. Objective properties SHOULD move toward
      MACHINE-ENFORCED where practical (ENF-1). Semantic ownership, invariant, and boundary judgments MAY legitimately
      remain REVIEW-ENFORCED (ENF-2).

---

## §11 Decision cards

Cards are consulted **only when the matching technique is being considered**. A technique is adopted only when its
"Adopt when" holds as a recorded FACT, its "Prove first" items are satisfied, and no "Simpler first" option credibly
meets the need. Every adopted consequential technique (§0.4 (h)) gets both a Complexity Ledger entry (CPX-6) and a
concrete REVISIT trigger. Small local uses inside one owner need neither unless another rule explicitly requires one.

### C-01 Interface or port

- **Adopt when:** two or more real implementations are needed now; or a boundary protects a real external, volatile,
  privileged, or nondeterministic dependency; or a consumer across an ownership boundary genuinely needs a narrower
  contract.
- **Avoid when:** mirroring one class one-to-one; "for testability" around stable in-process code; speculating about a
  second implementation.
- **Prove first:** the volatility, trust, or ownership it protects; the consumers; that it hides complexity rather than
  mirroring it.
- **Simpler first:** a concrete class; a function type; a seam only at the dependency; testing through the owner's
  surface.
- **Recurring cost:** indirection, navigation cost, interface and implementation drift, mock-coupled tests.
- **Revisit:** one implementation, no volatility, for a long time -> inline it. Consumers grow -> narrow it.

### C-02 Shared abstraction or extraction

- **Adopt when:** the code represents the same knowledge, with two or more real consumers, the same reason for change,
  and a stable concept.
- **Avoid when:** the code merely looks alike. Counting repetitions is not proof; identity of knowledge is.
- **Prove first:** the consumers have changed together historically; divergence between copies has caused defects.
- **Simpler first:** tolerate the duplication; extract locally within one owner.
- **Recurring cost:** a coordination hub; false generalization; flag parameters and per-caller conditionals.
- **Revisit:** consumers diverge -> inline the abstraction back into them.

### C-03 Rich domain model

- **Adopt when:** non-trivial invariants, lifecycle transitions, and business terminology must have one authoritative
  model reused across entry points.
- **Avoid when:** CRUD, orchestration, or validation-only work.
- **Prove first:** the invariants listed in domain language, and where they would otherwise be duplicated.
- **Simpler first:** a transaction script or straightforward procedural orchestration with validation.
- **Recurring cost:** mapping, ceremony, persistence friction, learning curve.
- **Revisit:** invariants vanish -> simplify. Rules start duplicating across entry points -> promote.

### C-04 CQRS

- **Adopt when:** read and write models genuinely diverge in shape, scale, consistency, or ownership; complex queries
  distort the write model.
- **Avoid when:** "it is our standard"; symmetric command and query objects over CRUD.
- **Prove first:** the concrete divergence; staleness is acceptable and visible (COM-23); projection rebuild and
  reconciliation strategy.
- **Simpler first:** one model with separate query functions, read-optimized views, indexes, or a materialized view.
- **Recurring cost:** projections, eventual consistency, two models, rebuild and reconciliation.
- **Revisit:** the divergence disappears.

### C-05 Event sourcing

- **Adopt when:** the event history **is** the source of truth; reconstruction, temporal queries, or legal audit
  semantics require it; the team can operate it.
- **Avoid when:** you only need an audit trail; "events are modern."
- **Prove first:** the reconstruction requirement; event schema evolution and upcasting plan; snapshotting; erasure and
  privacy strategy; replay performance.
- **Simpler first:** an append-only audit table beside authoritative current state.
- **Recurring cost:** event versioning, projections, erasure difficulty, debugging, learning curve. Very hard to reverse
  (one-way door).
- **Revisit:** the reconstruction requirement weakens; replay or evolution cost dominates.

### C-06 Modular monolith

**Baseline note:** this card describes the obligations of the baseline composition (§1.6). Its "Adopt when" and "Prove
first" items are checks on whether the baseline is honest and enforced, not an extra signal gate that must be passed
before the baseline may be used.

- **Adopt when:** internal ownership boundaries matter and independent deploy, failure, or scale ownership is not yet
  required. Default candidate (DST-3).
- **Avoid when:** boundaries would exist only as folders with no enforcement.
- **Prove first:** boundaries are enforced automatically (ENF); per-owner data ownership; public contracts between
  modules.
- **Simpler first:** a single module, if the system is small and cohesive.
- **Recurring cost:** discipline; a shared release train and blast radius.
- **Revisit:** demonstrated independent deploy, scale, trust, or failure pressure -> extract the one module (C-08).

### C-07 Microservices

- **Adopt when:** multiple business modules truly need independent ownership, deployment, failure, or scale boundaries
  and the organization can operate distributed data, networking, observability, and delivery safely.
- **Avoid when:** the motivation is future scale, fashion, resume technology, or org-chart aesthetics.
- **Prove first:** stable business boundaries already exist; data ownership and coordinated changes are mapped; one
  extraction is prototyped and its deploy, coupling, and ops cost measured; failure isolation and independent
  deployability are demonstrated.
- **Simpler first:** a modular monolith with explicit ownership; a separate worker process for resource isolation;
  extract only the one business module whose pressure is proven.
- **Recurring cost:** latency and partial failure, tracing, versioned contracts, security surface, distributed data
  consistency, deployment and on-call burden, all permanent. Signals against: shared database, frequent coordinated
  releases, chatty calls, invariants spanning services.
- **Revisit:** the original adoption pressure changes, or services keep releasing together.

### C-08 Split or extract a service

- **Adopt when:** a distinct owner, deploy cadence, scale profile, failure domain, or trust boundary exists **and** a
  single writer for its data can be established.
- **Avoid when:** size alone; "the codebase is large."
- **Prove first:** a logical module with an enforced contract already exists; data ownership map (DAT-1); migration
  stages (DAT-7); rollback plan.
- **Simpler first:** keep it a module; run it as a separate process only if isolation is the need.
- **Recurring cost:** see C-07.
- **Revisit:** the pressure that justified the split changes; the two sides keep changing together.

### C-09 Message broker, work queue, publish-subscribe, event-driven architecture

- **Adopt when:** temporal decoupling, buffering and load leveling, fan-out to independent consumers, durable handoff,
  or independent processing rate materially helps, and the consistency semantics are acceptable.
- **Avoid when:** you want to merely look decoupled; the interaction needs an immediate response; a database-backed
  queue with an outbox suffices; commands are disguised as events; a single known consumer needs a reply.
- **Prove first:** all **applicable** §7 obligations (delivery, idempotency, ordering scope, retry/quarantine, replay,
  reconciliation, monitoring); who operates the broker; contract ownership.
- **Simpler first:** an in-process call; a database queue with outbox and worker; a scheduled job.
- **Recurring cost:** infrastructure and operations, harder reasoning about control flow, ordering and duplicate
  handling, schema governance, debugging across hops.
- **Revisit:** the decoupling benefit is not used; one consumer, one producer, tight coupling remains.

### C-10 Streaming platform

- **Adopt when:** high-throughput ordered log, long retention, replay, and multiple consumers or stream processing are
  needed.
- **Avoid when:** moderate volume that a queue handles.
- **Prove first:** volume, retention, and replay requirements; partitioning from the real workload (COM-19); operating
  ownership.
- **Simpler first:** a queue or broker; batch jobs.
- **Recurring cost:** heavy operations, partition management, consumer-group and schema governance.
- **Revisit:** volume or replay need falls below the threshold that justified it.

### C-11 Saga, orchestration, choreography

- **Adopt (saga) when:** a multi-step business operation across real owners cannot be one local transaction, and
  compensation or forward recovery is defined per step.
- **Avoid when:** one local transaction suffices; a workflow engine for a single owner.
- **Prove first:** persistent state model; idempotent steps; compensation semantics; timeouts and visibility (COM-15,
  COM-16).
- **Choose orchestration when** the process has an owner, needs visibility, timeouts, and compensation, or has
  conditional flow across three or more steps. **Choose choreography when** a few independent reactions exist and no
  process owner is needed. Avoid choreography that hides control flow.
- **Simpler first:** one owner and one transaction (CNS-1).
- **Recurring cost:** state management, partial-failure handling, observability, complexity of reasoning.
- **Revisit:** ownership is merged and the saga collapses into a local transaction.

### C-12 Distributed transactions, distributed locking, leader election, consensus

- **Distributed transaction (2PC/XA) adopt when:** strong atomicity across resources is truly required, every
  participant supports it, and the latency and availability cost is acceptable. Rare. **Avoid** across independently
  owned services by default.
- **Distributed lock adopt when:** mutual exclusion across processes is required for an external side effect and no
  idempotent or data-level design works. Locks that protect correctness MUST be fenced at the protected resource
  (COM-21). **Avoid** as a substitute for a unique constraint, optimistic version check, or per-key serialization.
- **Leader election or consensus adopt when:** a single active coordinator with failover is needed and no platform
  primitive covers it. Use proven implementations (a managed or established coordination service); **never build your
  own consensus**.
- **Simpler first:** single owner; unique constraints; compare-and-set; one queue per key; a single instance with
  restart.
- **Recurring cost:** availability coupling, stalls under partition, subtle correctness bugs, operational expertise.
- **Revisit:** a data-level design now removes the need for coordination.

### C-13 Eventual consistency and effectively-once effects

- **Accept eventual consistency when:** the business tolerates the inconsistency window, and a concrete benefit
  (availability, scale, independence) is gained.
- **Avoid when:** a strong invariant must hold at write time (INV-2).
- **Prove first:** the window is measured; user and operator visibility (COM-23); reconciliation and compensation
  (COM-14, COM-15).
- **Require effectively-once effects when:** duplicates or loss are unacceptable (payments, inventory). Build from
  idempotency keys, deduplication with retention, and an outbox (COM-3, COM-6, COM-7).
- **Simpler first:** keep it in one transaction under one owner.
- **Revisit:** the window or discrepancy rate exceeds what the business accepted.

### C-14 Anti-corruption layer or compatibility adapter

- **Adopt when:** a foreign or legacy model with different semantics would otherwise leak into your domain language.
- **Avoid when:** the models are compatible; one-to-one pass-through.
- **Prove first:** the semantic mismatch and the translations required; who owns the mapping.
- **Simpler first:** direct use when semantics align.
- **Recurring cost:** translation code and its maintenance; drift when the foreign model changes.
- **Revisit:** the legacy or foreign system is retired -> remove the layer (MIG-8).

### C-15 Strangler migration, branch by abstraction, parallel run

- **Strangler adopt when:** legacy behavior can be intercepted behind a routing seam and replaced incrementally.
- **Branch by abstraction adopt when:** an internal implementation is replaced while the system stays releasable:
  introduce abstraction, add the new implementation, switch, remove the old implementation **and the abstraction**.
- **Parallel run adopt when:** the cutover is high-consequence and outputs can be compared (MIG-7).
- **Avoid when:** the seam cannot be created; parallel run would duplicate external side effects.
- **Prove first:** rollback path; comparison oracle; single writer per fact during overlap (MIG-10); time-box and exit
  criteria.
- **Simpler first:** a small in-place refactor with tests.
- **Recurring cost:** dual maintenance during overlap; risk of becoming permanent.
- **Revisit:** overlap exceeds its time-box -> force a decision.

### C-16 Fitness functions

- **Adopt when:** an architecture property is objective and consequential and must keep holding.
- **Avoid when:** the property is subjective.
- **Prove first:** a threshold, an owner, and a defined failure action.
- **Simpler first:** a review checklist item.
- **Recurring cost:** maintenance and false positives.
- **Revisit:** the property is no longer consequential, or the check is routinely ignored.

### C-17 Build, buy, managed service; vendor lock-in; standardization

- **Buy or use managed when:** the function is generic and not a business differentiator, the team is small, TCO is
  favorable, SLO and security fit, **data export and an exit path** exist, and fit is proven.
- **Build when:** it is a differentiator, or fit gaps are material.
- **Accept lock-in when:** the benefit exceeds a known, bounded exit cost; vendor types are isolated at the boundary
  (DEP-5). Do not abstract everything "for portability."
- **Standardize a technology when:** it reduces operational and cognitive cost across teams on a shared platform, **with
  an explicit exception process**. Avoid mandates without exceptions.
- **Prove first:** TCO, SLO, security review, exit path, proof of fit.
- **Revisit:** price, SLO, or fit changes; exit cost grows beyond what was accepted.

### C-18 Minor cards

| Technique                              | Adopt when                                                                                                                                         | Avoid when                          | Simpler first                                             |
|----------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------|-----------------------------------------------------------|
| Multi-region deployment                | An explicit RTO, RPO, latency, or regulatory requirement exists; replication consistency, failover drills, conflict handling, and cost are defined | Prestige or vague "resilience"      | Single region, multi-zone, backups, and a tested DR drill |
| CDN                                    | Cacheable content with geographic latency or origin offload need; invalidation strategy defined; hit rate measured                                 | Personalized or uncacheable content | Origin caching, compression                               |
| Long-running service vs job            | **Service** for continuous, low-latency, or connection-stateful work. **Job** for periodic, batch, or bounded work                                 | Running a service for periodic work | A scheduled job                                           |
| Formal specification or model checking | Concurrency or distributed protocols (locking, replication, state machines) where defects are very costly, and expertise exists                    | CRUD or simple flows                | Property-based tests, scenario tests                      |
| Cache                                  | A measured read or latency pressure; staleness contract and invalidation defined; never a second authority (KNW-3)                                 | Masking an unmeasured slow path     | Query or index fix                                        |

---

## §12 Architecture tests

A design does not pass because the diagram looks clean. These are falsification tests. L1 runs tests 1 to 3. L2 and L3
run all that apply. Each result is recorded as pass, fail, or not applicable, with the evidence used.

**Semantic proof targets.** The progressive explanatory model gives four useful things to prove, without mandating one
test class per concept:

```text
Flow            -> scenario proof: does the end-to-end story reach the intended outcome?
Feature/Use Case -> behavior proof: does this concrete goal behave correctly?
Capability      -> decision/ability proof: does the stable rule or ability produce the right result?
Implementation  -> boundary proof: does the technical realization translate, fail, and recover as promised?
```

These proof targets are semantic; `unit`, `integration`, `contract`, and `end-to-end` describe execution
scope/technique. A trivial behavior MAY need only one focused behavior test. Do not manufacture a Capability,
Implementation abstraction, or extra test layer merely to complete the matrix (P12, CPX-7).

| #  | Test                     | Procedure                                                                                                                                                                                                                                                                                                          | Fail when                                                                                                                                                                                                                                                                                                                                                          |
|----|--------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 1  | **Change**               | Take a representative business change (backlog item or realistic scenario). Walk it through the design and list every owner and area touched.                                                                                                                                                                      | One coherent change needs edits across many unrelated structural areas.                                                                                                                                                                                                                                                                                            |
| 2  | **Invariant**            | Take the most important business invariant. Find every place it is implemented or synchronized.                                                                                                                                                                                                                    | Several slices, modules, or services must reimplement or synchronize the same rule. The authoritative owner is probably misplaced.                                                                                                                                                                                                                                 |
| 3  | **Knowledge-hiding**     | Change one private implementation or design decision.                                                                                                                                                                                                                                                              | Unrelated consumers must understand or change. The boundary leaks.                                                                                                                                                                                                                                                                                                 |
| 4  | **Screaming**            | Look first at the top-level structure, public entry points, caller-facing business APIs, and any flow/navigation view. Ask a competent engineer to state the main owners, what the system does end to end, the important behaviors, and where technical realization begins without reading private implementation. | The tree or public API mainly screams `Controllers`, `Services`, `Repositories`, framework/ORM roles, generic verbs (`handle`, `process`, `execute`), or leaked technical workflow such as `validate -> persist -> publish` where one business action should be visible; or the business story cannot be followed without first understanding framework mechanics. |
| 5  | **Coupling** (empirical) | Inspect VCS history for what changed together in recent changes and over several months (apply CPL-8 filtering), then take one predicted change.                                                                                                                                                                   | Independent responsibilities repeatedly need coordinated edits, or units that share a reason to change are scattered.                                                                                                                                                                                                                                              |
| 6  | **Cohesion**             | Inspect each grouping.                                                                                                                                                                                                                                                                                             | Grouped code shares no knowledge, invariant, lifecycle, or reason for change. The grouping is cosmetic.                                                                                                                                                                                                                                                            |
| 7  | **Depth**                | Inspect each module or interface.                                                                                                                                                                                                                                                                                  | It mostly forwards calls and exposes most implementation decisions. It is shallow ceremony.                                                                                                                                                                                                                                                                        |
| 8  | **Distribution**         | For every service, process, or network boundary, name what distribution buys: deploy, trust, scale, failure isolation, lifecycle, or ownership.                                                                                                                                                                    | The answer is only "decoupling" or "clean architecture." Keep the boundary in-process.                                                                                                                                                                                                                                                                             |
| 9  | **Complexity**           | For every architectural element, name the pressure that earns it and the simpler option that failed.                                                                                                                                                                                                               | It cannot be named. Remove or defer it.                                                                                                                                                                                                                                                                                                                            |
| 10 | **Quality-attribute**    | For consequential choices attach a QAS-2 scenario and evidence: load or capacity test; failure injection or failover rehearsal; security or authorization test; dependency or architecture check; deployment exercise; restore or recovery drill; representative change scenario.                                  | A quality attribute drives the decision but has no scenario or evidence.                                                                                                                                                                                                                                                                                           |
| 11 | **Reality**              | Check security, reliability, performance, data, regulatory, deployment, and organizational constraints against the preferred decomposition.                                                                                                                                                                        | A **verified** constraint contradicts the decomposition and no Override is recorded. Project reality wins; the override MUST be explicit.                                                                                                                                                                                                                          |

---

## §13 Procedures and artifacts

### 13.1 Pre-flight (before structural work)

Full at L2 and L3. At L1 compressed to items 1, 2, and 5. Not required at L0. The agent MUST be able to answer, or list
what is UNKNOWN:

1. What level is this (§0.5), and which Architectural Events (§0.4) does it contain?
2. What did I actually observe (AI-1), and what am I inferring or assuming?
3. Which hard constraints are verified (§2.1)?
4. Which existing ADRs, Overrides, and REVISIT triggers touch this area (§13.7)?
5. What are the flows, authoritative facts, invariants, owners, and reasons for change involved?
6. Which rules in §4 to §10 and which cards in §11 apply?

### 13.2 Templates

Templates are mandatory at the level shown. Omit a field only by writing "n/a" with a reason.

**Authoritative storage of governance artifacts.** Persistent governance artifacts — the adopting-project decision log
(ADRs and Overrides), Boundary Register, Complexity Ledger, Constraints Register, Enforcement Map, and any required
provenance/source map — MUST live in the project's version-controlled decision/governance store using the project's
established convention. This portable document does not mandate a repository path. Each artifact or register MUST have
exactly one authoritative location. Registers MAY be separate living documents or generated/aggregated from
authoritative decision records, but conflicting hand-maintained copies MUST NOT exist. If a generated view exists, its
authoritative source MUST be identified.

**Short Decision Note (L1)**

```text
SDN-<n>  <title>
Level: L1 | Owner(s): <...> | Behavior: <...>
Invariants: <...>
Decision: <what> | Why simplest: <pressure or "no new pressure">
Tests: Change / Invariant / Knowledge-hiding -> <pass | fail | n/a> | Revisit: <trigger or "none">
Compliance: <ONLY material violation, unresolved Rule Conflict, or NOT VERIFIED reason; omit when clean>
```

**Architecture Decision Record (L2 and L3)**

```text
ADR-<nnnn>  <title>
Status: Proposed | Accepted | Superseded by ADR-<nnnn> | Rejected
Level: L2 | L3          Date: <...>          Decider: <accountable human>
Rules cited: <BND-1, KNW-4, DST-3, ...>

FACTS          observed, each with a path, symbol, measurement, commit, or source
INFERENCES     interpretations, hypotheses, each with how it would be verified
ASSUMPTIONS    accepted without verification
UNKNOWNS       not observable and not inferable
CONSTRAINTS    verified hard constraints (reference the Constraints Register)
DECISIONS      the choice, stated without naming the pattern first
ALTERNATIVES   2 to 3 serious options, including "do less", each with why it was rejected
TRADE-OFFS     the recurring cost of the choice
VALIDATION     how we will know it works (tests from §12, scenarios, evidence)
REVISIT        concrete, observable evidence that should reopen the decision
OVERRIDES      Override Records that apply, if any
```

**Bootstrap without an accountable human.** An adopting project MAY inspect, evaluate, and provisionally apply this
governance before it has designated its accountable human, but approval authority remains absent. Until exactly one
accountable human is designated:

- L0 work inside already accepted boundaries MAY continue when no rule requires accountable approval.
- L1 analysis and Short Decision Notes MAY be produced; they do not create approval authority.
- Every new L2/L3 ADR MUST remain **Proposed** and MUST NOT be treated as Accepted.
- A new Override MUST NOT become effective.
- A decision that requires accountable-human acceptance MUST remain NOT VERIFIED / pending; the agent MUST stop only
  when that unresolved approval is necessary to complete the requested task correctly (§13.5).
- Existing previously Accepted decisions remain in force; absence of a current tie-breaker does not silently revoke
  them.

The adopting project MAY designate the accountable human in the same recorded decision that adopts this governance. A
one-off instruction does not create continuing tie-break authority unless the project records that designation.

**Governance adoption baseline (brownfield).** An existing repository MAY adopt this governance without pretending that
all pre-existing violations are already fixed. At adoption time it MAY capture the observed pre-existing violations once
in a version-controlled **Adoption Baseline** and apply MIG-4 ratcheting. The baseline is **not compliance, not a
waiver, and not permission for new violations**.

Each baseline entry MUST identify: the affected rule ID(s); evidence/scope (paths, modules, controls, or other concrete
location); current enforcement status; the ratchet or control that prevents regression where practical; an accountable
owner; and a resolution, accepted-remediation plan, or observable REVISIT trigger. New violations introduced after the
baseline are not grandfathered. The baseline SHOULD shrink over time and MUST be refreshed only through an explicit
reviewed decision, never silently expanded. Level-1 precedence (law, safety, security, data integrity, verified hard
constraints) cannot be grandfathered into an unsafe state.

```text
Rule(s) | Existing evidence/scope | Current status | Ratchet/control | Owner | Resolution / REVISIT
```

**Boundary Register entry** (one per important boundary)

```text
Boundary | Logical or Physical | Protects (BND-1 item) | Owner | Contract surface | Enforced by | Evidence | Revisit
```

**Complexity Ledger entry** (one per consequential mechanism, CPX-6)

```text
Mechanism | Pressure (known or hypothesized) | Evidence | Simpler option that failed | Recurring cost | Validation | Removal trigger
```

**Constraints Register entry**

```text
Constraint | Source (law, contract, authority, measurement) | How verified | Scope | Date | Review
```

**Override Record** (§13.6)

```text
OVR-<n>  <title>
Rule(s) / decision(s) overridden: <rule IDs and/or ADR references>
Authority: <law/constraint ID, or accountable human instruction>
Reason and evidence: <...>
Trade-off accepted: <...>
Compensating controls and validation: <...>
Expiry or review trigger: <...>
```

### 13.3 Review mode (reviewing an existing structure)

1. **Scope and evidence.** Level; what was inspected (paths, history, entry points); what could not be inspected.
2. **What works and should stay.** Name structure that earns its place. A review that only criticizes is unreliable.
3. **Findings, ordered by cost of leaving them in place.** For each: evidence (path or symbol); the rule or test it
   violates (IDs); the consequence; the smallest fix; the cost or risk of that fix.
4. **Not findings.** Things that look wrong but are cosmetic and not volatile. Say so, to prevent pedantic churn. A
   clean-looking directory rewrite with no ownership, knowledge, dependency, invariant, contract, or runtime change is
   not an architectural improvement (STR-14).
5. **Sequence.** Incremental, releasable steps (ratchet first), plus REVISIT triggers.

Rules: severity follows change cost, not taste. Every finding is falsifiable. Recommend the **structural property** to
achieve ("one owner for the pricing invariant"), not a named pattern, and note which mechanisms could satisfy it. A
review is read-only unless the user authorizes changes (AI-4).

### 13.4 Compliance audit and statement

Proportional to the level (P12). Items not actually checked are marked NOT VERIFIED (AI-9).

- **L0:** no audit output. A violation of any MUST rule is still a violation.
- **L1:** the audit is **silent**: the agent checks the list below mentally. The output is the Short Decision Note. Only
  a material violation, an unresolved Rule Conflict, or a NOT VERIFIED reason is surfaced, in one line (`Compliance:` in
  the note). No checklist is printed.
- **L2 and L3:** the **full audit** is performed. The ADR carries the result: only failed or NOT VERIFIED items are
  listed, and the final status line is required. The full checklist is attached for an L3 review or when requested.

```text
[ ] Level classified; Architectural Events (semantic) identified            (§0.4, §0.5)
[ ] System observed; every FACT cites evidence                              (AI-1)
[ ] Hard constraints registered and verified, or none                       (§2.1)
[ ] Hierarchy followed; Direction check passes                              (§2.2, PAT-5)
[ ] Each invariant has exactly one owner; no atomic invariant split         (INV, KNW-4)
[ ] Each important boundary is in the Boundary Register                     (BND-4)
[ ] Each consequential mechanism has a Complexity Ledger entry              (CPX-1, CPX-6)
[ ] Pressure classified known vs hypothesized                               (CPX-3)
[ ] Logical vs physical stated; distribution justified; §7 obligations      (DST, §7)
[ ] No cross-owner writes, private imports, or provider-type leakage        (STR-7, DAT-3, DEP-5)
[ ] No forbidden slice-private imports/cycles; module surface respected    (CMP-9, STR-15)
[ ] Consequential controls gate a live path or are marked NOT ENFORCED       (ENF-7)
[ ] Enforcement Map covers applicable normative rule IDs; N/A has reasons; current status has evidence; targets are not reported as current enforcement (ENF-8, ENF-9)
[ ] No hallway/cosmetic folders; primary navigation has a real reason       (STR-1 to STR-3, STR-16)
[ ] Business-facing names/APIs scream intent; technical workflow stays hidden (SCR-5 to SCR-8)
[ ] Technical area is neutral; no module semantics moved into it            (CMP-6)
[ ] Baseline composition followed, or departure recorded in one line        (CMP-1)
[ ] 2 to 3 alternatives considered, including "do less"                     (CPX-5)
[ ] Applicable tests run and recorded                                       (§12)
[ ] Quality attributes have scenarios and evidence where consequential      (QAS, test 10)
[ ] Objective properties automated, or an enforcement plan exists           (ENF)
[ ] REVISIT triggers are concrete and observable                            (§13.2)
[ ] Overrides recorded                                                      (§13.6)
[ ] Existing ADRs consulted; none contradicted without supersession         (§13.7)
[ ] No unrequested restructuring                                            (AI-4)
[ ] Unknowns and assumptions marked; no unmeasured numbers asserted         (AI-6, AI-14)
```

Status line, required at L2 and L3 (at L1 only when the result is not a clean pass). Exactly one of:

```text
GOVERNANCE: PASS
GOVERNANCE: PASS WITH OVERRIDES (OVR-<n>, ...)
GOVERNANCE: NOT VERIFIED (<items>)
GOVERNANCE: BLOCKED (<reason>)
```

### 13.5 Stop conditions

The agent MUST stop and ask the accountable human, presenting the facts and options, when:

1. A one-way-door decision (§2.4) lacks the evidence this document requires and the evidence cannot be obtained or
   inferred.
2. Hard constraints conflict with each other, or with an explicit instruction, and the conflict cannot be resolved by
   precedence (§0.3).
3. A change would destroy data, or break external or uncoordinated consumers, without a migration path.
4. An instruction would weaken a security, privilege, or data-integrity boundary (AI-15).
5. Two accepted ADRs conflict.
6. The decision is L3 and the system cannot be observed.
7. An Architectural Event arises inside a task that did not authorize it **and** resolving it is necessary for the task
   to be completed correctly, or it carries one-way-door, destructive, security, data-integrity, or external-contract
   risk (§0.4).

Outside these conditions the agent does not stop: it continues inside the existing accepted boundaries and records the
finding (§0.4).

While stopped, the agent does not proceed on a guess. It states the question once, with its recommended default.

### 13.6 Overrides and Rule Conflicts

- **Override.** Any deviation from a MUST rule, and any deviation from a SHOULD rule beyond a one-line reason, requires
  an Override Record. An Override MAY also represent an **explicitly temporary exception to an Accepted project
  decision/ADR** when the accountable human does not intend to replace that decision permanently. Valid authority is a
  verified hard constraint or an explicit instruction from the accountable human (§0.3). An Override is time-bounded or
  tied to a review trigger.
- **Accepted ADR vs explicit instruction.** A higher-precedence accountable-human instruction authorizes reconsideration
  of an Accepted ADR but does not rewrite history. If the instruction establishes a **durable replacement decision**,
  create a new ADR at the level required by the change and mark the old ADR `Superseded by ADR-<n>`; do not edit the old
  decision to make it appear that it always said the new thing. If the instruction is an **explicitly temporary
  exception**, keep the ADR Accepted and record an Override that cites the ADR, scope, trade-off, compensating controls,
  and expiry/REVISIT. When the temporary exception expires, either return to the Accepted ADR or make a new durable
  decision.
- **How to propose an override.** State the rule and/or accepted decision affected, the evidence or instruction, the
  trade-off, the compensating controls, and the validation. Do not silently proceed as though the rule/decision did not
  exist, and do not pretend it still applies unchanged to the exception.
- **Rule Conflict.** If two rules in this document conflict, or a rule yields a clearly absurd result in a specific
  situation (AI-7), the agent raises a Rule Conflict: the rules involved, why they collide, the recommended resolution,
  and its justification. It resolves in favor of the higher-precedence source (§0.3), then the MUST over the SHOULD,
  then the more reversible option (§2.4). The conflict is reported even when resolved.

### 13.7 Decision memory and drift control

- ADRs live in the project's decision log (default `docs/adr/NNNN-title.md`, unless the project has another convention).
- Before proposing a structure, the agent MUST search existing ADRs and Overrides for the area.
- An accepted ADR is changed only by a **new ADR that supersedes it**, citing new evidence, a fired REVISIT trigger, or
  a higher-precedence explicit accountable-human instruction that establishes a durable replacement (§0.3, §13.6). It is
  never edited in place to fit later work. A temporary exception uses an Override and leaves the ADR itself unchanged.
- When working in an area, the agent checks whether any REVISIT trigger has fired, and reports it. A fired trigger is a
  finding, not a licence to restructure unasked.
- REVISIT triggers MUST be observable (a metric, an event, a count, a date), not a mood.
- Overrides expire. At expiry or trigger the Override is reviewed and either renewed with a reason or retired.

### 13.8 Amendment of this document

- Only accountable humans amend this document. Agents propose, with the rule affected, the evidence, and the proposed
  text (AI-11).
- Changes use semantic versioning by **normative effect**:
    - **PATCH** — wording, typo, formatting, or clarification that does not change normative meaning or required
      behavior;
    - **MINOR** — a backward-compatible new rule, verification/process obligation, optional mechanism, or clarified
      default that does not make an already-compliant structural design non-compliant;
    - **MAJOR** — a change that can make previously compliant structural architecture non-compliant, changes precedence,
      removes/changes a core guarantee, or materially changes the ownership/boundary baseline.
- Tightening or relaxing a MUST is MINOR only when it changes governance procedure/evidence without invalidating
  existing structural decisions; otherwise it is MAJOR.
- The portable file records amendment version, date, and authority. An adopting project MUST also record each adopted
  MINOR or MAJOR amendment in its own decision log (ADR or equivalent). The portable file MUST NOT assume a
  project-specific `docs/adr/` path exists.
- 1.1.0 recorded the Baseline Composition amendment. 1.2.0 operationalized enforcement status, level calibration,
  governance ownership, and operational validation. 1.3.0 clarified proportional L3 evidence, the normative-rule set,
  Enforcement Map applicability/grouping/current-vs-target semantics, and bootstrap behavior before accountable-human
  designation. 1.3.1 aligned the §13.4 compliance audit with ENF-8/ENF-9. 1.3.2 adds the AI operating role; it does not
  change the baseline structural doctrine. 1.4.0 clarifies technical placement: mechanism versus policy/meaning, runtime
  versus infrastructure responsibilities, composition-root wiring, owner-internal technical detail, physical locality
  versus dependency direction, and seam admission. 1.4.1 clarifies the technical-placement split/merge trigger, removes
  an ambiguous MAY in CMP-6, restores the technical vocabulary note, and makes the neutral-technical-area import gate
  explicit in ENF-1. 1.4.2 clarifies authoritative, version-controlled storage for persistent governance artifacts
  without imposing a project path. 1.4.3 names the already-existing placement scopes **owner-internal technical area**
  and **slice-private technical area** and ties them explicitly to STR-17/STR-18 without changing structural
  obligations. 1.5.0 adds the product-first progressive explanatory model **Flow -> Feature/Use Case -> Capability ->
  Implementation**, separates Flow from individual behavior, defines when Flow becomes executable coordination,
  introduces the optional earned `Implementations/` grouping with a strict membership rule, and adds semantic proof
  targets for testability without changing the ownership-driven baseline. 1.5.1 is a consistency patch only: it removes
  one stale Flow/behavior conflation, distinguishes Capability from Owner knowledge and Contract, and aligns STR-16
  wording with the owner-local technical-placement rules in STR-18/STR-19. No structural doctrine or obligation changes.
  1.6.0 operationally hardens the same doctrine: it distinguishes technical boundary/schema validation from
  business/domain validation inside Implementations, adds observable non-numeric promotion signals, adds an illustrative
  ordinary-orchestration-versus-executable-Flow example, and makes the PILOT -> VALIDATED evidence gate explicit without
  inventing universal numeric quotas. 1.7.0 hardens adoption and decision governance: brownfield adoption
  baselines/ratchets are explicit, portable baseline composition no longer assumes a project ADR number, explicit
  instruction versus Accepted ADR has durable-supersession versus temporary-Override semantics, applicability
  boundaries/handoffs are explicit, and Example E is corrected so its timeout/retry/idempotency/compensation model does
  not contradict §7. 1.7.1 clarifies the anti-overengineering default: `one owner` prevents speculative splitting but
  MUST NOT be used to collapse already-distinct authoritative knowledge or independent reasons for change merely to
  avoid an in-process dependency, contract, or local transaction.

### 13.9 Operational validation

Normative status answers **which rules are binding**. Operational validation answers **how much real-world evidence
exists that the governance works as intended**. One does not imply the other.

| Status                | Meaning                                                                                                                                                                                                                                                              |
|-----------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **NOT YET VALIDATED** | Rules are approved, but no representative pilot evidence is recorded.                                                                                                                                                                                                |
| **PILOT**             | The governance is being used on one or more representative real repositories with evidence collection enabled.                                                                                                                                                       |
| **VALIDATED**         | A representative pilot completed; an independent reviewer (human or agent that did not author the decision) reviewed sampled outcomes; L0/L1 classifications were sampled; the project's Enforcement Map is populated; material findings have owners or resolutions. |

Promotion to VALIDATED MUST be evidence-based. A self-authored `GOVERNANCE: PASS` statement alone is insufficient.

**VALIDATED promotion gate.** The status MAY move from PILOT to VALIDATED only when **all applicable items below are
evidenced** in version-controlled pilot records:

1. At least one **representative real project/repository** has used the governance through a meaningful delivery cycle
   that included real design, implementation, review, and verification; a toy-only walkthrough is insufficient.
2. Real decisions at the levels that actually occurred in the pilot (including sampled L0/L1 work and any L2/L3 work
   that arose) were checked for correct classification and proportional ceremony.
3. The project's **Enforcement Map** is populated for all applicable normative rule IDs, with current status and
   evidence distinguished from future targets (ENF-8, ENF-9).
4. Objective consequential properties that are practical to automate have an effective gate or a recorded enforcement
   plan; claimed MACHINE-ENFORCED controls have actually run.
5. An **independent reviewer** (human or agent that did not author the sampled decision) reviewed representative
   decisions and outcomes, including at least ownership/boundary reasoning, one representative change, and the material
   architecture checks that apply.
6. Material governance findings are either resolved, explicitly accepted with owner/REVISIT/Override as applicable, or
   recorded as reasons validation cannot yet complete.
7. Recurring false positives, repeated rule-confusion, stale Overrides, and material ceremony cost were reviewed. No
   known recurring pattern that would make normal use misleading or impractical remains silently open.
8. Pilot evidence is version-controlled and sufficient to reproduce why the status changed; the accountable human
   explicitly approves the promotion.

Operational evidence SHOULD include: Override count and age; L0/L1/L2/L3 distribution; L2 decisions with valid ADR
coverage; fired REVISIT triggers and resulting actions; ratchet trend; recurring false-positive or override reasons; and
material ceremony cost. These are governance-health metrics, not product SLOs. **Portable governance does not invent
universal numeric quotas** such as a mandatory number of PRs, days, ADRs, files, or a maximum override percentage;
project-specific quantitative thresholds MAY be used only when backed by a real requirement or accepted evidence (AI-14,
QAS).

---

## §14 Forbidden theatrics and failure modes

### 14.1 Forbidden theatrics

Unless a project-specific signal overrides. An override counts only if the signal is **verified and recorded** as a FACT
or hard constraint with its source. An asserted or imagined signal is not a signal.

- repository or port "because clean"
- an interface for every dependency "for testability"
- command and query objects without a split signal
- event sourcing for audit
- domain events with no meaning and no consumer
- internal events exported as integration contracts
- one slice or module per endpoint regardless of shared invariants
- copying a heavy slice into a simple one "for consistency"
- `controller -> service -> repository` resurrected in every flow
- renaming `Services/X` to `Features/X` and calling it architecture
- `Shared` as a dumping ground
- microservice extraction to solve a naming or module problem
- message or event indirection merely to appear decoupled
- banning or requiring the words Domain, Ports, or Adapters globally
- declaring a folder, package, service, or team a "boundary" without showing what knowledge, ownership, or quality
  attribute it protects
- a shared mutable database across "independent" services
- a distributed lock used in place of a data-level constraint
- dual writes of state and events
- renaming a technical role to evade CMP-4 (`AccountsGateway`, `Processor`)
- `Domain/`, `Model/`, aggregates, or rich-domain ceremony in a module with no shared domain knowledge (CMP-10)
- one endpoint = one module = one bounded context
- a technical area that accumulates module-specific business semantics (CMP-6)
- creating `Flows/`, `Features/`, `Capabilities/`, or `Implementations/` everywhere merely to mirror the explanatory
  model
- using `Implementations/` as a catch-all for business rules, **business/domain validation**, calculations,
  orchestration, helpers, or neutral system mechanisms; technical boundary/schema validation remains allowed by §3 and
  STR-19

### 14.2 Failure modes to recognize and refuse

- Treating Vertical Slice as mandatory physical decomposition and splitting one invariant across slices.
- Treating Screaming Architecture as a folder recipe instead of a visibility test.
- Using Clean, Hexagonal, or Onion layers as acceptance criteria regardless of problem complexity.
- One interface per class, one repository per entity, or pass-through layers with no hidden knowledge.
- Choosing DDD because the application is "enterprise" while the logic is simple orchestration or CRUD.
- Creating bounded contexts from nouns or services rather than semantic ownership and language differences.
- Splitting services because the codebase is large.
- Publishing events to avoid direct calls while preserving hidden synchronous assumptions and coordinated releases.
- Centralizing similar-looking code that represents different policies changing independently.
- Duplicating an authoritative rule across slices to preserve local autonomy.
- Treating low coupling as a universal goal and replacing cheap local calls with interfaces or messages everywhere.
- Treating high cohesion as an excuse for a giant shared or core module.
- Optimizing diagram elegance while increasing operational and cognitive complexity.
- Naming "scalability", "maintainability", or "flexibility" without concrete scenarios or evidence.
- Refusing a justified project override because it violates doctrine aesthetics.
- Using the full procedure for a local code edit and paying unnecessary process cost.
- Applying the baseline composition uniformly (slice folder, owner knowledge, entry-point contract) to trivial CRUD, so
  that ceremony replaces judgment.
- Treating a module as a bounded context by definition, or a slice as a module, owner, or bounded context.
- Making owner knowledge a required folder, layer, or class.
- Replacing `ProductListController` with `ProductList implements Endpoint` as a new mandatory ceremony, or adding a
  pass-through wrapper only to avoid a small technical contract.
- Letting a technical area (for example `platform/`) become `Common`, `Shared`, or `Utils` under another name.
- Using a linter to decide whether a business name is good, or to block on naming smells.
- Modular monolith treated as a religion instead of a prior: refusing a verified distribution need, or extracting a
  service without one.
- Treating Flow as a new owner of every rule touched by an end-to-end scenario instead of leaving meaning and invariants
  with their authoritative owners.
- Turning every documented Flow into an executable `Flow` class when no independent coordination logic exists.
- Treating Capability as synonymous with module, public contract, or shared utility; or promoting a local ability before
  the shared knowledge/reason-for-change is real.
- Treating `Implementations/` as a mandatory layer or as a place for neutral Runtime/Infrastructure mechanisms.
- (Agents) Silent restructuring, unrequested refactors, idiom imports from habit, and premature stops where the task
  could finish inside existing accepted boundaries (AI-3, AI-4, AI-5, §0.4).

---

## §15 Worked examples

**Evidence status.** These are **illustrative scenarios**, not claims about a real repository. Their FACTS are scenario
givens. They do not cite invented paths, commits, measurements, or executed CI. Therefore a worked example may
demonstrate the reasoning and expected validation, but it MUST NOT claim GOVERNANCE: PASS unless the stated validation
has actually been executed against a real system or fixture.

### Example A (L1): CSV export of invoices in an internal admin app

```text
SDN-12  Invoice CSV export
Level: L1 | Owner: invoices | Behavior: ExportInvoices
Invariants: none (read-only)
Decision: keep one ExportInvoices slice; synchronous vs background execution remains the structural uncertainty because request budget and row volume are UNKNOWN.
Tests: Change -> pass by inspection; Invariant -> n/a; Knowledge-hiding -> pass by inspection | Revisit: measured volume or duration approaches the accepted request budget, or a second consumer appears.
Compliance: NOT VERIFIED (request budget and representative-volume benchmark are not provided; no runtime validation was executed).
```

### Example B (L2): subscription billing with an external payment provider

```text
ADR-0007  Billing ownership
Rules cited: INV-1, INV-2, INV-4, KNW-4, BND-6, CMP-1, CMP-2, CMP-9, DEP-1, DEP-5, DST-3, COM-3, COM-6, COM-8

FACTS         Three behaviors (StartTrial, Upgrade, Cancel) must never double-charge an account for a
              period and must keep entitlement consistent with payment state. The payment provider
              is external, versioned by the vendor, and sends webhooks.
INFERENCES    One invariant spans three behaviors. Reasons for change differ: pricing rules (product
              team) vs provider API (vendor). The provider is volatile and privileged.
DECISIONS     A `billing` module holds the subscription model and the charge/entitlement invariant
              once, as shared owner knowledge. StartTrial, Upgrade, and Cancel are slices of that
              module and thin orchestrations over it. A port and adapter exist only at the payment
              provider boundary; provider types never cross it. Webhooks are translated at the edge
              into billing slices and deduplicated by event id.
              Other owners read entitlement through a small contract or published read model.
              Everything stays in-process; events are published via an outbox.
ALTERNATIVES  One slice per endpoint, each re-checking charge state: rejected, splits the invariant (INV-4).
              Billing as a separate service: rejected, no verified independent deploy, scale, or trust
              pressure; it would turn a local transaction into distributed consistency (DST-4).
              Event sourcing: rejected, the need is audit, so an append-only audit suffices (C-05).
              Interface for every internal collaborator: rejected, no signal (DEP-2).
TRADE-OFFS    `billing` is a larger module and needs discipline so slices do not import one
              another's private workflow internals or form cycles (CMP-9, enforced by ENF-1).
VALIDATION    Required before PASS: execute duplicate-webhook and retried-upgrade scenarios;
              run an architecture check for provider-type leakage; run a representative change
              test for adding a plan type. No repository, fixture, paths, commits, or executed
              results are supplied by this illustrative scenario.
REVISIT       A second provider with different semantics (add a translation boundary); a separate
              compliance scope or release cadence for payments (consider isolation, C-08).

GOVERNANCE: NOT VERIFIED (illustrative scenario; validation specified but not executed)
```

### Example C (L2): "Split orders into a microservice for scalability"

```text
ADR-0011  Orders extraction request
Rules cited: DST-3, DST-4, DST-5, CPX-3, CPX-5, STR-15, AI-10

FACTS         One team, one deployable, one database. No load measurement presented. No
              independent release cadence or trust boundary requested.
INFERENCES    "Scalability" is hypothesized pressure (CPX-3). Coordinated releases are not
              currently a problem.
DECISIONS     Make `orders` an enforced module inside the monolith: public contract, own tables,
              automated import and schema checks (ENF-1, MIG-4). Record a REVISIT trigger.
ALTERNATIVES  Extract now: rejected, no verified pressure, and it adds the applicable §7 and §8 cost.
              Do nothing: retained as an option until real evidence shows that current orders
              ownership is blurred or unenforced; this illustrative scenario supplies no such evidence.
TRADE-OFFS    Shared release train and blast radius remain.
VALIDATION    Required before PASS: run the module-boundary checks and the load/latency test against
              an accepted target. This illustrative scenario supplies neither repository evidence
              nor an accepted performance target.
REVISIT       A measured load or latency target that the module cannot meet; independent deploy
              cadence or trust requirement; teams blocked on the same release.
OVERRIDES     If the accountable human explicitly instructs extraction anyway: state the trade-off
              once, comply, record OVR-<n>, and require C-08 plus applicable §7 and §8 obligations.

GOVERNANCE: NOT VERIFIED (illustrative scenario; repository evidence and validation are absent)
```

### Example D (illustrative): small webshop progressive view

```text
OWNER          Ordering                         Payments

FLOW           Checkout
                 |
FEATURES       PlaceOrder  ------------------>  PayOrder
                 |                               |
CAPABILITIES   CalculateTotal                   AuthorizePayment
                 |                               |
IMPLEMENTATION Persistence                      PaymentProviders/Stripe
```

The diagram is an explanatory/testability view, not a directory prescription. `Ordering` and `Payments` remain the
ownership boundaries. `Checkout` may stay only as documentation plus an end-to-end proof when no independent
coordination logic exists; if it owns sequencing, branching, failure handling, or lifecycle decisions, that coordination
MAY become executable application logic under BEH-7. `CalculateTotal` and `AuthorizePayment` remain with their owners;
Stripe translation is a Payments-specific Implementation. Neutral HTTP/database machinery remains at the technical edge.
A project MAY physically group several Payments realizations under `Payments/Implementations/` only when STR-19 is
earned.

### Example E (illustrative): ordinary orchestration vs executable Flow

**Before — ordinary use-case orchestration, not a Flow class.** One behavior sequences a few in-process owner
capabilities and has no independent lifecycle, durable state, waiting, retry policy, compensation, or coordination state
of its own:

```php
final class PlaceOrder
{
    public function __construct(
        private Carts $carts,
        private Pricing $pricing,
        private Orders $orders,
    ) {}

    public function __invoke(PlaceOrderCommand $command): void
    {
        $cart = $this->carts->forCustomer($command->customerId());
        $total = $this->pricing->totalFor($cart);

        $this->orders->place($command->customerId(), $cart, $total);
    }
}
```

Do **not** introduce `CheckoutFlow`, `OrderFlow`, or `Workflow` merely because several calls occur. The behavior remains
`PlaceOrder`; the wider Checkout Flow may stay as documentation, trace, or end-to-end proof (BEH-7). If a real external
side effect is added, its transaction/failure semantics must be handled by the applicable boundary/reliability rules;
the absence of a Flow class is never permission to ignore failure.

**After — executable coordination is earned.** A separate process becomes justified when coordination itself owns
decisions across time: persistent progress, waiting, branching, bounded retry/timeout decisions, compensation/recovery,
or lifecycle that survives request/process failure. Keep the **decision logic** explicit and deterministic:

```php
final class CheckoutProcess
{
    public function decide(
        CheckoutState $state,
        DateTimeImmutable $now,
    ): CheckoutDecision {
        if ($state->isTerminal()) {
            return CheckoutDecision::wait();
        }

        // Deadline wins before another retry can be scheduled.
        if ($state->deadlineExceeded($now)) {
            return CheckoutDecision::expire();
        }

        if ($state->paymentFailedPermanently()) {
            return CheckoutDecision::compensateOrder(
                orderId: $state->orderId(),
                idempotencyKey: $state->compensationKey(),
            );
        }

        if (
            $state->awaitingPayment()
            && $state->retryDue($now)
            && $state->canRetryPayment()
        ) {
            return CheckoutDecision::retryPayment(
                paymentId: $state->paymentId(),
                attempt: $state->nextAttemptNumber(),
                idempotencyKey: $state->nextPaymentAttemptKey(),
            );
        }

        return CheckoutDecision::wait();
    }
}
```

The process owns **coordination state and lifecycle**, not the underlying payment/order invariants. A runner persists
the process state and executes returned decisions under the applicable §7 obligations: retries are bounded and
scheduled/backed off by explicit state, external effects use idempotency keys, compensation is a new business action
rather than a rollback, and state/message handoff uses an outbox or equivalent atomic mechanism when required (COM-3,
COM-8, COM-11, COM-15, COM-16). The deadline is evaluated before retry scheduling, so waiting cannot mask timeout. This
example intentionally does **not** pretend that a process-state write and an external side effect are one local atomic
transaction. The class name need not contain `Flow`; use the business/process name that best communicates the
responsibility.

---

## §16 Anchors and scope

### 16.1 Literature anchors

These are reasoning anchors, not authorities to cargo-cult. The right column says what each changes in practice.

**Provenance.** These entries are reasoning anchors, not authorities. Provenance metadata records that a maintainer-side
review on 2026-10-06 inspected relevant local sections of **Khononov** (*Learning Domain-Driven Design* and *Balancing
Coupling in Software Design*), **Ousterhout** (*A Philosophy of Software Design*), **Richards & Ford** (*Fundamentals of
Software Architecture*, 2nd ed.), **Robert C. Martin** (*Clean Architecture* / Screaming Architecture), **Skelton &
Pais** (*Team Topologies*), and **Ford et al.** (*Building Evolutionary Architectures*, 2nd ed.). This portable document
does not embed the page/section evidence needed to reproduce that review independently; detailed claim-to-source
evidence therefore belongs in a companion provenance/source map when required. Sources not named above as locally
inspected remain external or NOT VERIFIED here unless separately evidenced; in particular, *Software Architecture: The
Hard Parts* was not found in the local corpus during that review. No literature source overrides project reality or this
document.

| Source                                                                              | Idea                                                                                                                                   | Use it to                            |
|-------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------|
| **Parnas**, "On the Criteria To Be Used in Decomposing Systems into Modules" (1972) | Decompose around design decisions and knowledge likely to change, not execution steps                                                  | Run the Knowledge-hiding test; BEH-3 |
| **Ousterhout**, A Philosophy of Software Design                                     | Deep modules; more modules are not automatically more modular                                                                          | HID rules; Depth test                |
| **Robert C. Martin**, Screaming Architecture                                        | Architecture should expose purpose and keep frameworks as details                                                                      | SCR rules; Screaming test            |
| **Richards & Ford**, Fundamentals of Software Architecture                          | Architecture characteristics drive partitioning                                                                                        | QAS; ranking quality attributes      |
| **Ford, Richards, Sadalage, Dehghani**, Software Architecture: The Hard Parts       | No best architecture, only least-harmful trade-offs; data ownership; transactions                                                      | TRADE-OFFS; §8; §11                  |
| **Evans / Vernon / Khononov**, DDD                                                  | Semantic boundaries, invariants, and consistency ownership decide when richer modeling is justified; a bounded context is not a module | DOM rules; DOM-7                     |
| **Khononov**, Balancing Coupling in Software Design                                 | Integration strength, distance, volatility                                                                                             | CPL-4                                |
| **Percival & Gregory**, appropriate coupling                                        | Local coupling between cohesive elements can be healthy                                                                                | CPL-2                                |
| **Skelton & Pais**, Team Topologies                                                 | Ownership, cognitive load, and flow as fracture planes                                                                                 | TEAM; §2.3                           |
| **Farley**, Modern Software Engineering                                             | Modularity, cohesion, controlled coupling manage complexity                                                                            | Sanity check of the whole approach   |
| **Ford et al.**, Building Evolutionary Architectures                                | Fitness functions and guided incremental change                                                                                        | ENF-3; C-16                          |
| **Kleppmann**, Designing Data-Intensive Applications                                | System of record vs derived data; replication, ordering, delivery guarantees                                                           | KNW; DAT; §7                         |
| **Feathers**, Working Effectively with Legacy Code                                  | Seams, characterization tests, dependency breaking                                                                                     | MIG-3; TST                           |
| **Humble & Farley**, Continuous Delivery; **Fowler**, Strangler Fig                 | Always-releasable incremental change; branch by abstraction                                                                            | MIG; C-15                            |
| **Nygard**, "Documenting Architecture Decisions" (article)                          | Record decisions with context and consequences                                                                                         | §13 ADR                              |

### 16.2 Out of scope and handoffs

**Applicability boundary.** The ownership/evidence/boundary/complexity principles in this document are broadly portable.
Its detailed baseline composition, Vertical Slice examples, Runtime/Infrastructure split, and messaging/distribution
obligations primarily target **application/backend/service systems**. Frontend UI architecture, mobile/device lifecycle,
data and ML pipelines, libraries/SDKs, firmware/embedded systems, and other specialized domains MAY reuse the core
principles, but their domain-specific forces and quality attributes require companion guidance. Do not force backend
module/slice/runtime shapes onto them merely for consistency.

Test pyramid and tooling, database topology and tuning, deploy granularity and pipelines, threat modeling, SRE,
performance engineering, security design, AI/agent safety and nondeterministic-model engineering, software
supply-chain/OSS licensing and provenance, dependency pinning, and secrets management are separate disciplines. They can
**force or invalidate architectural structure**, so hand off to those disciplines when their pressures are material. The
AI operating-role persona does not imply that this architecture document replaces those specialist standards.

When no specialist rule set or reviewer is available, do not silently assume an answer. State the open question as a
risk or REVISIT trigger and recommend the specialist review.

---

## Document control

|                               |                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
|-------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Governance owner role         | **Project Architecture Owner** (or an explicitly equivalent role chosen by the adopting project)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| Accountable human / tie-break | The adopting project MUST designate exactly one accountable human as final tie-break authority for unresolved governance decisions; teams/reviewers may advise but do not create ambiguous shared final authority. Until designation, bootstrap semantics in §13.2 apply: new L2/L3 ADRs remain Proposed and Overrides cannot become effective.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Change process                | §13.8                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Operational validation        | See §13.9. Current portable status: **NOT YET VALIDATED** until representative pilot evidence is attached by the adopter.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| Review cadence                | At every L3 decision, after any fired governance REVISIT that materially challenges the doctrine, and at least once per year                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| Version history               | **1.7.1** (approved 2026-10-07 by explicit accountable-human instruction): ownership clarification — P14 is explicitly a guard against speculative splitting, not a licence to merge distinct authoritative knowledge/reasons for change merely to avoid an in-process dependency, contract, or local transaction; CMP-2 makes the same boundary test operational. **1.7.0**: adoption/decision hardening — brownfield Adoption Baseline + ratchet semantics; portable baseline no longer assumes `ADR-0002`; explicit instruction vs Accepted ADR clarified as durable superseding ADR or temporary Override; application/backend/service applicability made explicit with specialist handoffs; Example E corrected for timeout/retry/idempotency/compensation semantics. **1.6.0**: operational hardening — executable Flow vs ordinary orchestration worked example; technical boundary/schema validation explicitly allowed in owner-specific Implementations while business/domain validation remains forbidden there; observable non-numeric promotion signals; explicit evidence gate for PILOT -> VALIDATED; no new folder template. **1.5.1**: consistency patch only — stale Flow/behavior conflation removed; Capability distinguished from Owner knowledge and Contract; STR-16 aligned with STR-18/STR-19 owner-local technical placement; no structural-doctrine change. **1.5.0**: product-first progressive explanatory model `Flow -> Feature/Use Case -> Capability -> Implementation`; Flow/executable-coordination distinction; optional earned `Implementations/` grouping; semantic proof targets for highly testable architecture; ownership-driven baseline unchanged. **1.4.3**: terminology clarification naming owner-internal and slice-private technical areas and tying them to STR-17/STR-18. **1.4.2**: one authoritative, version-controlled storage location for persistent governance artifacts without mandating a repository path. **1.4.1**: technical-placement split/merge trigger, CMP-6 wording, restored vocabulary mapping note, and explicit neutral-technical-area import enforcement in ENF-1. **1.4.0**: technical-placement clarification covering mechanism vs policy/meaning, runtime vs infrastructure responsibilities, composition-root wiring, owner-internal technical detail, locality vs dependency direction, and seam admission. **1.3.2** (approved 2026-10-06): AI operating role; no architecture-doctrine change. **1.3.1**: consistency patch aligning §13.4 compliance audit with ENF-8/ENF-9. **1.3.0**: proportional L3 evidence, full normative rule-ID set, Enforcement Map applicability/N/A/grouping/current-vs-target semantics, accountable-human bootstrap. **1.2.0**: enforcement status, level calibration, operational validation, governance ownership/tie-break, provenance limits, and semver. **1.1.0**: Baseline Composition and standalone hardening. **1.0.0**: initial. |
| Companion artifacts           | Adopting-project decision log (ADRs/Overrides), Boundary Register, Complexity Ledger, Constraints Register, **Enforcement Map**, and optional detailed provenance/source map                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
