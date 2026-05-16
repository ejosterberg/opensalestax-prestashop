# Specs — opensalestax-prestashop

> Spec-kit conventions for this repo. Read these before writing code.

## What lives here

| Path | Purpose |
|---|---|
| `constitution.md` | Non-negotiable principles. Read FIRST. |
| `current-state.md` | Snapshot: shipped phases, code shape, quality bar, sibling-project map. Refresh after every phase. |
| `handoff.md` | What the next session should pick up. Always-current. |
| `research/*.md` | Platform-research notes (informs decisions). Append-only — historical record. |
| `decisions/NNN-<slug>.md` | Architecture Decision Records (ADRs). One file per locked decision. |
| `phase-NN-<slug>/` | (Future) Per-phase spec / plan / tasks if/when work meets the three-step rule. |

## At session start

Always, in this order:

1. Read `constitution.md` — non-negotiable conventions.
2. Read `current-state.md` — what's done, what's next.
3. Read `handoff.md` — specifically what to start on.
4. Skim `decisions/` — locked architectural decisions.
5. Skim `research/` — platform context.

## At session end

1. Update `current-state.md` if anything material shipped.
2. Update `handoff.md` to point the next session at the right
   work.
3. If you locked a new decision, write it as
   `decisions/NNN-<slug>.md` (next available NNN, zero-padded).

## Don't

- Don't edit research notes after the fact (append-only).
- Don't mutate ADRs after they're "Accepted" — supersede with a
  new ADR that references the old one.
- Don't bloat specs with code. Specs describe; code implements.

See `~/.claude/spec-kit-playbook.md` for the broader spec-kit
methodology.
