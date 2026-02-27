# In Absentia — Game Design Document

## Overview

In Absentia is a persistent, cooperative civilization-building RPG. The world advances in real time. Players declare intents through contracts, pour effort into them over time, and the world mutates only when contracts resolve. Character death is permanent but feeds account-level progression. The economy is backed by labor-time. Political governance replaces PvP combat. Everything is API-first — the server is the game, and any frontend is just a consumer.

---

## 1. The Contract System

Every meaningful player action is a **contract**. Crafting, building, researching, gathering, repairing, trading — all contracts. The contract is the single mechanical abstraction that drives the entire game.

### 1.1 Contract Lifecycle

```
DECLARE → VALIDATE → RESERVE → ACTIVE → [PAUSED] → ACTIVE → RESOLVE → APPLY
```

**DECLARE**: Client sends an intent. "I want to craft a folded iron blade." "I want to construct a smithy." "I want to research mithril crystallography."

**VALIDATE**: Server checks preconditions:
- Does the character meet base stat minimums?
- Does the character have the required Knowledge? (For creation only — not required for contribution.)
- Are the materials present and unreserved?
- Is the building/workstation available and functional?
- Is the character free of conflicting commitment slots?
- Can the character hold another paused contract if this one stalls? (Pause cap check.)

**RESERVE**: On validation success, all inputs are atomically reserved:
- Materials move from "available" to "reserved" state, invisible to other contract validations.
- The workstation is flagged as occupied.
- The character enters a committed state for this contract's commitment category.

**ACTIVE**: The contract is ticking. Stamina drains from contributors, converting into Effort via the efficiency formula. Progress accumulates. Infrastructure is continuously checked.

**PAUSED**: The contract can pause for four reasons:
- `STAMINA_DEPLETED` — all contributors ran out of stamina. Auto-resumes when any contributor recovers past the resume threshold.
- `PLAYER_INITIATED` — the owner chose to pause. Frees the commitment slot. Must be manually resumed.
- `INFRASTRUCTURE_UNAVAILABLE` — a required building, workstation, or equipment is broken/destroyed. Auto-resumes when repaired.
- `RESOURCE_DEPLETED` — a continuation cost (fuel, mana) can't be paid. Auto-resumes when the resource becomes available.

While paused: materials remain reserved, progress is frozen, the pause decay timer ticks. If the timer expires without resumption, the contract auto-cancels.

**RESOLVE**: The effort bucket is full. The server evaluates the outcome using weighted expertise across all contributors.

**APPLY**: State mutations happen atomically — reserved materials consumed, output created, expertise and WU distributed to contributors, character released from commitment.

### 1.2 The Effort Bucket

Every contract has an effort bucket:
- `effort_required`: How much Effort the contract needs to complete.
- `effort_invested`: How much has been poured in so far.

The contract doesn't know about time, stamina regen, or buffs. It just has a bucket that needs filling.

**Effort generation per tick, per contributor**:

```
stamina_to_drain = min(contract.drain_rate, character.current_stamina)
character.current_stamina -= stamina_to_drain
efficiency = calculate_efficiency(character, contract)
effort_generated = stamina_to_drain × efficiency
contract.effort_invested += effort_generated
```

**Efficiency** is calculated live every tick from the character's current stats:

```
efficiency = relevant_base_stat / reference_value
```

Higher relevant stats → more Effort per stamina point → faster completion and less total stamina spent. A strong blacksmith literally finds the work easier.

**Effort is**:
- Non-refundable: stamina converted to Effort is gone.
- Non-transferable: bound to the specific contract.
- Monotonically increasing: only goes up (freezes on pause, never decreases).

The server never estimates completion time. The client has all the information to project an ETA locally (current drain rate, current efficiency, remaining effort), but that projection is cosmetic and instantly stale if conditions change.

### 1.3 Cancellation

A player can cancel an active contract:
- **Partial material loss**: Percentage of reserved materials destroyed based on progress. Early cancel recovers most. Late cancel loses most.
- **No output**: Nothing produced.
- **Stamina spent is gone**: No refund.
- **Expertise and WU gained so far are kept**: The character learned from the attempt.
- **Startup costs are gone**: Non-refundable by definition.
- **Cooldown**: Brief cooldown before re-entering a contract of the same type.

### 1.4 Commitment Categories

