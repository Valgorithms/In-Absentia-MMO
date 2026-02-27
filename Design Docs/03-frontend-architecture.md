# In Absentia — Frontend Architecture Document

## Overview

The official frontend is a **webview shell** running a **Bun-built web application**. It consumes the exact same API that any third-party client, bot, or custom UI would use. The webview frontend has **zero privileged access** — same JWT auth, same REST endpoints, same WebSocket. If the webview can do it, a curl script can do it.

This is deliberate. Players are explicitly allowed to build their own interfaces and bot networks. The API is the game; this frontend is one possible way to play it.

---

## 1. Tech Stack

| Layer | Choice | Rationale |
|---|---|---|
| **Build tool** | Bun | Fast bundling, native TypeScript, built-in test runner |
| **Native shell** | webview-bun | Lightweight native window, no Electron overhead |
| **UI framework** | Vanilla TS + lit-html (or Preact) | Minimal footprint, no framework overhead for a game UI |
| **State management** | Custom reactive store | Thin wrapper around WebSocket events and REST responses |
| **Styling** | Plain CSS, CSS custom properties | No build-time CSS framework needed |
| **API types** | Generated from OpenAPI spec | Single source of truth, always in sync |

### 1.1 Why Not React/Electron/etc.

This is a game UI, not a SaaS dashboard. The priorities are:
- **Information density**: Lots of data on screen simultaneously (contracts, stamina, inventory, world map).
- **Real-time updates**: WebSocket events need to flow into the UI with minimal latency.
- **Low overhead**: The game runs alongside the webview. CPU/memory should go to gameplay, not framework reconciliation.
- **Simplicity**: A thin rendering layer over the API is easier to maintain and debug.

Preact is acceptable if a component model is desired. Full React or Angular is overkill. Svelte or Solid are fine alternatives if the team prefers them.

---

## 2. Project Structure

```
frontend/
├── src/
│   ├── main.ts                      // webview bootstrap
│   ├── app.ts                       // app shell, screen routing
│   │
│   ├── api/
│   │   ├── client.ts                // REST client (fetch wrapper)
│   │   ├── ws.ts                    // WebSocket manager (connect, reconnect, subscribe)
│   │   ├── types.ts                 // generated from OpenAPI spec
│   │   └── endpoints.ts             // typed endpoint helpers
│   │
│   ├── state/
│   │   ├── store.ts                 // reactive state container
│   │   ├── character.ts             // character state slice
│   │   ├── contracts.ts             // active contracts, progress tracking
│   │   ├── inventory.ts             // items, research materials
│   │   ├── world.ts                 // zones, buildings, mana
│   │   ├── economy.ts               // wallet, market state
│   │   └── governance.ts            // offices, elections, policies
│   │
│   ├── ui/
│   │   ├── screens/
│   │   │   ├── login.ts             // auth flow
│   │   │   ├── character-select.ts  // character list + creation
│   │   │   ├── character-create.ts  // species, stats, knowledge preloading
│   │   │   ├── world-map.ts         // zone overview, navigation
│   │   │   ├── zone-detail.ts       // buildings, workstations, stockpile
│   │   │   ├── crafting.ts          // recipe selection, variant picker, contract creation
│   │   │   ├── research.ts          // research paths, trial interface, journal
│   │   │   ├── building.ts          // construction contracts
│   │   │   ├── market.ts            // listings, buy/sell, price history
│   │   │   ├── governance.ts        // elections, policies, treasury
│   │   │   └── account.ts           // skillpoints, knowledge library, prestige
│   │   │
│   │   ├── components/
│   │   │   ├── contract-tracker.ts  // persistent: active contracts with progress bars
│   │   │   ├── stamina-bar.ts       // current stamina, regen rate, projected recovery
│   │   │   ├── effort-meter.ts      // per-contract effort bucket visualization
│   │   │   ├── inventory-panel.ts   // items grid, research materials section
│   │   │   ├── expertise-display.ts // discipline levels, gain indicators
│   │   │   ├── knowledge-tree.ts    // browsable tree with variants, discovery state
│   │   │   ├── research-journal.ts  // trial history, observations, pattern viewer
│   │   │   ├── contributor-list.ts  // who's working on a contract
│   │   │   ├── wu-display.ts        // wallet balance, recent transactions
│   │   │   ├── raid-countdown.ts    // next raid window, preparation status
│   │   │   ├── notification-feed.ts // WS event log, filterable
│   │   │   └── modal.ts             // generic modal container
│   │   │
│   │   └── styles/
│   │       ├── reset.css
│   │       ├── tokens.css           // CSS custom properties (colors, spacing, typography)
│   │       ├── layout.css           // grid/flex utilities
│   │       └── components.css       // component-specific styles
│   │
│   └── native/
│       └── webview.ts               // webview-bun integration
│
├── public/
│   └── index.html
├── scripts/
│   └── generate-types.ts            // OpenAPI → TypeScript type generator
├── bun.lock
└── package.json
```

