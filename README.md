# Commission Calculator

A full-stack web application for managing contracts and calculating commissions using configurable, version-controlled formulas. Built as a take-home assignment demonstrating a service-layer Laravel API and a reactive Vue 3 SPA.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend framework | Laravel 12 |
| Auth | Laravel Sanctum (token-based) |
| Expression engine | Symfony ExpressionLanguage |
| Frontend framework | Vue 3 (Composition API) |
| State management | Pinia 3 |
| Routing | Vue Router 5 |
| HTTP client | Axios |
| Styling | Tailwind CSS 4 |
| Bundler | Vite 7 |
| Database | MySQL (via XAMPP) |
| Testing | PHPUnit (179 tests, 395 assertions) |

---

## Features

### Contracts
- Full CRUD — create, view, edit, delete contracts
- Server-side search by contract number with debounced input
- Paginated list (configurable per-page)
- Risk score colour coding (green / amber / red)

### Commission Engine
- Calculate commission for any contract using the active formula
- Step-by-step calculation trace showing every intermediate value
- Results saved to history automatically

### Formulas
- Version-controlled formula management
- Sub-variables with chained execution order
- Frontend and backend validation: syntax errors, unknown identifiers, forward references, circular dependencies
- One active formula at a time — activation is atomic

### Formula Simulation
- Dry-run a formula across all contracts before activating
- Shows: contracts affected (commission changes) vs total, current total, projected total, difference and percentage change
- Reads the database — never writes

### Calculation History
- Per-contract paginated history
- Expandable accordion showing input values, sub-variable steps, and final result

### Dashboard
- Live stats: total contracts, active formula version

### Auth
- Register, login, logout (Sanctum token)
- Profile update and password change

---

## Architecture

The backend follows a strict service-layer pattern:

```
Request → FormRequest (validation) → Controller (thin) → Service (logic) → Model
```

- **Controllers** only resolve dependencies and return JSON — no business logic
- **Services** own all business logic and are independently unit-testable
- **Form Requests** handle all input validation, keeping controllers clean
- **Route model binding** used throughout — Laravel resolves models and returns 404 automatically

The frontend uses composables as the data layer:

```
Vue Component → Composable (useContracts / useFormulas) → Axios → API
```

- Each composable manages its own `loading`, `saving`, and `error` state
- Pinia stores only auth state (token + user), persisted to a cookie
- All routes are lazy-loaded

---

## Database Schema

```
users
  id, name, email, password, timestamps

contracts
  id, contract_no (unique), annual_usage, contract_value,
  contract_length, risk_score, timestamps

formulas
  id, version, expression, is_active, timestamps

dependent_variables
  id, formula_id (FK), name, expression, execution_order, timestamps

commission_calculations
  id, contract_id (FK), formula_id (FK), formula_version,
  commission, variables_json, steps_json, timestamps
```

---

## Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL (XAMPP or any local MySQL server)

### Installation

```bash
# 1. Clone the repository
git clone <repo-url>
cd commission-cal/my-app

# 2. Install PHP dependencies
composer install

# 3. Install JS dependencies
npm install

# 4. Configure environment
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=commission_cal
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 5. Run migrations and seed
php artisan migrate --seed

# 6. Start the development servers
npm run dev        # Vite (frontend)
php artisan serve  # Laravel (backend)
```

Open `http://localhost:8000` in your browser.

---

## Running Tests

```bash
# Full test suite
php artisan test

# Unit tests only
php artisan test tests/Unit

# Feature tests only
php artisan test tests/Feature

# Specific test file
php artisan test tests/Unit/FormulaSimulationServiceTest.php
```

**Test coverage:**

| Suite | File | Tests |
|---|---|---|
| Unit | `AuthServiceTest` | 10 |
| Unit | `CommissionEngineServiceTest` | 9 |
| Unit | `ContractServiceTest` | 12 |
| Unit | `FormulaServiceTest` | 15 |
| Unit | `FormulaSimulationServiceTest` | 16 |
| Feature | `ContractTest` | 44 |
| Feature | `FormulaStoreTest` | 35 |
| Feature | `FormulaSimulationTest` | 16 |
| Feature | `CalculationHistoryTest` | 19 |

---

## API Reference

All protected routes require `Authorization: Bearer <token>`.

### Auth

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Login, returns token |
| POST | `/api/logout` | Revoke current token |
| GET | `/api/profile` | Get authenticated user |
| PUT | `/api/profile` | Update name and email |
| PUT | `/api/password` | Update password |

### Dashboard

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/dashboard/stats` | Contract count and active formula version |

### Contracts

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/contracts` | Paginated list (supports `?search=&page=&per_page=`) |
| POST | `/api/contracts` | Create contract |
| GET | `/api/contracts/{contract}` | Get single contract |
| PUT | `/api/contracts/{contract}` | Update contract |
| DELETE | `/api/contracts/{contract}` | Delete contract |
| POST | `/api/contracts/{contract}/calculate` | Run commission calculation |
| GET | `/api/contracts/{contract}/calculations` | Paginated calculation history |

### Formulas

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/formulas` | List all formulas |
| POST | `/api/formulas` | Create formula |
| GET | `/api/formulas/{formula}/simulate` | Dry-run impact analysis |
| POST | `/api/formulas/{formula}/activate` | Set formula as active |

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── CommissionController.php
│   │   ├── ContractController.php
│   │   ├── DashboardController.php
│   │   └── FormulaController.php
│   └── Requests/
│       ├── StoreContractRequest.php
│       ├── UpdateContractRequest.php
│       └── StoreFormulaRequest.php
├── Models/
│   ├── CommissionCalculation.php
│   ├── Contract.php
│   ├── DependentVariable.php
│   ├── Formula.php
│   └── User.php
└── Services/
    ├── AuthService.php
    ├── CommissionEngineService.php
    ├── ContractService.php
    ├── FormulaService.php
    └── FormulaSimulationService.php

resources/js/
├── composables/
│   ├── useCalculations.js
│   ├── useContracts.js
│   └── useFormulas.js
├── pages/
│   ├── auth/         Login.vue, Register.vue
│   ├── contracts/    ContractList.vue, ContractCreate.vue, ContractEdit.vue, ContractCalculations.vue
│   ├── settings/     profile.vue, password.vue
│   ├── Dashboard.vue
│   ├── FormulaBuilder.vue
│   └── FormulaList.vue
├── store/
│   └── auth.js       (Pinia)
└── router/
    └── routes.js

tests/
├── Unit/
│   ├── AuthServiceTest.php
│   ├── CommissionEngineServiceTest.php
│   ├── ContractServiceTest.php
│   ├── FormulaServiceTest.php
│   └── FormulaSimulationServiceTest.php
└── Feature/
    ├── CalculationHistoryTest.php
    ├── ContractTest.php
    ├── FormulaSimulationTest.php
    └── FormulaStoreTest.php
```