Contracts have commitment categories that determine conflicts:

| Category | Description | Conflicts With |
|---|---|---|
| `PHYSICAL_LABOR` | Crafting, building, mining, hauling | Other PHYSICAL_LABOR |
| `MENTAL_FOCUS` | Studying, researching, ritual casting | Other MENTAL_FOCUS |
| `CIVIC_DUTY` | Governance, inspections, public works | PHYSICAL_LABOR and MENTAL_FOCUS |
| `PASSIVE` | Resting, eating, regeneration | Nothing (reduced benefit if combined) |

A character can hold at most one PHYSICAL_LABOR and one MENTAL_FOCUS contract simultaneously. Specific contracts may define additional exclusions.

### 1.5 Pause Rules

- **Materials stay reserved while paused.** The half-built wall still has your bricks in it.
- **Commitment slot is freed** on player-initiated pause. The character can start a new contract.
- **Maximum concurrent paused contracts per character**: 2–3 (tunable). Prevents indefinite resource lockup.
- **Pause decay timer**: 24–48 hours (tunable). Auto-cancels if not resumed.
- **Resume costs nothing extra.**
- **A character can start a contract with 0 stamina.** It immediately pauses (STAMINA_DEPLETED) and waits for regen. The pause cap prevents workstation squatting.

### 1.6 Infrastructure Dependencies

Every contract specifies infrastructure it needs to remain ACTIVE:

```
contract.infrastructure: {
  "requires": [
    {"type": "building", "id": "forge_01", "condition": "functional"},
    {"type": "workstation", "id": "anvil_03", "condition": "functional"},
    {"type": "mana_pool", "zone_id": "zone_01", "min_level": 50}
  ]
}
```

These are checked **continuously** during the ACTIVE phase. If any requirement fails (anvil breaks during a raid, mana pool depletes), the contract pauses with `INFRASTRUCTURE_UNAVAILABLE`. It auto-resumes when the requirement is met again (anvil repaired, mana recovers).

### 1.7 Startup and Continuation Costs

**Startup costs**: One-time expenditure on PENDING → ACTIVE transition. Consumed immediately. Non-refundable. Examples: heating the forge (coal), preparing a ritual circle (mana), setting up scaffolding (materials not part of the final product).

**Continuation costs**: Periodic expenditures to keep the contract ACTIVE. Examples: maintaining forge temperature (coal per interval), sustaining a magical field (mana per interval). If unpayable, the contract pauses with `RESOURCE_DEPLETED` and auto-resumes when the resource becomes available.

### 1.8 Multi-Contributor Contracts

A contract is bound to a **location**, not a single character. Any character meeting stat minimums can contribute effort to an existing contract — Knowledge is NOT required to contribute.

**Contract ownership**: The character who creates the contract is the owner. They control pause/resume, cancellation, and contributor access.

**Contribution modes**:
- `OWNER_ONLY` — solo contract.
- `OPEN` — anyone meeting stat minimums in the same zone can contribute.
- `APPROVAL_REQUIRED` — contributors request access, owner approves.
- `INVITE_ONLY` — owner explicitly invites specific characters.

**Workstation capacity**: Each workstation defines `contributor_slots` (max simultaneous contributors) and optionally `min_contributors` (some contracts require a minimum, e.g. rituals requiring exactly 3 mages).

**Effort from multiple contributors**: All active contributors drain stamina and generate effort simultaneously. The contract's effort bucket fills faster with more contributors.

**The contract only auto-pauses for STAMINA_DEPLETED when ALL contributors are spent.** If one person runs dry but another is still working, the contract stays active. Individual exhausted contributors are marked `SPENT` and stop draining, but the contract continues.

**Quality determination**: On resolution, output quality is based on weighted expertise:

```
weighted_expertise = sum(
  contributor.effort_invested / contract.effort_invested × contributor.avg_expertise
  for each contributor
)
```

The quality roll uses this weighted average. If a master does 10% at 2.5 efficiency and an apprentice does 90% at 0.8, the weighted result is 0.97 — between the two, biased toward whoever contributed more.

**Output distribution**: The contract owner receives all output. Contributors receive WU and expertise. Social agreements about payment happen outside the contract system.

---

## 2. The Three-Layer Character Model

### 2.1 Base Stats → Gatekeeping & Cost Scaling

Six base stats:

