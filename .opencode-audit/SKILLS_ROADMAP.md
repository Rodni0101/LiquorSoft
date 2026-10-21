# Skills Roadmap

**Generated:** 2026-10-20
**Purpose:** Track planned, in-progress, and future skills for the LiquorSoft ecosystem.

---

## Current State (v1.0 - 2026-10-20)

| Status | Count |
|--------|-------|
| Production Ready | 67 (56 official + 11 custom) |
| In Development | 2 |
| Planned | 8 |
| Under Review | 0 |
| Archived | 0 |

---

## In Development (Target: Week 1-2)

### payment-integration
**Priority:** HIGH
**Target:** Week 1
**Dependencies:** php-backend-development, php-security, api-testing

**Scope:**
- PayPal REST SDK integration patterns
- Order creation, capture, refund flows
- Webhook handling (PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED, etc.)
- Signature verification (certificate fetching, validation)
- Idempotency keys for duplicate protection
- Sandbox/production environment management
- Error handling and retry logic
- PCI DSS considerations (SAQ A)

**Deliverables:**
- `PaymentService` with typed methods
- Webhook controller with signature verification
- Integration tests with PayPal sandbox
- Contract tests (Pact)
- Documentation with code examples

### webhook-security
**Priority:** HIGH
**Target:** Week 1
**Dependencies:** php-security, api-testing

**Scope:**
- Generic webhook signature verification framework
- PayPal certificate-based verification
- WhatsApp Business API signature (X-Hub-Signature-256)
- Replay attack prevention (timestamp + nonce)
- Queue-based processing with dead letter handling
- Rate limiting per webhook source
- Observability (metrics, logs, traces)

**Deliverables:**
- `WebhookVerifier` interface + implementations
- Middleware for automatic verification
- Queue worker pattern with retries
- Integration tests with real signatures

---

## Planned (Month 1-2)

### legacy-modernization
**Priority:** MEDIUM
**Target:** Week 3
**Dependencies:** php-backend-development, mysql-database, docker-deployment

**Scope:**
- Strangler Fig pattern for legacy PHP migration
- Dual-write / read-from-new patterns
- Feature flags for gradual cutover
- Data migration strategies (CDC, batch, dual-write)
- Testing legacy + new in parallel
- Rollback procedures
- Monitoring during migration

### event-driven-architecture
**Priority:** MEDIUM
**Target:** Week 4
**Dependencies:** php-backend-development, mysql-database, observability

**Scope:**
- Domain events and event sourcing basics
- Event bus implementation (Redis Streams, RabbitMQ)
- Outbox pattern for reliability
- Event schema registry (Avro/Protobuf)
- Consumer groups and partitioning
- Idempotent consumers
- Event replay and debugging tools

### graphql-api
**Priority:** LOW
**Target:** Month 2
**Dependencies:** api-and-interface-design, php-backend-development

**Scope:**
- GraphQL schema design (code-first vs schema-first)
- Resolver patterns (DataLoader for N+1)
- Authentication/authorization in GraphQL
- Query complexity limiting
- Caching (DataLoader, HTTP cache)
- Subscriptions (WebSocket)
- Federation (if microservices)

### mobile-development
**Priority:** LOW
**Target:** Month 2
**Dependencies:** angular-advanced, api-and-interface-design

**Scope:**
- Capacitor/Ionic for Angular → mobile
- Capacitor plugins (camera, push, biometric)
- Offline-first patterns (Service Worker, IndexedDB)
- App store deployment (iOS/Android)
- Native bridge for payments
- Push notifications (FCM/APNs)

### data-engineering
**Priority:** LOW
**Target:** Month 2
**Dependencies:** mysql-database, observability, docker-deployment

**Scope:**
- ETL/ELT pipelines (Airflow, dbt, or custom)
- Data warehouse (ClickHouse, BigQuery, Snowflake)
- CDC (Debezium) for real-time sync
- Analytics events schema
- Dashboard/BI integration (Metabase, Superset)
- GDPR/privacy compliance (PII handling)

### compliance-audit
**Priority:** MEDIUM
**Target:** Month 2
**Dependencies:** security-and-hardening, documentation-engineer

**Scope:**
- GDPR compliance (data mapping, DSR, DPIA)
- PCI DSS SAQ A (PayPal integration)
- SOC 2 Type II evidence collection
- Audit logging requirements
- Data retention policies
- Incident response documentation

### performance-profiling
**Priority:** MEDIUM
**Target:** Month 2
**Dependencies:** investigating-performance, php-backend-development, angular-advanced

**Scope:**
- PHP profiling (Xdebug, Blackfire, Tideways)
- Database profiling (EXPLAIN, slow query log)
- Frontend profiling (Chrome DevTools, Lighthouse)
- Continuous profiling in CI
- Flame graph analysis
- Regression detection

