# Production Module — Testing & Quality Assurance Guide

> **Domain Location:** `app/Domains/Production`  
> **Documentation Root:** `app/Domains/Production/docs/TESTING.md`  
> **Test Location:** `tests/Feature/Production/` (56 suites) and `tests/Feature/` (28 `Production*.php` suites)  
> **Total Test Suites:** 84 Production Feature Test Suites

---

## 1. Test Architecture & Runner Setup

Production tests are written using PHPUnit 11 with Laravel's testing suite. Tests run against an isolated SQLite in-memory database (`:memory:`), guaranteeing that unit and feature tests never pollute development or staging databases.

### Environment Configuration (`phpunit.xml`)
```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="DB_FOREIGN_KEYS" value="false"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="CACHE_STORE" value="array"/>
```

---

## 2. Test Catalog & Critical Test Suites

The 84 Production test suites cover all critical manufacturing pathways:

### Core Execution Suites (`tests/Feature/Production/`)
1. **`ShopfloorQcScrapReworkArchitectureTest.php`:** Verifies the complete in-process QC loop, "Run QC" parameter validation, NCR generation, rework order routing, and operational scrap write-offs.
2. **`ScheduleDispatchAndPreReleaseTest.php`:** Verifies forward/backward scheduling, dispatch board drag/drop adjustments, operation locks, ripple shifts, and pre-release validation gates.
3. **`SubcontractDeliveryChallanTest.php`:** Verifies outsourced WIP delivery challans, stock checks, dispatch status, gate pass printing, and GRN return receipt.
4. **`AuditFixesTest.php`:** Tests stabilization regression fixes, tenant isolation, and status transition guards.
5. **`EnterpriseSchedulingTest.php`:** Verifies multi-work center finite capacity leveling and shift availability.
6. **`ProductionOrderAndWipUiTest.php`:** Verifies UI variable bindings, reservations display, and WIP cards.
7. **`ProductionBatchRoutingContinuityTest.php`:** Verifies lot traceability and continuous batch progression.

### Root Production Feature Suites (`tests/Feature/`)
- `ProductionBomTest.php`: BOM creation, approval, dynamic formulas, revisions, and explosion.
- `ProductionOrderTest.php`: Order generation, immutable snapshotting, and material reservation.
- `ProductionWipTest.php`: WIP tracking, SFG consumption, and finished goods conversion.
- `ProductionParameterizedBomTest.php`: Dynamic mathematical formula evaluation.
- `ProductionReleaseValidationTest.php`: Pre-release material issue requirements.

---

## 3. Actual Test Verification Report (Verified September 12, 2026)

During the audit, representative critical test suites were executed directly on the host machine. All 4 executed suites passed with **100% pass rate**:

| Suite Name | Execution Command | Result | Tests | Assertions | Duration |
|---|---|---|---|---|---|
| **AuditFixesTest** | `php vendor/phpunit/phpunit/phpunit tests/Feature/Production/AuditFixesTest.php --no-coverage` | **PASSED** | 9 | 25 | 20.3s |
| **ShopfloorQcScrapReworkArchitectureTest** | `php vendor/phpunit/phpunit/phpunit tests/Feature/Production/ShopfloorQcScrapReworkArchitectureTest.php --no-coverage` | **PASSED** | 8 | 43 | 15.3s |
| **ScheduleDispatchAndPreReleaseTest** | `php vendor/phpunit/phpunit/phpunit tests/Feature/Production/ScheduleDispatchAndPreReleaseTest.php --no-coverage` | **PASSED** | 16 | 65 | 12.0s |
| **SubcontractDeliveryChallanTest** | `php vendor/phpunit/phpunit/phpunit tests/Feature/Production/SubcontractDeliveryChallanTest.php --no-coverage` | **PASSED** | 8 | 41 | 11.3s |
| **Combined Sample Total** | | **100% PASS** | **41** | **174** | **58.9s** |

---

## 4. How to Run Production Tests

### Running a Specific Test File
```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/Production/ShopfloorQcScrapReworkArchitectureTest.php --no-coverage
```

### Running a Single Test Method
```bash
php vendor/phpunit/phpunit/phpunit --filter=it_blocks_schedule_release_when_materials_are_not_issued tests/Feature/Production/ScheduleDispatchAndPreReleaseTest.php --no-coverage
```

### Running All Production Feature Tests
```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/Production/ --no-coverage
```
