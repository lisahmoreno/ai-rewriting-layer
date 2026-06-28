# AI Rewriting Layer — Duplicate-Content Avoidance

**Status: Prototype** — working and validated on real data (14 hotels / 161 offers), on a feature branch; not merged to main or deployed to production.

Sanitised excerpt of a working prototype in a PHP/Symfony backend. Combined from: the concept page (internal), the code in the feature branch (`src/Service/AiRewriting/`), and a provider A/B sample-output evaluation.

**My commits:** the founding module — the initial service, provider layer, both guards, validator, entity + migration, CLI command, and unit tests. A developer subsequently extended the branch (GraphQL API + admin comparison view, Twig-templated prompts with content-hash versioning, prompt-injection/XSS guards, approve/reject workflow). The code shown here is from my commits; the developer's extensions are not included.

---

## The problem

After a platform migration, two consumer portals started serving identical offer texts — same titles, same descriptions. Google treats this as duplicate content, putting SEO-driven revenue at risk across both portals.

**The fix:** an automated rewriting layer that generates unique text for the secondary portal (KU), with the primary portal (KMW) kept as master.

---

## What's in this repo

A sanitised excerpt of the shipped backend service. The key classes:

| File | What it does |
|------|-------------|
| `ArrangementRewriteService.php` | Orchestration: build prompt → generate → validate → self-correct → guard → return |
| `Provider/LlmProviderInterface.php` | One interface for all LLM providers |
| `Provider/LlmProviderFactory.php` | Swappable provider selection by config (OpenAI / Anthropic / Gemini) |
| `Provider/AnthropicProvider.php` | Anthropic implementation (OpenAI and Gemini mirror it) |
| `Guard/ContentGuardService.php` | Blocklist + OpenAI moderation API |
| `Guard/SemanticGuardService.php` | Fact-check guard: flags amenities in output not present in source |
| `RewriteValidator.php` | Rule-based validation driving the retry loop |
| `Entity/ArrangementPortalContent.php` | Persistence entity with SHA-256 source hash + status tracking |
| `Command/ArrangementRewriteCommand.php` | Feature-flagged batch CLI |

---

## How it works

**1. Context enrichment** — hotel name, city, region, and detected amenities are injected into the prompt.

**2. Generation** — a single `LlmProviderInterface` abstracts OpenAI, Anthropic, and Gemini. Provider is chosen by env config; model changes don't touch the pipeline. The system prompt is tuned for German SEO requirements: title ≤35 chars with location, intro 200–500 chars with keywords in the first 155, KU tone of voice, no prices, no invented features.

**3. Validation + self-correction** — rule-based checks (length, required fields, hotel name not in title). On failure, the errors are fed back to the model as a correction prompt and it retries once — temperature drops from 0.7 → 0.3 on the second attempt.

**4. Two guard layers:**
- **Content guard**: term blocklist + OpenAI moderation API (fails closed on API error)
- **Semantic guard**: flags any amenity (pool, sauna, spa…) appearing in the generated text but absent from the source data; also checks language, prices, and star-rating mentions

**5. Idempotent persistence** — results stored per `(arrangement_id, portal)` with a SHA-256 source hash and prompt version. Unchanged offers are skipped; a prompt version bump triggers a full re-run. Status: `Pending / Generated / Failed / Approved / Rejected`.

**6. Batch CLI** — `app:arrangement:rewrite` with `--batch-size`, `--dry-run`, `--retry-failed`, `--arrangement-id`. Entire feature behind `AI_REWRITING_ENABLED` env flag.

---

## Provider A/B sample output

Validated across 14 hotels / 161 offers. Two examples:

**Hotelferienanlage Friedrichsbrunn (Harz) — offer #70890**
| | Title |
|---|---|
| Original | "All inklusiv kurz und unvergesslich – 4 Tage im Harz" |
| OpenAI | "4 Tage Wellness in Thale" |
| Gemini | "Thale: 4 Tage All-Inclusive Genuss" |

**Mövenpick Hotel Hamburg City — offer #782952**
| | Title |
|---|---|
| Original | "KARNEVAL SPECIAL: Kurztrip nach Hamburg \| 3 Tage" |
| OpenAI | "3 Tage Hamburg: Entdecke die Stadt" |
| Gemini | "Hamburg: 3 Tage Städtereise-Special" |

Both rewrites keep duration and city, drop verbatim phrasing, and lead with the location keyword. The hallucination guard confirmed: amenities named in output (sauna, pool) were ones present in the source data.

---

## What's concept (not yet built)

- AI-as-judge validator (separate model scores SEO/brand/fact-fit, reject < 80)
- Human review queue for top hotels before KU sync
- Semantic cache (embedding similarity instead of exact hash)
- CVR dashboard comparing AI vs. human text performance
- Category-specific prompts (hotel descriptions vs. offer texts)
- KU sync via MasterData API

---

## Tech stack

PHP 8.2 · Symfony 6 · Doctrine ORM · Guzzle · OpenAI / Anthropic / Gemini APIs

---

## Sanitisation note

Exact internal revenue figures, Confluence page IDs, and personal-space URLs have been removed. Brand names (KU / KMW) are kept because they're public consumer brands central to the story — replace with "Portal A / Portal B" for a fully anonymous version. No credentials are included; API keys are injected from env and are empty in `.env.example`.

---

*Built by [Lisa Hübner Moreno](https://lisahmoreno.github.io/portfolio/) — PM who owned concept through to shipping code.*
