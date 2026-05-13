# ⚔️ q2-sim: Enterprise Browser MMORPG Engine

> A production-grade PHP 8+ server authoritative MMO engine built on battle-tested architecture patterns. Strategic depth, cryptographic security, and scalable game economy.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777bb4?style=flat-square&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/Database-MySQLi%2B-337AB7?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Pattern-DDD%2FSOLID-red?style=flat-square)]()
[![Security](https://img.shields.io/badge/Security-Military%20Grade-green?style=flat-square)]()

---

## 🎯 Overview

**q2-sim** implements a browser-based MMORPG ecosystem inspired by the classic Torn structure, but built with modern security practices, scalable architecture, and strategic game depth.

### Core Philosophy

- **Server-Authoritative**: Zero client trust. All state changes validated server-side.
- **Cryptographically Secure**: Argon2ID password hashing, CSRF tokens, session binding to IP+UA
- **Deterministic & Fair**: RNG seeded with server nonce; no luck exploits
- **Scalable Design**: Service layer separation, indexed database schema, N+1 query prevention
- **Production Ready**: Full prepared statements, DI containers, PSR-12 compliance

---

## ⚙️ Technical Stack

| Component | Technology | Rationale |
|-----------|-----------|-----------|
| **Backend** | PHP 8.2+ (strict types) | Type safety, performance |
| **Database** | MySQLi (OOP, prepared only) | Zero SQL injection surface |
| **Security** | Argon2ID + CSRF + Session binding | NIST standards |
| **Architecture** | Service/Repository layers + DI | SOLID principles, testability |
| **Game Math** | Weighted arrays + cumulative probability | Anti-exploit RNG |

---

## 🔐 Security First

### Session Management
- **DB-backed handlers**: session_id, user_id, IP binding, user-agent binding
- **Lifecycle enforcement**: Idle timeout (30 min), absolute expiry (24h)
- **Regeneration**: Triggered on privilege escalation
- **Cookie security**: HttpOnly, Secure, SameSite=Strict

### Input Validation
- Prepared statements **only** — zero SQL concatenation
- Type hints on all method parameters
- Strict whitelist validation for game actions
- Rate limiting on login (10 attempts/5 min per IP)

### Exploit Prevention
- Server-authoritative all state
- Cooldown timestamps enforced server-side
- Transaction wrapping for race condition safety
- Client-provided data never trusted for scoring

---

## 🎮 Core Systems

### ⚔️ Combat Engine
- Turn-based, deterministic (seeded RNG)
- Damage calculation with non-linear scaling
- Crit mechanics using weighted probability arrays
- PvP + NPC encounter support

### 📊 Player Progression
- Logarithmic stat growth (soft caps at levels)
- Skill trees with specialization paths
- Experience curve with diminishing returns
- Character customization and loadout system

### 🏙️ Faction System
- Hierarchical roles with granular permissions
- Shared faction resources (treasury, items)
- Diplomatic relationships (allied/hostile/neutral)
- War declarations with consequence mechanics

### 💰 Game Economy
- Multiple currency types (primary, secondary, crafting)
- Dual-directional sinks and sources (balanced long-term)
- Market system with buy/sell orders
- Crafting with material costs and recipe tiers

### 📬 Inventory & Messaging
- Persistent item storage with serialization
- Mail system with attachments
- Trading with escrow protection
- Item expiry and durability decay

---

## 🧮 Game Math & Algorithms

### Weighted Random Selection
```
Instead of: rand(1, 100) and naive branching

Use: cumulative probability array
  weights: [10, 30, 40, 20]
  cumulative: [10, 40, 80, 100]
  roll := uniform(0, 100)
  result := binary_search(cumulative, roll)
```
Prevents weighted loot exploits through predictable RNG.

### Nonlinear Scaling
- **Logarithmic growth**: `stat = base * log(level + 1)`
- **Soft caps**: Diminishing returns above threshold
- **Breakpoints**: Milestone levels grant bonuses
- **Formulas are public**: Transparency prevents exploit accusations

### Deterministic Seeding
- Server provides action nonce
- Client includes nonce in request
- Server verifies nonce matches session state
- RNG seeded with: `hash(nonce + server_seed + user_id)`
- Result reproducible and auditable

---

## 🏗️ Architecture Patterns

### Service Layer
```
PlayerService → handles business logic
  ├─ PlayerRepository → DB access
  ├─ CombatService → damage/crit calc
  ├─ InventoryService → item management
  └─ EconomyService → market operations
```

All services receive dependencies via constructor injection. No singletons or static methods.

### Repository Pattern
```
interface UserRepository {
  findById(int $id): ?User;
  save(User $user): void;
  findByEmail(string $email): ?User;
}
```

Type-hinted return values. Prepared statements in implementations.

### Strict Types & Contracts
```php
declare(strict_types=1);

public function dealDamage(int $attacker_id, int $target_id, int $damage_amount): void
{
    // Type safety enforced at parse time
    // IDE autocomplete guaranteed
    // Implicit type coercion = parse error
}
```

---

## 🚀 Performance

### Database
- Indexed design (player_id, user_email, faction_id, created_at)
- Query result caching (Redis layer optional)
- Batch operations for mass updates
- Connection pooling (configurable)

### Application
- Service result caching (in-process)
- Lazy-loaded repositories
- Eager loading relationships where needed
- No N+1 query patterns

### Horizontal Scaling
- Stateless session handler (DB-backed, not file)
- Load balancer friendly
- API gateway compatible
- Microservice-ready architecture

---

## 📋 Directory Structure

```
q2-sim/
├── src/
│   ├── Entity/
│   │   ├── User.php
│   │   ├── Character.php
│   │   ├── Faction.php
│   │   └── Item.php
│   ├── Repository/
│   │   ├── UserRepository.php
│   │   ├── CharacterRepository.php
│   │   └── FactionRepository.php
│   ├── Service/
│   │   ├── PlayerService.php
│   │   ├── CombatService.php
│   │   ├── InventoryService.php
│   │   └── EconomyService.php
│   ├── Security/
│   │   ├── SessionHandler.php
│   │   ├── PasswordHasher.php
│   │   └── CSRFProtection.php
│   └── Container/
│       └── DIContainer.php
├── config/
│   ├── database.php
│   ├── game.php
│   └── security.php
├── schema/
│   └── schema.sql
├── public/
│   └── index.php
└── tests/
    └── unit/
```

---

## 🔧 Installation

### Requirements
- PHP 8.2+ with MySQLi extension
- MySQL 8.0+
- Composer (for dependency management)

### Setup
```bash
# Clone repository
git clone https://github.com/kingcairo303-crypto/q2-sim.git
cd q2-sim

# Install dependencies
composer install

# Configure database
cp config/database.php.example config/database.php
# Edit database credentials

# Run migrations
php bin/migrate.php

# Start development server
php -S localhost:8000 -t public/
```

---

## 🎯 Game Design Principles

### Fairness
- All RNG is seeded and auditable
- No hidden mechanics or asymmetric information
- Exploit vectors documented and addressed
- Community-driven balance patches

### Long-Term Retention
- Prestige systems for endgame
- Weekly challenges with rotated mechanics
- Seasonal economy resets
- PvP ranking ladder with soft reset

### Strategic Depth
- Multiple viable builds (no "optimal only" meta)
- Faction warfare with consequence
- Economy arbitrage opportunities
- Gear progression with diminishing returns

### Anti-Exploit
- Rate limiting on all state-changing actions
- Server-side validation of all calculations
- Timestamp-based cooldowns
- Transaction rollback on detected cheats

---

## 🧪 Testing

```bash
# Run unit tests
vendor/bin/phpunit tests/unit/

# Code coverage
vendor/bin/phpunit --coverage-html coverage/

# Security audit
vendor/bin/psalm --analyze src/
vendor/bin/phpstan analyze src/
```

---

## 📚 Documentation

- [Security Architecture](docs/security.md) — Session binding, CSRF, password hashing
- [Combat Mechanics](docs/combat.md) — Damage calculation, crit formula, scaling
- [Economy Design](docs/economy.md) — Sink/source balance, market mechanics
- [Database Schema](schema/schema.sql) — Full DDL with indexes
- [API Reference](docs/api.md) — Endpoint specifications, rate limits

---

## 🤝 Contributing

Contributions follow strict code standards:

1. **Code Style**: PSR-12 enforced via php-cs-fixer
2. **Types**: Strict types on all new code
3. **Tests**: 80%+ coverage required
4. **Security**: All state changes server-validated
5. **Documentation**: Game mechanics documented in docblocks

```bash
# Format code
vendor/bin/php-cs-fixer fix src/

# Check types
vendor/bin/phpstan analyze src/

# Run linter
vendor/bin/psalm src/
```

---

## 📄 License

MIT License — See LICENSE file for details

---

## 🎲 The Answer is 42

> *"In game design, as in quantum mechanics, the observer affects the result. We are building the observer: the server. The client is merely the interface to a reality we control."*

Built with ⚙️ and 🔐 by the q2-sim team.

**Status**: Pre-Alpha | **Last Updated**: May 2026 | **Next Milestone**: Combat Engine v1.0