---

## 3. Webview Integration

### 3.1 Entry Point

```typescript
// src/native/webview.ts
import { Webview } from "webview-bun"

const DEV = process.env.NODE_ENV === "development"
const DEV_PORT = 3000
const BUILD_DIR = "./dist"

const webview = new Webview()
webview.title = "In Absentia"
webview.size = { width: 1280, height: 720, hint: 0 }

if (DEV) {
	webview.navigate(`http://localhost:${DEV_PORT}`)
} else {
	webview.navigate(`file://${process.cwd()}/${BUILD_DIR}/index.html`)
}

webview.run()
```

### 3.2 Development Workflow

```bash
# Terminal 1: Bun dev server with hot reload
bun run dev

# Terminal 2: Webview pointing at dev server
bun run native:dev
```

### 3.3 Production Build

```bash
# Build web assets
bun run build    # outputs to dist/

# Run native webview loading local files
bun run native:prod
```

In production, the webview loads `dist/index.html` directly from the filesystem. The API server is remote — the webview connects to it over HTTP/WS like any other client.

---

## 4. API Layer

### 4.1 REST Client

A thin fetch wrapper with typed endpoints. No axios or heavy HTTP libraries.

```typescript
// src/api/client.ts
const API_BASE = "https://api.inabsentia.game/api/v1"

let authToken: string | null = null

export function setToken(token: string) {
	authToken = token
}

async function request<T>(method: string, path: string, body?: any): Promise<T> {
	const headers: Record<string, string> = {
		"Content-Type": "application/json"
	}
	if (authToken) {
		headers["Authorization"] = `Bearer ${authToken}`
	}

	const res = await fetch(`${API_BASE}${path}`, {
		method,
		headers,
		body: body ? JSON.stringify(body) : undefined
	})

	if (!res.ok) {
		const err = await res.json()
		throw new ApiError(res.status, err)
	}

	return res.json()
}

export const api = {
	get: <T>(path: string) => request<T>("GET", path),
	post: <T>(path: string, body?: any) => request<T>("POST", path, body),
	patch: <T>(path: string, body?: any) => request<T>("PATCH", path, body),
	delete: <T>(path: string) => request<T>("DELETE", path)
}
```

### 4.2 Typed Endpoint Helpers

```typescript
// src/api/endpoints.ts
import { api } from "./client"
import type {
	Account, Character, Contract, ContractCreateRequest,
	KnowledgeEntry, SkillpointInfo, MarketListing,
	TrialRequest, TrialResult, ResearchPaths
} from "./types"

export const auth = {
	login: (email: string, password: string) =>
		api.post<{token: string}>("/auth/login", { email, password }),
	register: (email: string, password: string) =>
		api.post<{token: string}>("/auth/register", { email, password }),
	refresh: () =>
		api.post<{token: string}>("/auth/refresh")
}

export const account = {
	get: () => api.get<Account>("/account"),
	skillpoints: () => api.get<SkillpointInfo>("/account/skillpoints"),
	library: () => api.get<{entries: KnowledgeEntry[]}>("/account/knowledge-library")
}

export const characters = {
	list: () => api.get<Character[]>("/account/characters"),
	get: (id: string) => api.get<Character>(`/characters/${id}`),
	create: (data: any) => api.post<Character>("/characters", data),
	retire: (id: string) => api.delete<void>(`/characters/${id}`),
	stats: (id: string) => api.get<any>(`/characters/${id}/stats`),
	expertise: (id: string) => api.get<any>(`/characters/${id}/expertise`),
	knowledge: (id: string) => api.get<any>(`/characters/${id}/knowledge`),
	inventory: (id: string, type?: string) =>
		api.get<any>(`/characters/${id}/inventory${type ? `?type=${type}` : ""}`),
	wallet: (id: string) => api.get<any>(`/characters/${id}/wallet`),
	researchJournal: (id: string, knowledgeId?: string) =>
		api.get<any>(`/characters/${id}/research-journal${knowledgeId ? `/${knowledgeId}` : ""}`)
}

