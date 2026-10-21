# Development Workflow

**Generated:** 2026-10-20
**Purpose:** Standardized workflow for all LiquorSoft development using the skills ecosystem.

---

## Overview

This workflow integrates the 67 skills and 10 virtual agents into a cohesive development process. Every task follows this lifecycle:

```
Task → Skill Selection → Agent Assignment → Implementation → Verification → Documentation → Done
```

---

## Phase 1: Task Initiation

### 1.1 Receive Task
- User request, issue, or PR description
- Identify: feature, bug, refactor, spike, docs

### 1.2 Skill Discovery (MANDATORY)
> **Rule:** Before ANY response or action, check for applicable skills.

```bash
# Mental checklist - invoke relevant skills
# Using using-superpowers + using-agent-skills:
# - Vague idea? → idea-refine
# - New feature/change? → spec-driven-development
# - Have spec, need tasks? → planning-and-task-breakdown
# - Implementing? → incremental-implementation
#   - UI? → frontend-ui-engineering / angular-advanced
#   - API? → api-and-interface-design / php-backend-development
#   - Database? → mysql-database
#   - Security? → security-and-hardening / php-security
#   - Tests? → test-driven-development / php-testing / testing-webapps
# - Bug? → systematic-debugging / debugging-and-error-recovery
# - Review? → code-review-and-quality / reviewing-security
# - Deploy? → shipping-and-launch / docker-deployment
```

### 1.3 Surface Assumptions
Before implementing, explicitly state:

```
ASSUMPTIONS I'M MAKING:
1. [requirement assumption]
2. [architecture assumption]
3. [scope assumption]
→ Correct me now or I'll proceed with these.
```

### 1.4 Agent Assignment
Match task to virtual agent:

| Task Type | Primary Agent | Supporting Agents |
|-----------|---------------|-------------------|
| Architecture decision | software-architect | documentation-engineer |
| Frontend feature | frontend-developer | accessibility-ux-specialist, qa-engineer |
| Backend feature | backend-developer | database-engineer, security-specialist, qa-engineer |
| Database change | database-engineer | backend-developer, devops-engineer |
| Security audit | security-specialist | code-reviewer, backend-developer |
| Performance issue | performance-engineer | backend-developer, database-engineer |
| Accessibility | accessibility-ux-specialist | frontend-developer, qa-engineer |
| API design | backend-developer | software-architect, qa-engineer |
| Documentation | documentation-engineer | relevant domain agent |
| AI/Automation | ai-automation-engineer | relevant domain agent |

---

## Phase 2: Specification & Planning

### 2.1 Spec-Driven Development
For non-trivial tasks, create spec first:

```markdown
# SPEC: [Feature Name]

## Context
- Problem statement
- User story
- Acceptance criteria (testable)

## Design
- API contracts (OpenAPI)
- Data model changes
- UI mockups (if applicable)
- Sequence diagrams

## Implementation Plan
- Task breakdown (from planning-and-task-breakdown)
- Dependencies
- Rollback plan

## Verification
- Unit tests
- Integration tests
- E2E tests
- Performance benchmarks
- Security checks
```

### 2.2 Task Breakdown
Use `planning-and-task-breakdown` to create:

```markdown
## Implementation Tasks

- [ ] T1: [Specific, verifiable task]
- [ ] T2: [Specific, verifiable task]
- [ ] T3: [Specific, verifiable task]

Each task: < 2 hours, independently testable
```

---

## Phase 3: Implementation

### 3.1 Incremental Implementation
Follow `incremental-implementation`:

1. **Smallest vertical slice** — End-to-end working piece
2. **Test first** — TDD for each slice
3. **Verify** — Run tests, lint, static analysis
4. **Commit** — Atomic, conventional commits
5. **Repeat** — Next slice

### 3.2 Domain-Specific Implementation

#### Frontend (Angular 22+)
```bash
# Skills: angular-advanced, frontend-ui-engineering, building-accessible-interfaces

# 1. Create standalone component with signals
ng g c features/xyz/component --standalone

# 2. Add to feature routes (lazy loaded)
# 3. Implement with OnPush, signals, inject()
# 4. Write unit test (Vitest + Testing Library)
# 5. Write E2E test (Playwright)
# 6. Verify accessibility (axe-core)
# 7. Verify performance (bundle size, CWV)
```

#### Backend (PHP 8.3+)
```bash
# Skills: php-backend-development, php-security, php-testing

# 1. Create domain model (Entity, VO, Repository interface)
# 2. Create application layer (Command, Handler, Event)
# 3. Implement infrastructure (Repository, Controller)
# 4. Add validation (Symfony Validator attributes)
# 5. Write unit tests (Pest)
# 6. Write integration tests (Pest + real DB)
# 7. Run static analysis (PHPStan Level 8, Psalm)
# 8. Run mutation testing (Infection)
```

