# Risk Register

| Risk ID | Category | Description | Impact | Probability | Mitigation Strategy |
|---------|----------|-------------|--------|-------------|---------------------|
| RSK-01 | Data Migration | Mapping the heavily overloaded `transactions` table to the new schema might result in data loss or mismatched accounting figures. | High | Medium | Create comprehensive ETL scripts with parallel-run validation and checksum comparisons before cutting over. |
| RSK-02 | Mobile App Compatibility | The existing offline-first mobile app depends on specific API endpoints. Modifying these in the new system may break mobile clients. | High | High | Document and port legacy API endpoints exactly as they exist (Request/Response schemas) in a specific `Api/V1` namespace. Implement contract testing. |
| RSK-03 | Feature Scope Creep | Rebuilding a system with 300+ migrations and 60+ domains can lead to endless development without reaching feature parity. | Medium | High | Strictly follow the `migration-plan.md` in phases. Focus on Core (IAM, SaaS, POS, Inventory) before tackling peripheral integrations (Twilio, Pusher). |