export const contracts = {
	create: (data: ContractCreateRequest) => api.post<Contract>("/contracts", data),
	get: (id: string) => api.get<Contract>(`/contracts/${id}`),
	cancel: (id: string) => api.delete<void>(`/contracts/${id}`),
	pause: (id: string) => api.patch<Contract>(`/contracts/${id}`, { action: "pause" }),
	resume: (id: string) => api.patch<Contract>(`/contracts/${id}`, { action: "resume" }),
	contributions: (id: string) => api.get<any>(`/contracts/${id}/contributions`),
	contribute: (id: string, charId: string) =>
		api.post<any>(`/contracts/${id}/contribute`, { character_id: charId }),
	withdraw: (id: string) => api.delete<void>(`/contracts/${id}/contribute`),
	submitTrial: (id: string, params: TrialRequest) =>
		api.post<TrialResult>(`/contracts/${id}/trial`, params),
	trials: (id: string) => api.get<TrialResult[]>(`/contracts/${id}/trials`)
}

export const knowledge = {
	browse: () => api.get<any>("/knowledge"),
	get: (id: string) => api.get<KnowledgeEntry>(`/knowledge/${id}`),
	researchPaths: (id: string, charId: string) =>
		api.get<ResearchPaths>(`/knowledge/${id}/research-paths?character_id=${charId}`)
}

export const world = {
	time: () => api.get<any>("/world/time"),
	zones: () => api.get<any>("/world/zones"),
	zone: (id: string) => api.get<any>(`/world/zones/${id}`),
	buildings: (zoneId: string) => api.get<any>(`/world/zones/${zoneId}/buildings`),
	treasury: (zoneId: string) => api.get<any>(`/world/zones/${zoneId}/treasury`),
	nextRaid: () => api.get<any>("/world/raids/next")
}

export const market = {
	listings: () => api.get<MarketListing[]>("/market/listings"),
	list: (data: any) => api.post<MarketListing>("/market/listings", data),
	buy: (data: any) => api.post<any>("/market/buy", data),
	history: () => api.get<any>("/market/history")
}

export const governance = {
	offices: () => api.get<any>("/governance/offices"),
	elections: () => api.get<any>("/governance/elections"),
	vote: (electionId: string, data: any) =>
		api.post<any>(`/governance/elections/${electionId}/vote`, data),
	policies: () => api.get<any>("/governance/policies"),
	propose: (data: any) => api.post<any>("/governance/policies", data)
}
```

### 4.3 WebSocket Manager

```typescript
// src/api/ws.ts
type EventHandler = (data: any) => void

class WebSocketManager {
	private ws: WebSocket | null = null
	private handlers: Map<string, Set<EventHandler>> = new Map()
	private subscriptions: Set<string> = new Set()
	private reconnectTimer: number | null = null
	private url: string

	constructor(url: string) {
		this.url = url
	}

	connect(token: string) {
		this.ws = new WebSocket(`${this.url}?token=${token}`)

		this.ws.onopen = () => {
			if (this.subscriptions.size > 0) {
				this.ws!.send(JSON.stringify({
					subscribe: Array.from(this.subscriptions)
				}))
			}
		}

		this.ws.onmessage = (event) => {
			const msg = JSON.parse(event.data)
			const handlers = this.handlers.get(msg.type)
			if (handlers) {
				for (const handler of handlers) {
					handler(msg.data)
				}
			}
		}

		this.ws.onclose = () => {
			this.scheduleReconnect(token)
		}
	}

	subscribe(channels: string[]) {
		for (const ch of channels) {
			this.subscriptions.add(ch)
		}
		if (this.ws?.readyState === WebSocket.OPEN) {
			this.ws.send(JSON.stringify({ subscribe: channels }))
		}
	}

	on(eventType: string, handler: EventHandler) {
		if (!this.handlers.has(eventType)) {
			this.handlers.set(eventType, new Set())
		}
		this.handlers.get(eventType)!.add(handler)
		return () => this.handlers.get(eventType)?.delete(handler)
	}

	private scheduleReconnect(token: string) {
		if (this.reconnectTimer) return
		this.reconnectTimer = setTimeout(() => {
			this.reconnectTimer = null
			this.connect(token)
		}, 3000) as any
	}