#### Database
```bash
# Skills: mysql-database, php-backend-development

# 1. Design schema (ERD, naming conventions)
# 2. Create migration (Doctrine Migrations)
# 3. Add indexes for query patterns
# 3. Test migration up/down
# 4. Update repositories
```

#### API
```bash
# Skills: api-and-interface-design, api-testing, php-security

# 1. Define OpenAPI spec
# 2. Implement contract tests (Pact)
# 3. Implement endpoint
# 4. Add authentication/authorization
# 5. Add rate limiting
# 6. Add integration tests
```

---

## Phase 4: Verification (MANDATORY)

### 4.1 Pre-Commit Checks
Run before every commit:

```bash
# Frontend
cd LiquorSoft-angular
npm run lint          # ESLint + Prettier
npm run test          # Vitest (unit)
npm run build         # Production build check

# Backend
cd ../backend
composer cs-check     # PHP-CS-Fixer
composer analyse      # PHPStan + Psalm
composer test         # Pest (unit + integration)
composer infection    # Mutation testing (MSI > 80%)
```

### 4.2 CI Pipeline Gates
All must pass in CI:

| Gate | Tool | Threshold |
|------|------|-----------|
| Code Style | PHP-CS-Fixer / Prettier | 0 errors |
| Static Analysis | PHPStan Level 8 / Psalm | 0 errors |
| Unit Tests | Pest / Vitest | 100% pass |
| Integration Tests | Pest / Playwright | 100% pass |
| Contract Tests | Pact | 100% pass |
| Mutation Testing | Infection | MSI ≥ 80% |
| Coverage | Pest / Vitest | ≥ 80% |
| Security Scan | Composer audit / npm audit | 0 critical/high |
| Docker Scan | Trivy | 0 critical/high |
| Accessibility | axe-core | 0 violations |

### 4.3 Manual Verification
Before marking done:

- [ ] Feature works end-to-end
- [ ] Edge cases handled
- [ ] Error states work
- [ ] Performance acceptable
- [ ] Accessibility verified
- [ ] Security review passed
- [ ] Documentation updated

---

## Phase 5: Code Review

### 5.1 Self-Review First
Author reviews own PR:
- [ ] Tests pass locally
- [ ] No debug code
- [ ] Clear commit messages
- [ ] No unrelated changes

### 5.2 Peer Review
Assign to `code-reviewer` agent or human:

**Review Dimensions:**
1. **Correctness** — Logic, edge cases, error handling
2. **Maintainability** — SRP, naming, coupling, DRY
3. **Security** — Validation, encoding, auth, secrets
4. **Architecture** — Boundaries, patterns, contracts
5. **Performance** — N+1, caching, complexity

**Feedback Priority:**
- 🔴 Blocking — Must fix (correctness, security, architecture)
- 🟠 Important — Should fix (maintainability, performance)
- 🟡 Suggestion — Could improve (style, modern patterns)
- ❓ Question — Clarify intent

### 5.3 Security Review
For security-sensitive changes, assign `security-specialist`:
- Auth changes
- Payment processing
- Data access modifications
- New endpoints

---

## Phase 6: Integration & Deployment

### 6.1 Merge Strategy
Follow `git-workflow-and-versioning` + `finishing-a-development-branch`:

```bash
# Option 1: Squash and merge (preferred for features)
git checkout main
git pull
git merge --squash feature/xyz
git commit -m "feat: add xyz feature"

# Option 2: Rebase and merge (for clean history)
git checkout feature/xyz
git rebase main
git checkout main
git merge feature/xyz
```

### 6.2 Deployment
Follow `shipping-and-launch` + `docker-deployment`:

```bash
# 1. Build images (multi-stage, signed)
docker buildx build --platform linux/amd64,linux/arm64 \
  --push -t ghcr.io/org/liquorsoft-api:v1.2.3 .

# 2. Deploy to staging
docker compose -f docker-compose.staging.yml up -d

# 3. Run smoke tests
k6 run smoke-tests.js

# 4. Deploy to production (blue-green)
# 5. Monitor (observability)
# 6. Rollback plan ready
```

---

## Phase 7: Documentation & Retrospective

### 7.1 Documentation Updates
Follow `documentation-and-adrs` + `writing-release-notes`:

- [ ] Update API docs (OpenAPI)
- [ ] Update architecture diagrams (if changed)
- [ ] Add/update ADR (for architectural decisions)
- [ ] Update runbooks (if deployment changed)
- [ ] Update CHANGELOG (automated from commits)

### 7.2 Retrospective
For significant features:

```
RETROSPECTIVE: [Feature Name]

What went well:
- 

What didn't:
- 

Action items:
- [ ] 
```

