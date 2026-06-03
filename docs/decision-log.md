# Decision Log

## Architecture Decisions

| Date | Topic | Decision | Rationale |
|------|-------|----------|-----------|
| 2026-06-02 | Framework | Upgrade Backend to Laravel 11 + PHP 8.3 | The legacy codebase is Laravel 9 (PHP 8.0). Laravel 11 is the latest stable, offering better performance, security, and developer experience. |
| 2026-06-02 | Architecture Pattern | API-First with Headless Frontend | The system must support both web and a complex offline-first mobile app. A unified API approach prevents logic duplication. |
| 2026-06-02 | Data Migration | Keep Legacy DB Read-Only; Write ETL script | We cannot risk the integrity of the live legacy system. An ETL script will be used to migrate data to a newly structured schema. |