	disconnect() {
		if (this.reconnectTimer) {
			clearTimeout(this.reconnectTimer)
			this.reconnectTimer = null
		}
		this.ws?.close()
		this.ws = null
	}
}

export const wsManager = new WebSocketManager("wss://api.inabsentia.game/api/v1/ws")
```

### 4.4 Type Generation

Types are generated from the shared OpenAPI spec:

```typescript
// scripts/generate-types.ts
// Run: bun run generate-types
// Reads shared/api/openapi.yaml → outputs src/api/types.ts
```

Use a lightweight OpenAPI → TypeScript generator (e.g. `openapi-typescript`). Types stay in sync with the API contract automatically.

---

## 5. State Management

### 5.1 Reactive Store

A minimal reactive state container. No Redux, no MobX. The game's state is mostly server-driven — the client's job is to reflect it, not own it.

```typescript
// src/state/store.ts
type Listener<T> = (state: T) => void

export function createStore<T>(initial: T) {
	let state = initial
	const listeners = new Set<Listener<T>>()

	return {
		get: () => state,
		set: (next: T) => {
			state = next
			for (const fn of listeners) fn(state)
		},
		update: (fn: (prev: T) => T) => {
			state = fn(state)
			for (const listener of listeners) listener(state)
		},
		subscribe: (fn: Listener<T>) => {
			listeners.add(fn)
			return () => listeners.delete(fn)
		}
	}
}
```

### 5.2 State Slices

```typescript
// src/state/contracts.ts
import { createStore } from "./store"
import { wsManager } from "../api/ws"
import type { Contract } from "../api/types"

export const contractsStore = createStore<Map<string, Contract>>(new Map())

// Wire up WebSocket events
wsManager.on("contract.progress", (data) => {
	contractsStore.update(contracts => {
		const c = contracts.get(data.contract_id)
		if (c) {
			c.effort.invested = data.effort_invested
		}
		return new Map(contracts)
	})
})

wsManager.on("contract.completed", (data) => {
	contractsStore.update(contracts => {
		contracts.delete(data.contract_id)
		return new Map(contracts)
	})
	// Trigger inventory refresh
})

wsManager.on("contract.paused", (data) => {
	contractsStore.update(contracts => {
		const c = contracts.get(data.contract_id)
		if (c) {
			c.status = "PAUSED"
			c.pause = data.pause
		}
		return new Map(contracts)
	})
})

wsManager.on("contract.research_material_drop", (data) => {
	// Notify the player of what dropped
	// Trigger inventory refresh
})
```

### 5.3 State Flow

```
Server Event (WebSocket)
  → wsManager dispatches to handlers
    → Store.update() applies the change
      → Subscribed UI components re-render

User Action (click "Create Contract")
  → REST call via api.post()
    → On success: update local store immediately
    → Subscribe to WebSocket events for the new contract
    → UI updates reactively
```

The client is optimistic where appropriate (update the UI before waiting for the next tick) but the server is always authoritative. If the server's next WebSocket event contradicts the optimistic update, the store corrects itself.

---

## 6. UI Design

### 6.1 Layout Philosophy

This is a management/strategy game. The UI should be **information-dense but organized**. Multiple panels visible simultaneously. Persistent status bars. Drill-down on click.

```
┌─────────────────────────────────────────────────────────────┐
│  [Character Name]  │  Stamina ████████░░  │  WU: 1,245  │  │
│  [Zone: Ironhaven] │  STR 12  DEX 10      │  ⚔ Raid: 2h │  │
├──────────────────┬──────────────────────────────────────────┤
│                  │                                          │
│  Navigation      │  Main Content Area                      │
│                  │  (screen-dependent)                      │
│  ○ World Map     │                                          │
│  ○ Crafting      │                                          │
│  ○ Research      │                                          │
│  ○ Inventory     │                                          │
│  ○ Market        │                                          │
│  ○ Governance    │                                          │
│  ○ Account       │                                          │
│                  │                                          │
├──────────────────┴──────────────────────────────────────────┤
│  Active Contracts                                           │
│  ┌──────────────────────┐ ┌──────────────────────────┐      │
│  │ Folded Iron Blade    │ │ Deconstruct Steel Helms  │      │
│  │ ████████░░ 74%       │ │ ██░░░░░░░░ 18%           │      │
│  │ Effort: 799/1080     │ │ Effort: 194/1080         │      │
│  │ Contributors: 2      │ │ Solo                     │      │
│  │ [Pause] [Cancel]     │ │ [Pause] [Cancel]         │      │
│  └──────────────────────┘ └──────────────────────────┘      │
├─────────────────────────────────────────────────────────────┤
│  Event Feed: Contract completed: Cast Iron Blade (FINE) ×3  │
│              Research material: technique_research:hilting   │
│              Stamina recovered to 45/100                     │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Persistent Elements

