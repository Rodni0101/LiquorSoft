# Compatibility Matrix

**Generated:** 2026-10-20
**Purpose:** Document compatibility between skills, tools, agents, and project requirements.

---

## Skill-to-Skill Compatibility

### Core Dependencies (Required Sub-skills)

| Skill | Required Sub-skills | Optional Sub-skills |
|-------|---------------------|---------------------|
| php-backend-development | security-and-hardening, mysql-database, api-and-interface-design | docker-deployment, observability, php-testing |
| php-security | php-backend-development, mysql-database | reviewing-security, api-testing |
| php-testing | php-backend-development, test-driven-development | api-testing, debugging-and-error-recovery |
| mysql-database | php-backend-development, evolving-apis-and-schemas | security-and-hardening, docker-deployment |
| angular-advanced | frontend-ui-engineering, building-accessible-interfaces, designing-frontend-interfaces | performance-optimization, testing-webapps, applying-themes |
| docker-deployment | ci-cd-and-automation, security-and-hardening, shipping-and-launch | observability, mysql-database |
| api-testing | php-testing, api-and-interface-design, security-and-hardening | testing-webapps, performance-optimization |
| observability | docker-deployment, ci-cd-and-automation, shipping-and-launch | performance-optimization, investigating-performance |
| architecture-diagrams | documentation-and-adrs, spec-driven-development | docker-deployment, api-and-interface-design |

### Conflict Matrix (Known Incompatibilities)

| Skill A | Skill B | Issue | Resolution |
|---------|---------|-------|------------|
| (none found) | | | All skills designed to be composable |

---

## Tool Compatibility

### Language/Framework Support

| Skill | PHP | TypeScript | JavaScript | Python | Go | Java |
|-------|-----|------------|------------|--------|-----|------|
| php-backend-development | ✅ Primary | | | | | |
| php-security | ✅ Primary | | | | | |
| php-testing | ✅ Primary | | | | | |
| mysql-database | ✅ Via PDO | ✅ Via ORM | | ✅ Via SQLAlchemy | | ✅ Via JDBC |
| angular-advanced | | ✅ Primary | | | | |
| docker-deployment | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| api-testing | ✅ Pest/Guzzle | ✅ Playwright | ✅ Playwright | ✅ pytest | | |
| observability | ✅ OpenTelemetry | ✅ OpenTelemetry | ✅ OpenTelemetry | ✅ OpenTelemetry | ✅ OpenTelemetry | ✅ OpenTelemetry |
| architecture-diagrams | ✅ Mermaid | ✅ Mermaid | ✅ Mermaid | ✅ Mermaid | ✅ Mermaid | ✅ Mermaid |

### CI/CD Platform Support

| Skill | GitHub Actions | GitLab CI | Jenkins | CircleCI | Azure DevOps |
|-------|----------------|-----------|---------|----------|--------------|
| php-testing | ✅ | ✅ | ✅ | ✅ | ✅ |
| angular-advanced | ✅ | ✅ | ✅ | ✅ | ✅ |
| docker-deployment | ✅ | ✅ | ✅ | ✅ | ✅ |
| api-testing | ✅ | ✅ | ✅ | ✅ | ✅ |
| observability | ✅ | ✅ | ✅ | ✅ | ✅ |

### Cloud Provider Support

| Skill | AWS | GCP | Azure | Self-hosted |
|-------|-----|-----|-------|-------------|
| docker-deployment | ✅ ECS/EKS/Fargate | ✅ Cloud Run/GKE | ✅ Container Apps/AKS | ✅ Docker Compose/K8s |
| observability | ✅ CloudWatch/X-Ray | ✅ Cloud Monitoring/Trace | ✅ Monitor/App Insights | ✅ Prometheus/Grafana |
| mysql-database | ✅ RDS/Aurora | ✅ Cloud SQL | ✅ Database for MySQL | ✅ Percona/MariaDB |

---

## Agent-to-Skill Mapping

| Agent | Primary Skills | Secondary Skills |
|-------|----------------|------------------|
| software-architect | spec-driven-development, architecture-diagrams, api-and-interface-design | evolving-apis-and-schemas, documentation-and-adrs, code-review-and-quality |
| frontend-developer | angular-advanced, frontend-ui-engineering, building-accessible-interfaces | designing-frontend-interfaces, designing-user-experience, testing-webapps |
| backend-developer | php-backend-development, php-security, php-testing | mysql-database, api-testing, observability, docker-deployment |
| database-engineer | mysql-database, observability | php-backend-development, docker-deployment, security-and-hardening |
| devops-engineer | docker-deployment, ci-cd-and-automation, observability | shipping-and-launch, using-git-worktrees, security-and-hardening |
| security-specialist | reviewing-security, security-and-hardening, php-security | mysql-database, docker-deployment, ci-cd-and-automation |
| qa-engineer | test-driven-development, php-testing, testing-webapps, api-testing | debugging-and-error-recovery, systematic-debugging, verifying-before-completion |
| performance-engineer | investigating-performance, performance-optimization, mysql-database | php-backend-development, angular-advanced, observability, api-testing |
| accessibility-ux-specialist | building-accessible-interfaces, designing-user-experience, applying-themes | designing-frontend-interfaces, frontend-ui-engineering, testing-webapps |
| ai-automation-engineer | building-mcp-servers, building-finetuning-datasets, subagent-driven-development | using-agent-skills, dispatching-parallel-agents, context-engineering |
| code-reviewer | code-review-and-quality, receiving-code-review, requesting-code-review | security-and-hardening, php-security, php-testing, api-and-interface-design |
| documentation-engineer | documentation-and-adrs, writing-release-notes, architecture-diagrams | writing-internal-comms, coauthoring-docs, api-and-interface-design |