| Stat | Primary Effect |
|---|---|
| **Strength** | Physical force, hauling, melee. Reduces effort/drain on physical contracts. |
| **Dexterity** | Precision, speed of execution, fine crafting. |
| **Constitution** | Stamina pool size, regen rate, resistance to injury. |
| **Intelligence** | Learning speed, reduces effort/drain on mental contracts. Research efficiency. |
| **Wisdom** | Resistance to manipulation, perception. |
| **Charisma** | Political influence, negotiation, governance buff strength. |

**Cost scaling**:

```
effort_required = recipe.base_effort / (relevant_stat / reference_value)
drain_rate = recipe.base_drain / (relevant_stat / reference_value)
```

High stats make contracts cheaper and faster. Low stats make them expensive. Stats gate contract creation via minimum thresholds (can't attempt Heavy Armor below 12 STR).

Stats do NOT affect output quality or success/failure.

### 2.2 Knowledge → Hard Prerequisite

Knowledge is binary per recipe variant. You either know how to make a folded iron blade or you don't.

**What it gates**: Contract *creation* only. You cannot create a contract for something you don't have Knowledge of.

**What it does NOT gate**: Contract *contribution*. You can help with contracts you don't have Knowledge for.

**How it's acquired**:
1. Research (three paths — see Section 5).
2. Character creation skillpoints (see Section 7).
3. Starter knowledge (species/background).
4. Being taught (future mechanic).

Knowledge does not affect quality, speed, or success chance. It's purely a gate on initiation.

### 2.3 Expertise → Quality & Reliability

Expertise is a continuous value per discipline (smithing, alchemy, masonry, etc.). It represents accumulated skill.

**What it does**:
- Determines output quality tier: CRUDE → COMMON → FINE → SUPERIOR → EXCEPTIONAL → MASTERWORK.
- Determines failure chance. Low expertise + hard recipe = risk of producing nothing (materials consumed, no output).
- Improves research material drop rates.

**How it's gained**: By investing effort into contracts of that discipline. Gained every tick while actively contributing. Scales with difficulty — working above your level gives boosted gain, working below your level gives diminished gain.

**Critical**: Contributing to a contract you don't have Knowledge for builds Expertise but does NOT grant Knowledge. You learn the motions, not the theory.

### 2.4 The Complete Flow

```
Can I create this?    → Stats (minimums) + Knowledge (binary gate) + Infrastructure (functional)
Can I contribute?     → Stats (minimums) only
How hard is it?       → Stats (cost scaling)
How good is the result? → Expertise (quality + failure chance)
What do I learn?      → Expertise (continuous gain from effort)
```

---

## 3. Stamina System

### 3.1 Stamina Pool

Every character has a stamina pool. Size determined primarily by Constitution, with modifiers from species, food, housing.

### 3.2 Regeneration

Stamina regenerates passively in real time. Base rate modified by:
- Constitution stat.
- Housing tier (better housing = faster regen).
- Food quality (consumable buffs with durations).
- Rest contracts (PASSIVE commitment — dedicating time to rest for accelerated regen).

### 3.3 Drain

Active contracts drain stamina at a rate defined by `contract.drain_rate` (modified by base stats). When stamina hits 0, the contributor is marked SPENT and stops draining. If all contributors are spent, the contract pauses.

### 3.4 No Stamina Check at Contract Creation

A character can create/join a contract with 0 stamina. The contract immediately pauses waiting for regen. The pause cap prevents abuse.

---

## 4. Recipe Variants

A single Knowledge entry can have **multiple valid recipes** (variants). Each variant uses different materials, techniques, and infrastructure; has different effort costs and quality ceilings; and requires different research materials to learn.

### 4.1 Example: Iron Sword

```
Knowledge: iron_sword (parent — abstract, not directly craftable)

Variant A: "Cast Iron Blade" (iron_sword:cast)
  materials: iron_ingot × 3, coal × 2, leather_strip × 1
  technique: cast_and_grind
  infrastructure: basic_forge, grinding_wheel
  effort_base: 80
  quality_ceiling: FINE
  research_materials_needed:
    material_research:iron_metallurgy × 3
    technique_research:casting × 2
    technique_research:hilting × 1

Variant B: "Folded Iron Blade" (iron_sword:folded)
  materials: iron_ingot × 5, flux × 1, leather_strip × 1, wood_handle × 1
  technique: fold_and_quench
  infrastructure: advanced_forge, anvil, quench_tank
  effort_base: 160
  quality_ceiling: MASTERWORK
  research_materials_needed:
    material_research:iron_metallurgy × 5
    technique_research:fold_forging × 4
    technique_research:edge_geometry × 3
    technique_research:hilting × 1
    technique_research:quenching × 2

Variant C: "Tempered Iron Blade" (iron_sword:tempered)
  materials: iron_ingot × 4, oil × 2, leather_strip × 1
  technique: forge_and_temper
  infrastructure: basic_forge, anvil, tempering_oven
  effort_base: 120
  quality_ceiling: EXCEPTIONAL
  research_materials_needed:
    material_research:iron_metallurgy × 4
    technique_research:edge_geometry × 2
    technique_research:hilting × 1
    technique_research:tempering × 3
```

**Not all techniques apply to all variants.** Quenching is needed for the folded blade but irrelevant (and harmful) to the cast blade. The research material requirements naturally enforce this — each variant demands its own specific combination.

### 4.2 Variant Discovery

Players don't see all variants from the start. Discovery happens through:
- **Encountering items** made via that variant (inspect or deconstruct).
- **Research clues** during experimental research.
- **Expertise threshold** — high enough discipline expertise reveals hidden variants.
- **Cross-pollination** — learning a variant of a related item may reveal variants of another.

### 4.3 Quality Ceilings

Each variant has a maximum quality tier. Cheap/fast methods cap at FINE. Expensive/slow methods reach MASTERWORK. This creates genuine specialization — the "right" variant depends on the situation.

---

## 5. Research System

Research is the primary path to acquiring Knowledge. It produces **physical research material items** through three distinct paths.

### 5.1 Research Materials

Research materials are tangible items in the inventory system. They represent specific categories of understanding and are:
- **Tradeable**: Can be bought, sold, and stockpiled.
- **Granular**: Dozens or hundreds of types (iron_metallurgy, hilting, edge_geometry, fold_forging, quenching, etc.).
- **Cross-disciplinary**: The same material can come from many activities. `technique_research:hilting` can come from deconstructing swords, axes, fishing rods, or spears.
- **Discoverable**: Players learn where materials drop by doing things and seeing what comes out.

### 5.2 Two-Stage Knowledge Acquisition

```
Stage 1: Research Contracts → produce Research Materials (% chance per cycle)
Stage 2: Knowledge Completion Contract → consume specific Research Materials → grant Knowledge
```

### 5.3 Path 1: Deconstruction Research

**"I have 100 iron swords. Let me take them apart."**

- Input: Existing items of a type (consumed per cycle).
- Process: Effort-bucket model. Each cycle consumes an item and rolls for research material drops.
- Output: Research materials at % chance, type depends on item properties.

Drop chance is modified by:
- **Item quality**: CRUDE (0.5×) through MASTERWORK (2.0×).
- **Character expertise**: Higher expertise in the discipline improves drop rates.

```
effective_chance = base_chance × quality_multiplier × expertise_multiplier
```

Requires: relevant workshop + analysis bench equipment.

### 5.4 Path 2: Experimental Research

**"I don't have examples, but I have materials and curiosity."**

- Input: Raw materials (consumed per trial).
- Process: Trial-based. Player selects experimental parameters via the API. Equipment records results.
- Output: Research materials at % chance based on parameter accuracy.

Each trial, the player submits parameter selections (material combinations, techniques, temperatures, tools). The server evaluates against a hidden solution space:
- **Close match**: Significant data, observations confirm you're on track.
- **Partial match**: Some data, observations hint which parameters were off.
- **Miss**: Minimal data, vague observations.

Observations accumulate in a per-character **research journal**, allowing the player to review patterns and optimize future trials.

If hidden prerequisites aren't met (expertise too low, missing foundational Knowledge), early trials immediately fail with clues about what's missing.

Can be collaborative — multiple researchers running trials on the same contract.

Requires: specialized research equipment (discipline-specific experimental apparatus).

### 5.5 Path 3: Passive Research Material Drops

**"I've made thousands of things. Understanding accumulates."**

Any crafting/production contract has a small chance of producing research materials as bonus output, based on the relationship between what you're making and what research materials exist.

```
On contract completion:
  for each related_research_material:
    chance = base_passive_chance × expertise × relevance
    if roll() <= chance:
      create research material item
```

A master blacksmith has a 1–5% chance of producing `technique_research:fold_forging` every time they complete a smithing contract, even for simple work. Over thousands of contracts, research materials accumulate passively.

This is the "5000 mithril blades" path — the apprentice who has contributed to thousands of contracts slowly accumulates the materials needed to complete a Knowledge contract without ever doing dedicated research.

### 5.6 Knowledge Completion Contract

Once a character has the required research materials for a specific Knowledge variant, they create a Knowledge Completion contract:
- Input: Specific research materials in specific quantities.
- Process: Effort-bucket (character synthesizes understanding).
- Output: Knowledge granted on completion.
- **Guaranteed success** if you have all the materials and meet stat requirements.

The hard part is gathering the research materials. Assembly is formalization.

### 5.7 Researcher as a Player Role

Because research materials are tradeable items, **research specialist** is a viable play style. A character who focuses on research contracts — deconstructing items, running experimental trials — produces materials they sell to crafters who'd rather buy understanding than grind for it. The market develops a research materials layer alongside the goods market.

---

## 6. Work Units (WU) — The Economy

### 6.1 Definition

1 minute of active contract time = 1 Work Unit (WU). WU is the fundamental currency of the economy.

### 6.2 Generation

- WU is generated **only** while a contract is ACTIVE and the character is contributing.
- Paused time does not generate WU.
- Cancelled contracts: WU generated up to the cancellation point is kept.
- WU accrues per-contributor based on their individual active_seconds.

### 6.3 Properties

- **Inflation-proof**: Supply bounded by (active players × minutes played). Can't be mined, duped, or farmed with gear.
- **Universal value anchor**: Every item has a floor value — the total WU of labor that went into it.
- **Natural taxation base**: Governance can tax WU generation directly.

### 6.4 Interesting Dynamic

Unskilled labor generates *more* WU per contract than skilled labor, because unskilled characters take longer to fill the effort bucket. A master finishes in 60 minutes (60 WU). An apprentice might take 90 minutes (90 WU). Raw currency generation favors volume; productivity (items per hour) favors skill. This creates natural economic tension.

### 6.5 Distribution on Completion

```
Per contributor:
  wu_earned = contributor.active_seconds / 60
  character_share = wu_earned × (1 - tax_rate)
  civic_treasury += wu_earned × tax_rate
```

Tax rate set by governance. Each contributor is taxed independently.

### 6.6 Auditability

Every WU that exists can be traced back to a contract that generated it. An append-only `wu_transactions` table records every generation, transfer, and expenditure.

### 6.7 Currency Approach

**WU is the only designed currency.** If rare materials emerge as commodity money through player behavior, that's emergent gameplay, not a designed system. Formalized secondary currencies can be added later if needed.

---

## 7. Character Lifecycle & Legacy

### 7.1 Character Impermanence

Characters are mortal. They die (permanently) or retire (voluntarily). This is core to the game — characters are not permanent avatars.

### 7.2 Skillpoints

Skillpoints are the account-level currency for character creation.

**Rules**:
1. **First character is free** if you have zero living characters.
2. **Every subsequent character costs a minimum skillpoint spend** (tunable, e.g. 5–10 points).
3. **Skillpoints are refunded on death and retirement.** Both return invested skillpoints to the bank.
4. **Pre-loaded Knowledge must come from the account's Knowledge Library** — only things a previous character has learned.
5. **Different Knowledge costs different amounts** — Tier 1 (1 point) through advanced (8+ points).
6. **Expertise always starts at zero.** Pre-loaded Knowledge lets you create contracts immediately but quality is terrible until you build Expertise through practice.

### 7.3 Knowledge Library

Append-only, account-level. Once any character learns a Knowledge entry, it's in the library permanently. Characters dying doesn't remove it. It's the permanent record of what the account has discovered.

### 7.4 Natural Character Limits

No artificial "max characters" cap. Your skillpoint budget IS the limit:

```
30 banked skillpoints, minimum 5 per character:
  → Max 6 additional characters (bare minimum Knowledge each)
  → Or 2 focused specialists (15 points each)
  → Or 1 powerhouse (30 points) and no alts
```

### 7.5 The Strategic Loop

1. Create first character (free). Starter Knowledge only. Research, learn, build expertise.
2. Character dies. Skillpoints refunded + prestige → more skillpoints. Knowledge enters library.
3. Create better characters using accumulated skillpoints and library. Skip early research.
4. Repeat. Library grows. Skillpoint bank grows. Characters become more capable at creation.
5. Intentional sacrifice: kill starter characters to fund a specialist master.

### 7.6 Death vs. Retirement

- **Death**: Skillpoints refunded. 10% XP → prestige → skillpoints.
- **Retirement**: Skillpoints refunded. Higher XP conversion + prestige bonus.

Retirement is always strictly better than death if you can afford ending the character voluntarily. The prestige bonus is the incentive.

---

## 8. Species & Modifiers

Species modify stat efficiency rather than granting overwhelming power.

- **Humans**: 1.0× across all stats.
- **Orcs**: 1.2× STR and CON, 0.8× INT and CHA.
- Other species may provide resistance traits, resource efficiency bonuses, unique crafting interactions.

Advanced species unlockable via account progression or hidden legacy requirements.

---

## 9. Governance

Direct PvP combat is disabled. Conflict is expressed through political governance.

### 9.1 Elections

- Restricted to sufficiently prestiged accounts.
- Term-limited offices.
- Voting restricted to characters meeting civic contribution thresholds.

### 9.2 Office Powers

Office holders grant passive sector buffs and can:
- Adjust tax rates (bounded, e.g. 0–25%).
- Allocate public building priority.
- Fund defensive upgrades from civic treasury.
- Regulate certain trade sectors.
- Designate (or redesignate) buildings.

### 9.3 Civic Treasury

Per-zone treasury funded by WU taxation. Fully transparent — anyone can query the balance and transaction history. Political campaigns revolve around tax rates, spending priorities, and transparency.

---

## 10. Raids & Threats

### 10.1 Scheduled Raids

Coordinated NPC raids occur at fixed real-world times, creating predictable but unavoidable pressure.

### 10.2 Scaling Factors

- Total zone prosperity.
- Infrastructure density.
- Average active player level.
- Stored magical reserves.

### 10.3 Consequences of Failure

- Structural damage (pauses contracts depending on damaged infrastructure).
- Loss of stored materials.
- Temporary production lockouts.
- Reduced civic morale (temporary debuffs).

### 10.4 Consequences of Success

- Prestige gain.
- Rare crafting materials.
- Territory stabilization.

### 10.5 Preparation as Collective Effort

Raid preparation (reinforcing walls, charging wards, stockpiling supplies) uses OPEN contracts. The whole server pours effort in before the deadline. Natural cooperative pressure without forced grouping.

---

## 11. Magic System

### 11.1 Mana

Mana is a measurable environmental resource. Mana pools accumulate in zones from ambient saturation, artifacts, practitioners, and destroyed mana-infused objects.

Regional mana levels influence spell potency, crafting success, and resurrection stability.

### 11.2 Infusion

Objects can be infused with mana to enhance durability or function. Each object has a maximum mana capacity. Over-infusion causes structural failure and mana release back into the nearest pool.

### 11.3 Artificing

Artificing is the structured conversion of mundane items into magical items through rune application. Runes define purpose, consume mana capacity, and must be balanced against the item's structural integrity. Improper rune balance causes instability and degradation.

---

## 12. Reincarnation

The Guild Mages maintain a central arcane source enabling soul reconstruction. Upon death, legacy conversion occurs and account unlock checks evaluate hidden achievements. Reincarnation requires minimum civic infrastructure conditions — if civic magical reserves are insufficient, resurrection penalties may apply.

---

## 13. Open Design Questions

1. **Single shard vs. multi-world**: Centralized history or parallel experiments?
2. **Time acceleration**: Is 1 real second = 1 game second, or is there a multiplier?
3. **Recipe/building data format**: JSON config files loaded at startup? Database-driven?
4. **Multiple active characters**: Can one player run contracts on multiple characters simultaneously?
5. **Anti-bot stance**: Bots are explicitly allowed. What rate limits define fair play?
6. **Audit logging depth**: Full event log with before/after snapshots for every state mutation?
7. **Teaching mechanic**: How does one character formally teach Knowledge to another?
8. **Knowledge Completion failure**: Currently guaranteed success. Should it ever fail?