Always visible regardless of which screen is active:

**Top bar**: Character name, zone, stamina bar, WU balance, raid countdown, base stats summary.

**Bottom bar**: Active contracts with progress bars. Each shows effort bucket fill state, contributor count, pause/cancel buttons. Clicking expands to full detail.

**Event feed**: Scrolling log of WebSocket events. Filterable by category. Research material drops, contract completions, stamina updates, raid warnings.

### 6.3 Screen: Crafting

The core crafting flow:

```
1. Select discipline (Smithing, Alchemy, Carpentry...)
2. Browse known recipes → shows parent Knowledge entries
3. Expand recipe → see discovered variants
4. Select variant → see:
   - Materials required (with availability check against local stockpile)
   - Infrastructure required (with status: functional/damaged/destroyed)
   - Estimated effort (based on character stats)
   - Quality ceiling for this variant
   - Failure chance (based on character expertise)
   - Estimated WU generation
5. Select workstation (if multiple available)
6. Set contribution mode (solo/open/approval/invite)
7. Create Contract → contract appears in bottom bar
```

### 6.4 Screen: Research

Three sub-tabs matching the three research paths:

**Deconstruction**:
- Select item type to deconstruct from inventory.
- Show what research materials this item can produce (discovered sources only, unknown as "???").
- Show item quality and how it affects drop rates.
- Create deconstruction research contract.

**Experimental**:
- Select target Knowledge entry.
- If prerequisites not met: show known clues from past attempts.
- Select research equipment and available parameters.
- Run trial: submit parameters → see results + observations.
- Review research journal: all past trial results, pattern analysis.

**Knowledge Completion**:
- For each Knowledge variant you're working toward:
  - Show required research materials with have/need counts.
  - Highlight which materials you're missing.
  - If all materials collected: "Complete Research" button → creates Knowledge Completion contract.

### 6.5 Screen: Character Creation

Multi-step flow:

```
1. Species selection (only unlocked species shown)
2. Base stat allocation (point-buy within species constraints)
3. Knowledge pre-loading:
   - Browse account Knowledge Library
   - Each entry shows skillpoint cost
   - Running total vs. available skillpoints
   - Minimum spend indicator
   - Variant-level selection (iron_sword:cast vs iron_sword:folded)
4. Starting zone selection
5. Confirm → character created
```

### 6.6 Screen: Research Journal

Dedicated view for experimental research analysis:

```
┌─────────────────────────────────────────────────┐
│  Research Journal: Mithril Blade                 │
│                                                  │
│  Trial #1  [2026-02-25 14:30]                    │
│  Params: mithril_ore + flux, fold_and_quench     │
│  ✗ No materials produced                         │
│  Observation: "The flux ratio seems off"         │
│                                                  │
│  Trial #2  [2026-02-25 15:45]                    │
│  Params: mithril_ore + refined_flux, fold        │
│  ✓ material_research:mithril_crystallography ×1  │
│  Observation: "Material responded well to high   │
│  temperature. Crystalline structures forming."   │
│                                                  │
│  Trial #3  [2026-02-26 09:00]                    │
│  Params: mithril_ore + refined_flux, fold, high  │
│  ✓ technique_research:fold_forging ×1            │
│  ✓ material_research:mithril_crystallography ×1  │
│  Observation: "Excellent results. The folding    │
│  technique at high temperature is promising."    │
│                                                  │
│  Pattern: High temp + refined flux + folding     │
│  produces the best results consistently.         │
└─────────────────────────────────────────────────┘
```

The journal is a key tool for players who want to optimize their experimental research without relying on external wikis. The client can surface patterns (e.g. "high temp trials produce more materials") as a convenience layer.

### 6.7 Client-Side ETA Projection

The server never provides an estimated completion time. The client computes it locally:

