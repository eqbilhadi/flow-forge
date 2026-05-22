# ⚡ FlowForge

**Real-Time Multi-Tenant Workflow Orchestration Engine**

FlowForge lets teams define, execute, monitor, and collaborate on automated workflows in real time — a self-hosted blend of Zapier's workflow engine and GitHub Actions' execution model.

---

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Backend API | Laravel 12, PHP 8.3 |
| Frontend | Vue 3 + TypeScript, Vite |
| UI Components | shadcn/ui (via CSS variables + Tailwind) |
| Database | PostgreSQL 16 |
| Cache / Queue | Redis 7 |
| WebSockets | Soketi (self-hosted Pusher) |
| Auth | JWT (tymon/jwt-auth) |
| Containerization | Docker + Docker Compose |
| CI/CD | GitHub Actions |

---

## 📁 Project Structure

```
flowforge/
├── backend/                  # Laravel 12 API
│   ├── app/
│   │   ├── Actions/          # Single-responsibility actions
│   │   ├── Enums/            # PHP 8.3 enums (StepType, StepStatus, etc.)
│   │   ├── Events/           # Broadcast events (WebSocket)
│   │   ├── Http/
│   │   │   ├── Controllers/  # Thin controllers
│   │   │   ├── Requests/     # Form request validation
│   │   │   └── Resources/    # API resources (JSON serialization)
│   │   ├── Jobs/             # ExecuteWorkflowJob (queued)
│   │   ├── Models/           # Eloquent models
│   │   └── Services/         # Business logic
│   │       ├── DAGParser.php               # Parse + validate + topo-sort
│   │       ├── WorkflowExecutionEngine.php # Step executor with retries
│   │       ├── WorkflowService.php         # Workflow CRUD + versioning
│   │       └── AIWorkflowBuilderService.php # OpenAI integration
│   ├── database/migrations/  # All migrations
│   ├── routes/api.php        # API routes
│   └── tests/                # Pest unit + feature tests
│
├── frontend/                 # Vue 3 + TypeScript SPA
│   ├── src/
│   │   ├── api/              # Axios API client + service modules
│   │   ├── assets/           # Global CSS (shadcn/ui variables)
│   │   ├── components/
│   │   │   ├── layout/       # AppLayout, sidebar
│   │   │   └── workflow/     # StatusDot, StatusBadge, etc.
│   │   ├── composables/      # useEcho (WebSocket), etc.
│   │   ├── lib/utils.ts      # cn(), formatDuration(), etc.
│   │   ├── router/           # Vue Router with auth guards
│   │   ├── stores/           # Pinia stores (auth, workflows)
│   │   ├── types/index.ts    # Full TypeScript types
│   │   └── views/            # Page components
│   └── Dockerfile
│
├── docker-compose.yml        # Full local stack
├── .github/workflows/ci.yml  # CI pipeline
├── REVIEW.md                 # Code review exercise
└── README.md
```

---

## Prasyarat
- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL 16
- Git Bash / Terminal

---

## BACKEND SETUP

### 1. Masuk ke folder backend
```bash
cd backend
```

### 2. Install PHP dependencies
```bash
composer install
```

### 3. Copy dan setup .env
```bash
cp .env.example .env
```

Edit `.env`, sesuaikan database:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flowforge
DB_USERNAME=flowforge
DB_PASSWORD=secret
```

### 4. Buat database di PostgreSQL
```sql
CREATE DATABASE flowforge;
CREATE USER flowforge WITH PASSWORD 'secret';
GRANT ALL PRIVILEGES ON DATABASE flowforge TO flowforge;
```

### 5. Generate keys
```bash
php artisan key:generate
php artisan jwt:secret
```

### 6. Migrasi & seed database
```bash
php artisan migrate --seed
```

### 7. Install Reverb (WebSocket)
```bash
composer require laravel/reverb
php artisan reverb:install
# Tekan Enter untuk semua prompt (gunakan default)
```

---

## FRONTEND SETUP

### 1. Masuk ke folder frontend
```bash
cd frontend
```

### 2. Install dependencies
```bash
npm install
```

### 3. .env sudah ada, tidak perlu diubah
```env
VITE_API_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=flowforge-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

---

## CARA MENJALANKAN (4 Terminal)

Buka 4 terminal terpisah:

**Terminal 1 — Laravel API:**
```bash
cd backend
php artisan serve
# http://localhost:8000
```

**Terminal 2 — Reverb WebSocket:**
```bash
cd backend
php artisan reverb:start --host=127.0.0.1 --port=8765
# ws://localhost:8080
```

**Terminal 3 — Queue Worker:**
```bash
cd backend
php artisan queue:work --queue=workflows,default
```

**Terminal 4 — Vue Frontend:**
```bash
cd frontend
npm run dev
# http://localhost:5173
```

---

## Login Demo

Buka http://localhost:5173

| Email | Password | Role |
|---|---|---|
| admin@demo.com | password | Admin |
| editor@demo.com | password | Editor |
| viewer@demo.com | password | Viewer |

---

## Troubleshooting