---

## Under Consideration (Future)

| Skill | Description | Trigger |
|-------|-------------|---------|
| microservices-decomposition | Service extraction patterns, contracts, deployment | When monolith becomes bottleneck |
| kubernetes-operations | K8s operators, CRDs, GitOps (ArgoCD/Flux) | When moving to K8s |
| service-mesh | Istio/Linkerd for mTLS, traffic management | Multi-service deployment |
| feature-flags | LaunchDarkly/Unleash integration, gradual rollout | Need for progressive delivery |
| chaos-engineering | Litmus/Chaos Mesh experiments | High availability requirements |
| ml-model-serving | ONNX/TensorFlow Serving, A/B testing | ML feature development |
| edge-computing | Cloudflare Workers, Vercel Edge, Deno Deploy | Global latency optimization |
| zero-trust-networking | SPIFFE/SPIRE, mTLS everywhere | Compliance requirements |

---

## Skill Maintenance Schedule

### Monthly Reviews (First Monday)
- Check official skill updates (opencode releases)
- Review custom skill usage metrics
- Identify gaps from recent projects
- Update roadmap priorities

### Quarterly Deep Dive (Month 1, 4, 7, 10)
- Full skill audit (security, relevance, quality)
- Deprecate unused skills
- Consolidate overlapping skills
- Plan next quarter's development

### Annual Strategy (January)
- Align with technology roadmap
- Evaluate new tools/frameworks
- Budget for training/certification
- Update skill catalog documentation

---

## Skill Creation Process

Following `creating-skills` methodology:

```
1. Identify Need
   └─ Recurring pattern, not documented elsewhere
   └─ Multiple team members ask same question
   └─ Mistake repeats across PRs

2. Research & Design
   ├─ Check existing skills (don't duplicate)
   ├─ Study official patterns (opencode skills)
   ├─ Define trigger description (when to use)
   ├─ Outline workflow/principles
   └─ Identify sub-skills

3. Write SKILL.md
   ├─ YAML frontmatter (name, description, compatibility)
   ├─ Core principles
   ├─ Patterns with code examples
   ├─ Anti-patterns
   ├─ Quick reference table
   └─ When NOT to use

4. Test (RED-GREEN-REFACTOR)
   ├─ Baseline: Agent fails without skill
   ├─ Write skill
   ├─ Agent succeeds with skill
   ├─ Close loopholes (rationalization table)
   └─ Edge cases

5. Document & Deploy
   ├─ Add to SKILLS_CATALOG.md
   ├─ Update COMPATIBILITY_MATRIX.md
   ├─ Add agent mappings
   └─ Team notification
```

---

## Resource Requirements

### Time Investment
| Activity | Weekly Hours | Monthly Hours |
|----------|--------------|---------------|
| Skill maintenance | 2 | 8 |
| New skill development | 8 | 32 |
| Testing/validation | 4 | 16 |
| Documentation | 2 | 8 |
| **Total** | **16** | **64** |

### Team Allocation (Recommended)
| Role | % Time on Skills |
|------|------------------|
| Tech Lead | 20% |
| Senior Engineers | 10% each |
| Platform Team | 50% (if exists) |

---

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Skill coverage of project needs | 95% | Gap analysis quarterly |
| Skill adoption rate | > 80% of tasks | Agent invocation logs |
| Time to productivity (new hire) | < 2 weeks | Onboarding survey |
| Defects prevented by skills | > 50% reduction | Retrospective analysis |
| Skill freshness | < 6 months stale | Last updated timestamp |

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Skill sprawl (too many) | MEDIUM | HIGH | Quarterly consolidation, strict creation criteria |
| Skill staleness | HIGH | MEDIUM | Monthly review, automated update checks |
| Inconsistent quality | LOW | HIGH | creating-skills methodology, peer review |
| Knowledge silos | MEDIUM | HIGH | Cross-training, documentation, rotation |
| Toolchain drift | MEDIUM | MEDIUM | Pinned versions, automated testing |

---

## Next Actions (This Week)

1. [ ] Complete `payment-integration` skill
2. [ ] Complete `webhook-security` skill
3. [ ] Validate both with PayPal sandbox integration test
4. [ ] Add to agent mappings (backend-developer, security-specialist)
5. [ ] Update SKILLS_CATALOG.md and COMPATIBILITY_MATRIX.md
6. [ ] Schedule Month 1 planning session

---

## Archive Policy

Skills moved to archive when:
- Not invoked in 6 months
- Superseded by better approach
- Technology deprecated
- Project no longer uses relevant stack

Archived skills remain in `~/.opencode/skills/archive/` with README explaining why.