```typescript
function projectEta(contract: Contract, character: Character): number | null {
	const remaining = contract.effort.required - contract.effort.invested
	if (remaining <= 0) return 0

	const drainRate = contract.effort.drain_rate
	const currentStamina = character.current_stamina
	const regenRate = character.stamina_regen_rate
	const efficiency = contract.contributors
		.find(c => c.character_id === character.id)
		?.current_efficiency ?? 1.0

	// Simplified: assumes current conditions hold
	const effectiveDrain = Math.min(drainRate, currentStamina > 0 ? drainRate : regenRate)
	const effortPerSec = effectiveDrain * efficiency

	if (effortPerSec <= 0) return null  // can't project

	return remaining / effortPerSec
}
```

Displayed as "~2h 15m remaining" with a note that it's an estimate based on current conditions. Recalculated on every WebSocket update.

---

## 7. Offline / Reconnection Behavior

### 7.1 The World Doesn't Wait

The game progresses whether the client is connected or not. Contracts tick on the server. When the player reconnects:

1. WebSocket reconnects automatically (3-second retry).
2. Client re-subscribes to all channels.
3. Client refreshes active contract state via REST (contracts may have completed, paused, or failed while offline).
4. Store updates, UI re-renders.

### 7.2 State Recovery

On reconnection, the client performs a "state sync":

```typescript
async function syncState(characterId: string) {
	const [character, activeContracts, inventory] = await Promise.all([
		characters.get(characterId),
		characters.contracts(characterId),
		characters.inventory(characterId)
	])

	characterStore.set(character)
	contractsStore.set(new Map(activeContracts.map(c => [c.id, c])))
	inventoryStore.set(inventory)
}
```

This is idempotent and safe to call on every reconnect.

---

## 8. Build & Development

### 8.1 package.json

```json
{
  "name": "in-absentia-frontend",
  "scripts": {
    "dev": "bun run --hot src/dev-server.ts",
    "build": "bun build src/app.ts --outdir dist --minify",
    "native:dev": "bun run src/native/webview.ts",
    "native:prod": "NODE_ENV=production bun run src/native/webview.ts",
    "generate-types": "bun run scripts/generate-types.ts",
    "test": "bun test"
  },
  "dependencies": {
    "webview-bun": "latest"
  },
  "devDependencies": {
    "openapi-typescript": "latest"
  }
}
```

### 8.2 Development Server

```typescript
// src/dev-server.ts
Bun.serve({
  port: 3000,
  fetch(req) {
    const url = new URL(req.url)
    const filepath = url.pathname === "/" ? "/index.html" : url.pathname
    const file = Bun.file(`./public${filepath}`)
    return new Response(file)
  }
})
```

In dev mode, Bun serves the static files. The webview points at localhost:3000. Hot reload on file changes.

### 8.3 API Server Configuration

```typescript
// src/config.ts
export const config = {
  apiBase: import.meta.env.API_BASE ?? "http://localhost:8080/api/v1",
  wsBase: import.meta.env.WS_BASE ?? "ws://localhost:8080/api/v1/ws"
}
```

In development, both backends run locally. In production, these point to the remote server.

---

## 9. Bot-Friendliness Verification

The frontend's design should serve as proof that the API is sufficient for any client. During development, periodically verify:

1. **Every UI action maps to a documented API call.** If the frontend does something that requires a non-public endpoint, that's a bug.
2. **No hidden client-side logic.** The frontend doesn't compute anything the server should be computing. ETA projection is the one exception (explicitly cosmetic).
3. **All information displayed comes from API responses or WebSocket events.** No hardcoded game data in the frontend.
4. **A curl script can replicate any player action.** If it can't, the API is incomplete.

This isn't just a design principle — it's a test case. Maintaining a "curl playthrough" script in the integration test suite validates bot-friendliness on every commit.

---

## 10. Future Considerations

- **Mobile web**: The webview app is a web app at its core. Deploying to mobile via a webview wrapper (or just as a PWA) is straightforward.
- **Theming**: CSS custom properties make it trivial to support dark/light themes or player-customizable color schemes.
- **Accessibility**: The information-dense layout should be navigable by keyboard. Screen reader support for contract status, inventory, and event feed.
- **Localization**: All user-facing strings should go through a translation layer from day one, even if only English is supported initially.
- **Audio**: Ambient audio, event sounds (contract completion, raid warning). Low priority but enhances atmosphere. WebAudio API, triggered by WebSocket events.
