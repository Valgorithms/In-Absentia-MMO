# In Absentia — Backend Architecture Document

## Overview

The server is the single source of truth. The API is the game. Every frontend — official webview client, third-party bots, custom UIs — consumes the same API contract. No client is trusted. No client state is authoritative. The server validates everything, and the world progresses whether anyone is watching or not.

Two backend implementations are developed in parallel: Go and PHP (ReactPHP). Both implement the exact same API surface, validated against a shared OpenAPI specification and integration test suite.

---

## 1. API Design Principles

- **RESTful for state queries, WebSocket for real-time updates.**
- Every mutation goes through the contract system. There are almost no instant mutations.
- Authentication is token-based (JWT). Account-level auth, character selection is a separate step.
- Rate limiting is per-account, not per-character.
- All responses include server timestamp for client sync.
- Versioned from day one: `/api/v1/...`
- Both backends produce identical response schemas for identical inputs.

---

## 2. API Contract

### 2.1 Authentication & Account

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh

GET    /api/v1/account
GET    /api/v1/account/skillpoints
GET    /api/v1/account/knowledge-library
```

**GET /api/v1/account/skillpoints**
```json
{
  "banked": 25,
  "invested": 10,
  "lifetime_earned": 85,
  "minimum_per_character": 5,
  "living_characters": 1,
  "can_create_free": false
}
```

**GET /api/v1/account/knowledge-library**
```json
{
  "entries": [
    {"id": "iron_sword:cast", "tier": 1, "skillpoint_cost": 1, "discipline": "smithing"},
    {"id": "iron_sword:folded", "tier": 2, "skillpoint_cost": 3, "discipline": "smithing"},
    {"id": "basic_smelting", "tier": 1, "skillpoint_cost": 1, "discipline": "metallurgy"}
  ]
}
```

### 2.2 Character Management

```
POST   /api/v1/characters
GET    /api/v1/characters/{id}
DELETE /api/v1/characters/{id}                  // retire (voluntary)
GET    /api/v1/characters/{id}/stats
GET    /api/v1/characters/{id}/expertise
GET    /api/v1/characters/{id}/knowledge
GET    /api/v1/characters/{id}/inventory
GET    /api/v1/characters/{id}/inventory?type=research_material
GET    /api/v1/characters/{id}/contracts
GET    /api/v1/characters/{id}/wallet
```

**POST /api/v1/characters** (creation)
```json
{
  "species": "human",
  "base_stats": {"str": 12, "dex": 10, "con": 11, "int": 10, "wis": 9, "cha": 8},
  "preloaded_knowledge": ["iron_sword:cast", "iron_sword:folded", "basic_smelting"],
  "starting_zone": "uuid"
}
```

Validation:
- Total skillpoint cost of preloaded_knowledge >= minimum_per_character (if account has other living characters).
- Total skillpoint cost <= account.banked.
- All preloaded entries exist in account knowledge library.
- Species is unlocked for this account tier.

### 2.3 Contracts (Core Mutation API)

```
POST   /api/v1/contracts                        // declare a new contract
GET    /api/v1/contracts/{id}
DELETE /api/v1/contracts/{id}                    // cancel (owner only)
PATCH  /api/v1/contracts/{id}                    // pause/resume (owner only)

GET    /api/v1/contracts/{id}/contributions
POST   /api/v1/contracts/{id}/contribute         // join as contributor
DELETE /api/v1/contracts/{id}/contribute          // withdraw