---

## Project Requirements Coverage

### LiquorSoft Stack: Angular 22 + PHP 8.3 + MariaDB 11

| Requirement | Covered By | Status |
|-------------|------------|--------|
| Angular 22 signals/zoneless | angular-advanced | ✅ |
| Angular Material MDC | angular-advanced, designing-frontend-interfaces | ✅ |
| PHP 8.3 strict types | php-backend-development | ✅ |
| PHP DDD/CQRS | php-backend-development | ✅ |
| PHP security (SQLi, XSS, CSRF) | php-security | ✅ |
| PHP testing (Pest, PHPStan, Infection) | php-testing | ✅ |
| MariaDB schema/migrations | mysql-database | ✅ |
| MariaDB optimization | mysql-database | ✅ |
| Docker multi-stage builds | docker-deployment | ✅ |
| Docker Compose (dev/prod) | docker-deployment | ✅ |
| CI/CD pipelines | ci-cd-and-automation | ✅ |
| Contract testing (Pact) | api-testing | ✅ |
| Load testing (k6) | api-testing | ✅ |
| Observability (logs/metrics/traces) | observability | ✅ |
| Health checks | observability | ✅ |
| Architecture diagrams (C4) | architecture-diagrams | ✅ |
| Accessibility (WCAG 2.1 AA) | building-accessible-interfaces | ✅ |
| PayPal integration | payment-integration (planned) | 🔄 |
| WhatsApp webhook security | webhook-security (planned) | 🔄 |

### Missing Coverage (Planned Skills)

| Requirement | Planned Skill | Priority |
|-------------|---------------|----------|
| PayPal webhook verification | payment-integration | High |
| WhatsApp signature verification | webhook-security | High |
| Idempotency patterns | payment-integration | High |
| Fraud prevention | payment-integration | Medium |
| Webhook replay protection | webhook-security | High |

---

## Version Compatibility

### Skill Versions (All Created: 2026-10-20)

| Skill | Version | Compatible With |
|-------|---------|-----------------|
| php-backend-development | 1.0.0 | PHP 8.1+, Composer 2, PSR-12 |
| php-security | 1.0.0 | PHP 8.1+, Symfony Validator 7, lcobucci/jwt 5 |
| php-testing | 1.0.0 | Pest 2.34+, PHPStan 1.11+, Psalm 5+, Infection 0.27+ |
| mysql-database | 1.0.0 | MySQL 8.0+, MariaDB 10.6+, Doctrine Migrations 3.7+ |
| angular-advanced | 1.0.0 | Angular 17+, TypeScript 5+, RxJS 7+, Material 17+ |
| docker-deployment | 1.0.0 | Docker 23+, BuildKit, Compose 2.20+ |
| api-testing | 1.0.0 | Pact PHP, Spectral, k6 0.47+, Guzzle 7+ |
| observability | 1.0.0 | Monolog 3, Prometheus PHP 3, OpenTelemetry PHP 1.4+ |
| architecture-diagrams | 1.0.0 | Mermaid 10+, PlantUML 1.2024+, C4-PlantUML |

### Project Dependencies

| Dependency | Current | Required | Status |
|------------|---------|----------|--------|
| PHP | (backend) | 8.3+ | ✅ |
| Composer | (backend) | 2.7+ | ⚠️ Need to add |
| MariaDB | (database) | 11+ | ✅ |
| Angular | 22.1.0 | 17+ | ✅ |
| TypeScript | 6.0.2 | 5+ | ✅ |
| RxJS | 7.8.0 | 7+ | ✅ |
| Node.js | 22.23.2 | 18+ | ✅ |
| npm | 11.19.1 | 9+ | ✅ |
| Docker | 29.9.1 | 23+ | ✅ |
| Docker Compose | (included) | 2.20+ | ✅ |

---

## Known Limitations

| Limitation | Affected Skills | Workaround |
|------------|-----------------|------------|
| No official PHP Laravel skill | php-backend-development | Use generic patterns, adapt for Laravel |
| No Symfony-specific skill | php-backend-development | Use generic patterns, adapt for Symfony |
| Mermaid C4 support limited | architecture-diagrams | Use PlantUML for complex C4 |
| OpenTelemetry PHP auto-instrumentation limited | observability | Manual instrumentation required |
| Pact PHP consumer support basic | api-testing | Use provider-side verification primarily |

---

## Upgrade Path

| Skill | Next Version | Breaking Changes | Migration Effort |
|-------|--------------|------------------|------------------|
| All new skills | 1.1.0 | Minor additions only | Low (additive) |
| Pre-existing 56 | opencode updates | Follow opencode changelog | Medium (review changelog) |

---

## Testing Compatibility

Run this verification script to confirm all skills load correctly:

```bash
#!/bin/bash
# verify-skills.sh
cd ~/.opencode/skills
for skill in */; do
    if [[ -f "${skill}SKILL.md" ]]; then
        echo "✅ ${skill%/}"
    else
        echo "❌ ${skill%/} (missing SKILL.md)"
    fi
done
```

Expected output: 67 skills (56 pre-existing + 11 new) all showing ✅