---

## Quick Reference: Skill Invocation by Task

| Task | Invoke These Skills (in order) |
|------|--------------------------------|
| New feature | 1. spec-driven-development 2. planning-and-task-breakdown 3. incremental-implementation 4. test-driven-development 5. code-review-and-quality |
| Bug fix | 1. systematic-debugging 2. test-driven-development 3. code-review-and-quality |
| Refactor | 1. code-simplification 2. test-driven-development 3. code-review-and-quality |
| Security audit | 1. reviewing-security 2. security-and-hardening 3. code-review-and-quality |
| Performance issue | 1. investigating-performance 2. performance-optimization 3. verifying-before-completion |
| New API endpoint | 1. api-and-interface-design 2. php-backend-development 3. php-security 4. api-testing 5. test-driven-development |
| Frontend component | 1. designing-frontend-interfaces 2. angular-advanced 3. building-accessible-interfaces 4. testing-webapps |
| Database change | 1. mysql-database 2. evolving-apis-and-schemas 3. php-testing |
| Deploy to production | 1. shipping-and-launch 2. docker-deployment 3. observability 4. ci-cd-and-automation |

---

## Emergency Procedures

### Hotfix Process
```bash
# 1. Create hotfix branch from main
git checkout main && git pull
git checkout -b hotfix/critical-fix

# 2. Minimal fix + test
# 3. Fast-track review (security-specialist + code-reviewer)
# 4. Deploy with shipping-and-launch
# 5. Post-deploy verification
```

### Rollback Process
```bash
# 1. Detect issue (observability alerts)
# 2. Decide rollback (shipping-and-launch)
# 3. Execute rollback (docker-deployment)
# 4. Verify health (observability)
# 5. Investigate root cause (systematic-debugging)
```

---

## Maintenance Commands

### Daily
```bash
# Update dependencies
cd backend && composer update --dry-run
cd ../LiquorSoft-angular && npm outdated

# Check security advisories
composer audit
npm audit

# Run full test suite
composer test
npm test
```

### Weekly
```bash
# Mutation testing (slow, run weekly)
composer infection

# Performance baseline
k6 run load-tests/api-load-test.js

# Architecture diagram validation
for f in docs/architecture/**/*.mermaid; do mmdc -i "$f" -o /tmp/out.svg; done

# Skill updates check
# (Review opencode skill updates manually)
```

### Monthly
```bash
# Dependency updates (test thoroughly)
composer update
npm update

# Security scan
trivy image ghcr.io/org/liquorsoft-api:latest
trivy image ghcr.io/org/liquorsoft-frontend:latest

# Capacity review
# Check observability dashboards for trends
```

---

## Escalation Contacts

| Issue Type | Primary | Secondary |
|------------|---------|-----------|
| Architecture | software-architect | CTO |
| Security | security-specialist | CISO |
| Production Down | devops-engineer | SRE On-call |
| Performance | performance-engineer | backend-developer |
| Accessibility | accessibility-ux-specialist | Legal/Compliance |
| Data Loss | database-engineer | DevOps |
| AI/ML Issues | ai-automation-engineer | CTO |

---

## Continuous Improvement

### Skill Evolution
- Review skill effectiveness monthly
- Update skills based on project learnings
- Create new skills for recurring patterns
- Archive unused skills

### Workflow Tuning
- Measure: Cycle time, defect rate, deployment frequency
- Adjust: Task size, review requirements, gate thresholds
- Automate: Manual steps that repeat

---

## Quick Commands Reference

```bash
# Start feature
git checkout main && git pull && git checkout -b feature/xyz

# Run all checks locally
./scripts/check-all.sh  # (create this script)

# Create spec
./scripts/new-spec.sh feature-name

# Run mutation testing
cd backend && composer infection

# Validate diagrams
for f in docs/architecture/**/*.mermaid; do mmdc -i "$f" -o /tmp/out.svg; done

# Deploy staging
docker compose -f docker-compose.staging.yml up -d --build

# View logs
docker compose logs -f api

# Health check
curl https://staging.liquorsoft.com/health/ready
```

---

## Definition of Done

A task is **DONE** when:

- [ ] Specification exists and approved
- [ ] Implementation complete (all subtasks)
- [ ] All tests pass (unit, integration, contract, E2E)
- [ ] Static analysis clean (PHPStan, Psalm, ESLint)
- [ ] Mutation testing MSI ≥ 80%
- [ ] Code review approved (no blocking comments)
- [ ] Security review passed (if applicable)
- [ ] Accessibility verified (if UI)
- [ ] Performance within budget
- [ ] Documentation updated
- [ ] Deployed to staging and verified
- [ ] Merged to main with conventional commit
- [ ] Deployed to production (or scheduled)
- [ ] Monitoring verified post-deploy