POST   /api/v1/contracts/{id}/approve/{char_id}  // approve contributor
POST   /api/v1/contracts/{id}/invite/{char_id}   // invite contributor
```

**POST /api/v1/contracts** (creation)
```json
{
  "character_id": "uuid",
  "type": "CRAFT",
  "knowledge_variant": "iron_sword:folded",
  "quantity": 1,
  "contribution_mode": "OPEN",
  "location": {
    "zone_id": "uuid",
    "building_id": "uuid",
    "workstation_id": "uuid"
  }
}
```

**GET /api/v1/contracts/{id}** (response)
```json
{
  "id": "uuid",
  "owner_id": "uuid",
  "type": "CRAFT",
  "knowledge_variant": "iron_sword:folded",
  "status": "ACTIVE",
  "commitment_category": "PHYSICAL_LABOR",
  "effort": {
    "required": 1080.0,
    "invested": 740.2,
    "drain_rate": 0.3
  },
  "pause": null,
  "contribution_mode": "OPEN",
  "contributors": [
    {
      "character_id": "uuid",
      "status": "ACTIVE",
      "effort_invested": 108.0,
      "wu_generated": 10.0,
      "current_efficiency": 1.2
    },
    {
      "character_id": "uuid",
      "status": "ACTIVE",
      "effort_invested": 632.2,
      "wu_generated": 81.5,
      "current_efficiency": 0.8
    }
  ],
  "wu": {
    "total_generated": 91.5,
    "tax_rate": 0.10
  },
  "infrastructure": {
    "requires": [
      {"type": "building", "id": "uuid", "condition": "functional", "met": true},
      {"type": "workstation", "id": "uuid", "condition": "functional", "met": true}
    ]
  },
  "inputs": [
    {"item": "iron_ingot", "quantity": 5, "reserved": true},
    {"item": "flux", "quantity": 1, "reserved": true}
  ],
  "output_spec": {"item": "iron_sword:folded", "quantity": 1},
  "warnings": [
    {
      "type": "LOW_EXPERTISE",
      "detail": "Weighted expertise 0.97 on difficulty-2.0 recipe. Estimated failure chance: 22%."
    }
  ],
  "created_at": "2026-02-27T10:00:00Z",
  "started_at": "2026-02-27T10:00:01Z"
}
```

**PATCH /api/v1/contracts/{id}** (pause/resume)
```json
{"action": "pause"}
{"action": "resume"}
```

**Contract creation rejection responses**:
```json
{"error": "MISSING_KNOWLEDGE", "detail": "Character does not have Knowledge: iron_sword:folded", "knowledge_id": "iron_sword:folded"}
{"error": "STAT_REQUIREMENT_NOT_MET", "detail": "Requires STR 12, character has STR 9", "stat": "strength", "required": 12, "current": 9}
{"error": "MATERIALS_UNAVAILABLE", "detail": "Insufficient iron_ingot: need 5, available 3"}
{"error": "WORKSTATION_OCCUPIED", "detail": "Workstation is occupied by another contract"}
{"error": "INFRASTRUCTURE_BROKEN", "detail": "Required building forge_01 is not functional"}
{"error": "COMMITMENT_CONFLICT", "detail": "Character already committed to PHYSICAL_LABOR contract"}
{"error": "INSUFFICIENT_SKILLPOINTS", "detail": "Character creation requires minimum 5 skillpoints"}
```

### 2.4 Research

```
// Experimental research trials
POST   /api/v1/contracts/{id}/trial
GET    /api/v1/contracts/{id}/trials

// Knowledge browsing
GET    /api/v1/knowledge
GET    /api/v1/knowledge/{id}
GET    /api/v1/knowledge/{id}/research-paths?character_id={id}

// Per-character research state
GET    /api/v1/characters/{id}/research-journal
GET    /api/v1/characters/{id}/research-journal/{knowledge_id}
GET    /api/v1/characters/{id}/research-discoveries
```

**POST /api/v1/contracts/{id}/trial** (experimental research)
```json
{
  "parameters": {
    "material_combination": ["mithril_ore", "flux_powder"],
    "technique": "fold_and_quench",
    "temperature": "high",
    "tool": "precision_hammer"
  }
}
```

**Trial result response**:
```json
{
  "trial_id": "uuid",
  "materials_consumed": [
    {"item": "mithril_ore", "quantity": 2},
    {"item": "flux_powder", "quantity": 1}
  ],
  "research_materials_produced": [
    {"material": "material_research:mithril_crystallography", "quantity": 1}
  ],
  "observations": [
    "The material responded well to high temperature treatment.",
    "The folding technique showed promise but the flux ratio seems off.",
    "Equipment recorded anomalous crystalline structures at grain boundaries."
  ]
}
```

**GET /api/v1/knowledge/{id}**:
```json
{
  "id": "iron_sword",
  "display_name": "Iron Sword",
  "discipline": "smithing",
  "variants": [
    {
      "id": "iron_sword:cast",
      "display_name": "Cast Iron Blade",
      "discovered": true,
      "known": true,
      "quality_ceiling": "FINE",
      "effort_base": 80,
      "recipe_summary": "3 iron ingots, basic forge"
    },
    {
      "id": "iron_sword:folded",
      "display_name": "Folded Iron Blade",
      "discovered": true,
      "known": false,
      "quality_ceiling": "MASTERWORK",
      "effort_base": 160,
      "completion_requirements": {
        "research_materials": {
          "material_research:iron_metallurgy": {"have": 3, "need": 5},
          "technique_research:fold_forging": {"have": 0, "need": 4},
          "technique_research:edge_geometry": {"have": 2, "need": 3},
          "technique_research:hilting": {"have": 1, "need": 1},
          "technique_research:quenching": {"have": 0, "need": 2}
        }
      }
    },
    {"id": "???", "discovered": false}
  ]
}
```

**GET /api/v1/knowledge/{id}/research-paths?character_id={id}**:
```json
{
  "knowledge_id": "iron_sword:folded",
  "paths": {
    "deconstruction": {
      "available": true,
      "item_type": "iron_sword (any variant)",
      "estimated_quantity": "15-50 depending on quality",
      "equipment_required": "analysis_bench"
    },
    "experimental": {
      "available": false,
      "reason": "Missing prerequisite data.",
      "hint": "Foundational understanding of iron forging techniques is insufficient."
    },
    "passive": {
      "description": "Small chance of research material drops during smithing contracts."
    }
  }
}
```

### 2.5 World State

```
GET    /api/v1/world/time
GET    /api/v1/world/zones
GET    /api/v1/world/zones/{id}
GET    /api/v1/world/zones/{id}/buildings
GET    /api/v1/world/zones/{id}/stockpile
GET    /api/v1/world/zones/{id}/mana
GET    /api/v1/world/zones/{id}/treasury
GET    /api/v1/world/events
GET    /api/v1/world/raids/next
```

### 2.6 Economy & Trade

```
GET    /api/v1/market/listings
POST   /api/v1/market/listings                  // list item for sale (creates a contract)
POST   /api/v1/market/buy                       // purchase (creates a contract)
GET    /api/v1/market/history

