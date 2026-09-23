# Robopath - Real-Time Autonomous Robot Tracking & Fleet Management

Robopath is a web-based autonomous robot tracking, 3D digital-twin visualization, and fleet management platform built with Laravel, Three.js, Tailwind CSS, and HTML5 WebGL. The system provides real-time multi-floor monitoring of autonomous delivery units across floor maps, dynamic shortest-path routing (A* and Dijkstra algorithms), interactive map and room node editing, task dispatching, incident reporting with photo evidence, and telemetry logging.

This is the primary stable branch (`main`).

---

## Key Features

- **Multi-Floor 3D Digital Twin**: Interactive WebGL rendering powered by Three.js supporting Floor 1 and Floor 2 with detailed GLB architectural models, realistic directional shadows, ambient/sun lighting sliders, and responsive room label scaling.
- **Interactive 3D Room & Map Editor (`bot_control_3d`)**: Comprehensive visual editor allowing administrators to create and modify room nodes, transit routes, destination points, and multi-floor stair connections with persistent `graph.json` synchronization and dedicated save actions.
- **Autonomous Fleet Dispatch & Tracking**: Real-time position tracking and mission lifecycle management for multiple robot units (Robot Alpha, Robot Beta, Robot Gamma) across office desks, Table Office 1-4, meeting rooms, and transit waypoints.
- **Hallway & Corridor Routing Engine**: Enforces strict corridor navigation paths through primary walkways, preventing illegal wall-clipping and unrealistic shortcuts.
- **Mission Lifecycle & Multi-Stage Execution**: Handles autonomous pickup, delivery transit, dropoff handover, and automated return-to-base upon task completion.
- **Battery & Incident Management**: Real-time battery drain simulation, automatic low-battery return-to-base, collision simulation, and hardware incident reporting with validated photo uploads (up to 1MB).
- **Role-Based Access Control**: Secure workflow management with distinct permissions for Administrator (full map editing, 3D lighting, dispatching) and Employee/Karyawan (monitoring and incident reporting) roles.
- **Responsive Modern Interface**: 100% responsive floating toolbars, contextual inspector cards, and full-screen modes designed to prevent overflow cutoffs on standard laptop displays.
- **Strict Professional Standards**: Clean design system utilizing FontAwesome 6 icons with zero unicode emojis across all interfaces, logs, and templates.

---

## Development Branches

- `main`: Stable production-ready branch containing core 3D tracking, map editing, and fleet management.
- `feature/robot-view-mode`: Feature branch implementing the dynamic 3D Robot Follow Camera System (First-Person Mata Robot POV, Third-Person Chase Cam, and Orbit Follow with keyboard shortcut `V`).

---

## Technology Stack

- **Backend Framework**: Laravel 11.x (PHP 8.3+)
- **3D Graphics Engine**: Three.js (r128) WebGL with OrbitControls and GLTFLoader
- **Frontend & Styling**: Blade Templates, Tailwind CSS 3.x, FontAwesome 6.x
- **Database**: MySQL / PostgreSQL / SQLite
- **Asset Bundler**: Vite

---

## Prerequisites

- PHP >= 8.3 with required extensions (OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON)
- Composer >= 2.x
- Node.js >= 18.x and NPM

---

## Environment Configuration (.env.example)

The `.env` file containing local environment credentials is excluded from version control via `.gitignore` for security. When cloning or deploying the repository, developers must duplicate `.env.example` to create a new `.env` file before executing key generation and database migrations.

---

## Installation Guide

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/RyanHidayat058/Robopath.git
   cd Robopath
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install Node.js Dependencies**:
   ```bash
   npm install
   ```

4. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure Database & Run Migrations**:
   Configure database credentials inside `.env`, then execute migrations and seeders:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Create Storage Link**:
   ```bash
   php artisan storage:link
   ```

7. **Compile Frontend Assets**:
   ```bash
   npm run build
   ```

8. **Start the Development Server**:
   ```bash
   php artisan serve
   ```
   Access the dashboard at `http://127.0.0.1:8000`.

---

## Primary API Endpoints

- `GET /api/telemetry` - Retrieve active robot coordinates, battery levels, active deliveries, and alerts.
- `POST /api/deliveries` - Dispatch a new delivery task to an available robot unit.
- `PUT /api/deliveries/{id}/complete` - Mark an active delivery as completed.
- `POST /api/reports` - Submit a new incident report with optional evidence photo.
- `PUT /api/reports/{id}/resolve` - Mark an active hardware alert as resolved.
- `POST /api/robots/{id}/telemetry` - Update individual robot unit telemetry.
- `POST /api/system/reset` - Reset all active deliveries, reports, and restore units to home base.

---

## License

This project is open-source software licensed under the MIT license.