POST   /api/v1/transfers                        // direct WU transfer between characters
GET    /api/v1/economy/stats                    // aggregate WU supply, velocity
```

Market listings are priced in WU:
```json
{
  "character_id": "uuid",
  "item_id": "uuid",
  "price_wu": 150,
  "quantity": 1
}
```

### 2.7 Governance

```
GET    /api/v1/governance/offices
GET    /api/v1/governance/elections
POST   /api/v1/governance/elections/{id}/vote
GET    /api/v1/governance/policies
POST   /api/v1/governance/policies              // propose policy (office holder only)
```

### 2.8 WebSocket

```
WS     /api/v1/ws
```

**Client subscribes to channels**:
```json
{"subscribe": ["character:{id}", "zone:{id}", "world"]}
```

**Events pushed**:
- `contract.started`, `contract.progress`, `contract.completed`, `contract.failed`, `contract.paused`, `contract.resumed`
- `contract.research_material_drop` — a research material was produced
- `raid.warning`, `raid.started`, `raid.resolved`
- `zone.update` — building completion, damage, infrastructure changes
- `character.stamina`, `character.levelup`, `character.expertise_gain`
- `governance.election_started`, `governance.policy_enacted`
- `world.event`

---

## 3. Database

### 3.1 Choice: PostgreSQL

Both implementations share the same Postgres instance. Postgres provides:
- JSONB for flexible metadata.
- Row-level locking for contract reservation atomicity.
- `FOR UPDATE SKIP LOCKED` for tick processing without contention.
- Excellent timestamp arithmetic.
- Advisory locks for coordinating tick execution across instances.

### 3.2 Core Schema

```sql
-- Accounts & Auth
CREATE TABLE accounts (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	email TEXT UNIQUE NOT NULL,
	password_hash TEXT NOT NULL,
	created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE account_skillpoints (
	account_id UUID PRIMARY KEY REFERENCES accounts(id),
	banked INT NOT NULL DEFAULT 0,
	invested INT NOT NULL DEFAULT 0,
	lifetime_earned INT NOT NULL DEFAULT 0
);

CREATE TABLE account_knowledge_library (
	account_id UUID NOT NULL REFERENCES accounts(id),
	knowledge_id TEXT NOT NULL,
	first_acquired_at TIMESTAMPTZ DEFAULT now(),
	first_acquired_by UUID,  -- character_id
	PRIMARY KEY (account_id, knowledge_id)
);

-- Characters
CREATE TABLE characters (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	account_id UUID NOT NULL REFERENCES accounts(id),
	name TEXT NOT NULL,
	species TEXT NOT NULL DEFAULT 'human',
	status TEXT NOT NULL DEFAULT 'alive',  -- alive, dead, retired
	str INT NOT NULL,
	dex INT NOT NULL,
	con INT NOT NULL,
	int_ INT NOT NULL,  -- "int" is reserved
	wis INT NOT NULL,
	cha INT NOT NULL,
	current_stamina DECIMAL NOT NULL DEFAULT 100,
	max_stamina DECIMAL NOT NULL DEFAULT 100,
	stamina_regen_rate DECIMAL NOT NULL DEFAULT 0.5,
	wallet_wu DECIMAL NOT NULL DEFAULT 0,
	zone_id UUID,
	skillpoints_invested INT NOT NULL DEFAULT 0,
	created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE character_knowledge (
	character_id UUID NOT NULL REFERENCES characters(id),
	knowledge_id TEXT NOT NULL,  -- variant-level: "iron_sword:folded"
	acquired_at TIMESTAMPTZ DEFAULT now(),
	acquired_via TEXT NOT NULL,  -- RESEARCH, STARTER, LEGACY, TAUGHT
	PRIMARY KEY (character_id, knowledge_id)
);

CREATE TABLE character_expertise (
	character_id UUID NOT NULL REFERENCES characters(id),
	discipline TEXT NOT NULL,
	level DECIMAL NOT NULL DEFAULT 0,
	updated_at TIMESTAMPTZ DEFAULT now(),
	PRIMARY KEY (character_id, discipline)
);

CREATE TABLE character_preloaded_knowledge (
	character_id UUID NOT NULL REFERENCES characters(id),
	knowledge_id TEXT NOT NULL,
	skillpoint_cost INT NOT NULL,
	PRIMARY KEY (character_id, knowledge_id)
);

CREATE TABLE character_variant_discoveries (
	character_id UUID NOT NULL REFERENCES characters(id),
	variant_id TEXT NOT NULL,
	discovered_at TIMESTAMPTZ DEFAULT now(),
	discovered_via TEXT NOT NULL,  -- ENCOUNTER, RESEARCH_CLUE, EXPERTISE, CROSS_POLLINATION
	PRIMARY KEY (character_id, variant_id)
);

CREATE TABLE character_research_discoveries (
	character_id UUID NOT NULL REFERENCES characters(id),
	research_material TEXT NOT NULL,
	source_activity TEXT NOT NULL,
	discovered_at TIMESTAMPTZ DEFAULT now(),
	PRIMARY KEY (character_id, research_material, source_activity)
);

-- Inventory
CREATE TABLE inventory_items (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	owner_type TEXT NOT NULL,  -- character, stockpile, market
	owner_id UUID NOT NULL,
	item_type TEXT NOT NULL,
	item_subtype TEXT,  -- for research materials: "material_research:iron_metallurgy"
	quality TEXT,  -- CRUDE, COMMON, FINE, SUPERIOR, EXCEPTIONAL, MASTERWORK
	quantity INT NOT NULL DEFAULT 1,
	reserved_by UUID,  -- contract_id, null if unreserved
	metadata JSONB,
	created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_inventory_available ON inventory_items (owner_type, owner_id, item_type)
	WHERE reserved_by IS NULL;

-- Contracts
CREATE TABLE contracts (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	owner_id UUID NOT NULL REFERENCES characters(id),
	type TEXT NOT NULL,  -- CRAFT, BUILD, GATHER, RITUAL, REPAIR, DECONSTRUCTION_RESEARCH, EXPERIMENTAL_RESEARCH, SIMPLIFIED_RESEARCH, KNOWLEDGE_COMPLETION
	knowledge_variant TEXT,  -- "iron_sword:folded" for crafting contracts
	commitment_cat TEXT NOT NULL,  -- PHYSICAL_LABOR, MENTAL_FOCUS, CIVIC_DUTY, PASSIVE
	status TEXT NOT NULL DEFAULT 'PENDING',  -- PENDING, ACTIVE, PAUSED, COMPLETED, CANCELLED, FAILED
	pause_reason TEXT,  -- STAMINA_DEPLETED, PLAYER_INITIATED, INFRASTRUCTURE_UNAVAILABLE, RESOURCE_DEPLETED
	pause_detail JSONB,
	paused_at TIMESTAMPTZ,
	pause_deadline TIMESTAMPTZ,
	created_at TIMESTAMPTZ DEFAULT now(),
	started_at TIMESTAMPTZ,

	-- Effort bucket
	effort_required DECIMAL,
	effort_invested DECIMAL NOT NULL DEFAULT 0,
	drain_rate DECIMAL NOT NULL,

	-- Data bucket (research)
	data_required DECIMAL,
	data_accumulated DECIMAL,

	-- Infrastructure
	infrastructure JSONB,
	startup_cost JSONB,
	continuation_cost JSONB,

	-- Multi-contributor
	contribution_mode TEXT NOT NULL DEFAULT 'OWNER_ONLY',
	output_recipient UUID,

	-- Research-specific
	target_knowledge TEXT,

	-- Reservation
	inputs JSONB,
	output_spec JSONB,
	location JSONB,

	metadata JSONB
);

CREATE INDEX idx_contracts_active ON contracts (status) WHERE status = 'ACTIVE';
CREATE INDEX idx_contracts_paused ON contracts (status, pause_reason) WHERE status = 'PAUSED';

CREATE TABLE contract_contributions (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	contract_id UUID NOT NULL REFERENCES contracts(id),
	character_id UUID NOT NULL REFERENCES characters(id),
	status TEXT NOT NULL DEFAULT 'ACTIVE',  -- ACTIVE, WITHDRAWN, SPENT
	joined_at TIMESTAMPTZ DEFAULT now(),
	effort_invested DECIMAL NOT NULL DEFAULT 0,
	active_seconds INT NOT NULL DEFAULT 0,
	wu_generated DECIMAL NOT NULL DEFAULT 0,
	expertise_gained JSONB NOT NULL DEFAULT '{}',
	knowledge_gained JSONB NOT NULL DEFAULT '{}',
	UNIQUE (contract_id, character_id)
);

-- Research trials (experimental research)
CREATE TABLE research_trials (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	contract_id UUID NOT NULL REFERENCES contracts(id),
	character_id UUID NOT NULL REFERENCES characters(id),
	parameters JSONB NOT NULL,
	materials_consumed JSONB,
	materials_produced JSONB,
	observations JSONB,
	created_at TIMESTAMPTZ DEFAULT now()
);

-- World
CREATE TABLE zones (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	name TEXT NOT NULL,
	prosperity DECIMAL NOT NULL DEFAULT 0,
	morale DECIMAL NOT NULL DEFAULT 100,
	mana_level DECIMAL NOT NULL DEFAULT 0,
	mana_capacity DECIMAL NOT NULL DEFAULT 1000,
	treasury_wu DECIMAL NOT NULL DEFAULT 0,
	tax_rate DECIMAL NOT NULL DEFAULT 0.05
);

CREATE TABLE buildings (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	zone_id UUID NOT NULL REFERENCES zones(id),
	type TEXT NOT NULL,
	name TEXT NOT NULL,
	condition TEXT NOT NULL DEFAULT 'functional',  -- functional, damaged, destroyed
	health DECIMAL NOT NULL DEFAULT 100,
	max_health DECIMAL NOT NULL DEFAULT 100,
	metadata JSONB
);

CREATE TABLE workstations (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	building_id UUID NOT NULL REFERENCES buildings(id),
	type TEXT NOT NULL,
	occupied_by UUID,  -- contract_id
	contributor_slots INT NOT NULL DEFAULT 1,
	min_contributors INT NOT NULL DEFAULT 1,
	condition TEXT NOT NULL DEFAULT 'functional'
);

-- Economy
CREATE TABLE wu_transactions (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	timestamp TIMESTAMPTZ DEFAULT now(),
	from_type TEXT NOT NULL,  -- CONTRACT, CHARACTER, TREASURY, MARKET
	from_id UUID NOT NULL,
	to_type TEXT NOT NULL,  -- CHARACTER, TREASURY, MARKET
	to_id UUID NOT NULL,
	amount DECIMAL NOT NULL,
	reason TEXT,
	contract_id UUID
);

CREATE TABLE market_listings (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	seller_id UUID NOT NULL REFERENCES characters(id),
	item_id UUID NOT NULL REFERENCES inventory_items(id),
	price_wu DECIMAL NOT NULL,
	quantity INT NOT NULL,
	status TEXT NOT NULL DEFAULT 'active',
	created_at TIMESTAMPTZ DEFAULT now()
);

-- Governance
CREATE TABLE governance_offices (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	zone_id UUID NOT NULL REFERENCES zones(id),
	title TEXT NOT NULL,
	holder_id UUID REFERENCES characters(id),
	term_start TIMESTAMPTZ,
	term_end TIMESTAMPTZ,
	powers JSONB
);

CREATE TABLE elections (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	office_id UUID NOT NULL REFERENCES governance_offices(id),
	status TEXT NOT NULL DEFAULT 'pending',
	voting_start TIMESTAMPTZ,
	voting_end TIMESTAMPTZ,
	candidates JSONB,
	results JSONB
);

-- Raids
CREATE TABLE raids (
	id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
	zone_id UUID NOT NULL REFERENCES zones(id),
	scheduled_at TIMESTAMPTZ NOT NULL,
	status TEXT NOT NULL DEFAULT 'scheduled',  -- scheduled, active, resolved
	scaling_factors JSONB,
	outcome JSONB,
	resolved_at TIMESTAMPTZ
);
```

### 3.3 Reservation Atomicity

The critical integrity guarantee. When a contract is created, reservation is fully atomic:

```sql
BEGIN;

-- Lock the inventory rows we need
SELECT id FROM inventory_items
WHERE owner_type = 'stockpile'
  AND owner_id = $zone_id
  AND item_type = $material_type
  AND reserved_by IS NULL
ORDER BY id
LIMIT $quantity
FOR UPDATE;

-- If we got fewer rows than needed: ROLLBACK

-- Reserve them
UPDATE inventory_items
SET reserved_by = $contract_id
WHERE id = ANY($selected_ids);

-- Lock the workstation
UPDATE workstations
SET occupied_by = $contract_id
WHERE id = $workstation_id
  AND occupied_by IS NULL;

-- If workstation was already occupied: ROLLBACK

-- Create the contract
INSERT INTO contracts (...) VALUES (...);

-- Create owner contribution record
INSERT INTO contract_contributions (...) VALUES (...);

COMMIT;
```

If any step fails, nothing is reserved. No partial state. No double-spending.

### 3.4 Shared Migrations

Both backends use the same SQL migration files. Migration runner is a standalone tool.

---

## 4. World Tick System

### 4.1 Tick Cadences

**Fast tick (every 1–5 seconds)**:
- Advance active contract effort.
- Drain contributor stamina.
- Generate expertise per contributor.
- Check contract completion conditions.
- Roll for research material drops (on research contracts).
- Process stamina regeneration for all characters.
- Check infrastructure requirements on active contracts.
- Check resume conditions on paused contracts.

**Slow tick (every 60 seconds)**:
- Evaluate civic morale modifiers.
- Process infrastructure decay.
- Update economic aggregates.
- Check raid scheduling windows.
- Process political term timers.
- Process continuation costs on active contracts.
- Check pause decay timers.

**Event tick (scheduled)**:
- Raid execution.
- Election cycles.
- World events.
- Mana storms.

### 4.2 Lazy Evaluation

For entities no one is interacting with:
- Store `last_evaluated_at` timestamp.
- On any query or interaction, fast-forward state to `now` before responding.
- A character offline mid-contract gets their contract resolved retroactively on first API call.

### 4.3 Fast Tick Logic (Pseudocode)

```
// Phase 1: Stamina regeneration (before contract processing)
for each alive character:
  character.current_stamina = min(
    character.max_stamina,
    character.current_stamina + character.stamina_regen_rate × tick_interval
  )

// Phase 2: Active contract processing
for each contract WHERE status = 'ACTIVE':

  // 2a: Infrastructure check
  for each req in contract.infrastructure.requires:
    if not meets_requirement(req):
      contract.status = 'PAUSED'
      contract.pause_reason = 'INFRASTRUCTURE_UNAVAILABLE'
      contract.pause_detail = req
      contract.paused_at = now()
      contract.pause_deadline = now() + PAUSE_DECAY_DURATION
      emit_ws('contract.paused', contract)
      continue to next contract

  // 2b: Contributor processing
  any_active = false
  for each contribution WHERE status = 'ACTIVE':
    character = load(contribution.character_id)
    drain = min(contract.drain_rate, character.current_stamina)

    if drain == 0:
      contribution.status = 'SPENT'
      continue

    any_active = true
    character.current_stamina -= drain
    efficiency = calculate_efficiency(character, contract)
    effort = drain × efficiency

    // Accumulate effort
    contribution.effort_invested += effort
    contract.effort_invested += effort

    // Track WU
    contribution.active_seconds += tick_interval_sec
    contribution.wu_generated = contribution.active_seconds / 60.0

    // Expertise accrual
    exp_gain = effort × expertise_rate(contract) × diminishing(character, contract.discipline)
    contribution.expertise_gained[contract.discipline] += exp_gain
    character.expertise[contract.discipline] += exp_gain

    // Research material drops (for research contracts, per cycle)
    if contract is research type and cycle_complete:
      for each possible_output:
        if roll() <= effective_chance:
          create_item(possible_output)
          emit_ws('contract.research_material_drop', ...)

  // 2c: All contributors spent?
  if not any_active:
    contract.status = 'PAUSED'
    contract.pause_reason = 'STAMINA_DEPLETED'
    contract.paused_at = now()
    contract.pause_deadline = now() + PAUSE_DECAY_DURATION
    emit_ws('contract.paused', contract)

  // 2d: Completion check
  if contract.effort_invested >= contract.effort_required:
    resolve_contract(contract)
    emit_ws('contract.completed', contract)

// Phase 3: Paused contract checks
for each contract WHERE status = 'PAUSED':

  // Pause decay
  if now() >= contract.pause_deadline:
    auto_cancel(contract)
    continue

  // STAMINA_DEPLETED: check if any contributor has recovered
  if contract.pause_reason == 'STAMINA_DEPLETED':
    for each contribution WHERE status = 'SPENT':
      character = load(contribution.character_id)
      if character.current_stamina >= RESUME_THRESHOLD:
        contribution.status = 'ACTIVE'
    if any contribution is ACTIVE:
      contract.status = 'ACTIVE'
      contract.pause_reason = null
      emit_ws('contract.resumed', contract)

  // INFRASTRUCTURE_UNAVAILABLE: check if requirements are met
  if contract.pause_reason == 'INFRASTRUCTURE_UNAVAILABLE':
    all_met = check_all_requirements(contract)
    if all_met:
      contract.status = 'ACTIVE'
      contract.pause_reason = null
      emit_ws('contract.resumed', contract)

  // RESOURCE_DEPLETED: check if resources available
  if contract.pause_reason == 'RESOURCE_DEPLETED':
    if can_pay_continuation(contract):
      contract.status = 'ACTIVE'
      contract.pause_reason = null
      emit_ws('contract.resumed', contract)
```

### 4.4 Contract Resolution

```
resolve_contract(contract):
  if contract.type == 'KNOWLEDGE_COMPLETION':
    // Guaranteed success — grant knowledge
    character = load(contract.owner_id)
    character.knowledge.add(contract.target_knowledge)
    account.knowledge_library.add(contract.target_knowledge)
    return

  if contract.type in [DECONSTRUCTION_RESEARCH, EXPERIMENTAL_RESEARCH, ...]:
    // Research contracts don't produce items on "completion"
    // They produce materials during active ticks
    // "Completion" means the research effort is finished
    return

  // Crafting/Building resolution
  weighted_expertise = 0
  total_effort = contract.effort_invested
  for each contribution:
    weight = contribution.effort_invested / total_effort
    expertise = character.expertise[contract.discipline]
    weighted_expertise += weight × expertise

  quality_tier = roll_quality(weighted_expertise, recipe.difficulty)
  failure_chance = calculate_failure(weighted_expertise, recipe.difficulty)

  if roll() < failure_chance:
    contract.status = 'FAILED'
    // Materials consumed, no output. Expertise/WU already gained.
    return

  create_output(contract, quality_tier)

  // Passive research material drops on craft completion
  for each related_research_material:
    chance = base_passive_chance × expertise × relevance
    if roll() <= chance:
      create_item(related_research_material)

  contract.status = 'COMPLETED'

  // WU distribution
  for each contribution:
    wu = contribution.wu_generated
    tax = wu × zone.tax_rate
    character.wallet_wu += (wu - tax)
    zone.treasury_wu += tax
    record_wu_transaction(...)
```

---

## 5. Go Implementation

### 5.1 Constraints

- Zero external dependencies except `golang.org/x/*` libraries.
- Exception: `pgx` for Postgres (or `lib/pq` — implementing the wire protocol is not worth it).
- Standard library `net/http` for HTTP (Go 1.22+ has method + path param support in `http.ServeMux`).
- `encoding/json` for serialization.
- `crypto/*` for JWT.
- WebSocket via `x/net` or hand-rolled upgrade.

### 5.2 Project Structure

```
backend-go/
├── cmd/
│   └── server/
│       └── main.go
├── internal/
│   ├── server/
│   │   ├── server.go
│   │   ├── router.go
│   │   └── websocket.go
│   ├── auth/
│   │   ├── handler.go
│   │   ├── token.go
│   │   └── middleware.go
│   ├── account/
│   │   ├── handler.go
│   │   ├── service.go
│   │   └── repo.go
│   ├── character/
│   │   ├── handler.go
│   │   ├── service.go
│   │   └── repo.go
│   ├── contract/
│   │   ├── handler.go
│   │   ├── service.go
│   │   ├── repo.go
│   │   ├── resolver.go
│   │   └── types.go
│   ├── research/
│   │   ├── handler.go
│   │   ├── service.go
│   │   ├── trial.go
│   │   └── repo.go
│   ├── world/
│   │   ├── handler.go
│   │   ├── tick.go
│   │   ├── zone.go
│   │   ├── building.go
│   │   └── mana.go
│   ├── economy/
│   │   ├── handler.go
│   │   ├── market.go
│   │   └── repo.go
│   ├── governance/
│   │   ├── handler.go
│   │   ├── election.go
│   │   └── repo.go
│   ├── raid/
│   │   ├── scheduler.go
│   │   ├── executor.go
│   │   └── scaling.go
│   ├── skill/
│   │   └── calculator.go
│   └── db/
│       ├── postgres.go
│       └── tx.go
├── migrations/
│   └── (symlink to shared)
└── go.mod
```

### 5.3 Key Patterns

**Router**: `http.ServeMux` (Go 1.22+). Method + path patterns. No third-party router.

**Middleware**: `func(http.Handler) http.Handler` chain. Auth → Rate Limit → Logging → Handler.

**Tick loop**:
```go
func (w *WorldTicker) Run(ctx context.Context) {
	fast := time.NewTicker(time.Second)
	slow := time.NewTicker(time.Minute)
	defer fast.Stop()
	defer slow.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-fast.C:
			w.processFastTick(ctx)
		case <-slow.C:
			w.processSlowTick(ctx)
		}
	}
}
```

**Contract resolution**: Inside `processFastTick()`. Query contracts with `FOR UPDATE SKIP LOCKED` so multiple instances don't fight. Resolve, apply, emit WebSocket events.

**WebSocket hub**: Central goroutine manages subscriptions. Clients subscribe to topics. Events fanned out via channels.

---

## 6. PHP (ReactPHP) Implementation

### 6.1 Architecture

Long-lived process on ReactPHP's event loop. Not traditional PHP request/response.

Dependencies:
- `react/http` — HTTP server
- `react/socket` — TCP/WebSocket
- `react/event-loop` — core loop
- `react/promise` — async flow
- Async Postgres driver (e.g. `voryx/pgasync`)

### 6.2 Project Structure

```
backend-php/
├── bin/
│   └── server.php
├── src/
│   ├── Server/
│   │   ├── HttpServer.php
│   │   ├── WebSocketServer.php
│   │   └── Router.php
│   ├── Auth/
│   │   ├── AuthHandler.php
│   │   ├── TokenService.php
│   │   └── AuthMiddleware.php
│   ├── Account/
│   │   ├── AccountHandler.php
│   │   ├── AccountService.php
│   │   └── AccountRepository.php
│   ├── Character/
│   │   ├── CharacterHandler.php
│   │   ├── CharacterService.php
│   │   └── CharacterRepository.php
│   ├── Contract/
│   │   ├── ContractHandler.php
│   │   ├── ContractService.php
│   │   ├── ContractRepository.php
│   │   ├── ContractResolver.php
│   │   └── ContractTypes.php
│   ├── Research/
│   │   ├── ResearchHandler.php
│   │   ├── ResearchService.php
│   │   ├── TrialService.php
│   │   └── ResearchRepository.php
│   ├── World/
│   │   ├── WorldHandler.php
│   │   ├── WorldTicker.php
│   │   ├── ZoneService.php
│   │   ├── BuildingService.php
│   │   └── ManaService.php
│   ├── Economy/
│   │   ├── MarketHandler.php
│   │   └── MarketService.php
│   ├── Governance/
│   │   ├── GovernanceHandler.php
│   │   ├── ElectionService.php
│   │   └── PolicyService.php
│   ├── Raid/
│   │   ├── RaidScheduler.php
│   │   ├── RaidExecutor.php
│   │   └── RaidScaling.php
│   ├── Skill/
│   │   └── SkillCalculator.php
│   └── Database/
│       ├── ConnectionPool.php
│       └── TransactionHelper.php
├── migrations/
│   └── (symlink to shared)
├── composer.json
└── composer.lock
```

### 6.3 Key Patterns

**Tick loop**:
```php
$loop->addPeriodicTimer(1.0, function () use ($worldTicker) {
    $worldTicker->fastTick();
});

$loop->addPeriodicTimer(60.0, function () use ($worldTicker) {
    $worldTicker->slowTick();
});
```

**Single-threaded advantage**: ReactPHP is single-threaded. Contract resolution within a tick is sequential — no race conditions within a tick by design. The DB still needs transactional integrity for multi-instance scenarios.

**Routing**: Match method + path pattern → handler callable. No framework needed.

**WebSocket**: Subscriber map: `topic → [conn1, conn2, ...]`. Push events on contract state changes.

---

## 7. Shared Infrastructure

### 7.1 OpenAPI Specification

A single `openapi.yaml` is the canonical API reference. Both backends validate against it. The frontend generates TypeScript types from it.

```
shared/
├── api/
│   └── openapi.yaml
├── migrations/
│   └── *.sql
├── gamedata/
│   └── *.json           // recipes, knowledge entries, species, buildings
└── tests/
    └── integration/
        └── *.test.ts    // language-agnostic integration tests
```

### 7.2 Integration Test Suite

Language-agnostic (TypeScript/Bun recommended). Runs against either backend:

- Full contract lifecycle: create account → create character → declare contract → wait → verify completion → check inventory.
- Reservation atomicity: two simultaneous contracts for the same scarce resource → only one succeeds.
- Cancellation: mid-contract cancel → verify partial material recovery.
- Lazy evaluation: create contract → wait past completion → query → verify retroactive resolution.
- Multi-contributor: two characters contributing → verify weighted expertise on output.
- Research flow: deconstruction → research material drops → knowledge completion.
- WebSocket event delivery.
- Auth flows and error responses.

### 7.3 Game Data

Recipes, knowledge entries, research material definitions, species, building types — all defined in JSON config files. Loaded at server startup. Cached in memory. No recompile needed to change game data.

### 7.4 Monorepo Layout

```
in-absentia/
├── shared/
│   ├── api/
│   │   └── openapi.yaml
│   ├── migrations/
│   │   └── *.sql
│   ├── gamedata/
│   │   └── *.json
│   └── tests/
│       └── integration/
├── backend-go/
├── backend-php/
├── frontend/
├── docs/
│   ├── 01-game-design.md
│   ├── 02-backend-architecture.md
│   └── 03-frontend-architecture.md
├── docker-compose.yml
└── Makefile
```

---

## 8. Implementation Phases

### Phase 1: Foundation (Weeks 1–3)
Database schema (accounts, characters, inventory, contracts, skills, zones). Auth endpoints. Character CRUD. Contract creation for CRAFT type. Handful of hardcoded recipes. Fast tick loop resolving contracts. Effort bucket model with stamina drain. Basic WebSocket events. Integration tests validating parity between Go and PHP.

### Phase 2: World & Commitment (Weeks 4–6)
Zones with buildings and workstations. Infrastructure dependencies (continuous checks, auto-pause/resume). Stamina system (regen, food/housing modifiers). Commitment categories and conflict rules. Contract pausing (all four reasons). Multi-contributor contracts. Startup and continuation costs. Cancellation with partial material recovery. Lazy evaluation.

### Phase 3: Skills & Research (Weeks 7–10)
Full three-layer model (stats, knowledge, expertise). Recipe variants. Expertise-based quality/failure rolls. Research material items. Deconstruction research contracts. Experimental research with trial system. Passive research material drops. Knowledge completion contracts. Research journal API. Variant discovery system.

### Phase 4: Economy (Weeks 11–13)
WU generation and distribution. WU taxation. Market system (listings, purchasing as contracts). WU transfers. Audit trail (wu_transactions). Economy stats API. Skillpoint system. Character creation with pre-loaded knowledge. Account knowledge library.

### Phase 5: Threats & Governance (Weeks 14–17)
Raid scheduler and execution. Raid scaling and consequences. Infrastructure damage from raids. Character death and skillpoint refund. Retirement. Election system. Office holder powers. Tax adjustment. Civic treasury management. Policy proposal system.

### Phase 6: Magic & Polish (Weeks 18–22)
Mana pools and regional effects. Infusion mechanics. Artificing. Species selection with modifiers. Load testing. Race condition audit. WebSocket reconnection. Rate limiting. Bot-friendliness audit. Documentation. Deployment pipeline.
