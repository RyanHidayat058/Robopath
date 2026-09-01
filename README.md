# Robopath - Real-Time Autonomous Robot Tracking & Fleet Management

Robopath is a web-based autonomous robot tracking and fleet management system built with Laravel, Tailwind CSS, and HTML5 Canvas/SVG visualization. The system provides real-time monitoring of autonomous delivery units across floor maps, path planning, task dispatching, incident reporting with photo evidence, and historical telemetry logging.

## Features

- Real-Time Live Tracking Map: High-performance SVG and Canvas map overlay rendering robot paths, location pins, and animated unit markers without scroll latency.
- Multi-Unit Dispatch System: Assign simultaneous delivery tasks to multiple robot units (Robot Alpha, Robot Beta, Robot Gamma) with dynamic shortest-path calculation (Dijkstra algorithm).
- Hallway Path Routing: Enforces strict corridor navigation via Ruang Kerja Utama while preventing illegal shortcuts through walls or meeting rooms.
- Automatic Return-to-Base: Autonomous unit return to Blank Room 2 upon mission completion with telemetry synchronization.
- Hardware Incident & Fault Management: Form to report physical obstacles, sensor faults, and battery alerts with evidence photo uploads (validated up to 1MB).
- Operation Logs & Pagination: Complete delivery history with search capabilities and custom blue-white pagination controls.
- Responsive Modern UI: Clean, high-contrast dashboard styled with Tailwind CSS and FontAwesome icons.

## System Architecture & Technologies

- Framework: Laravel 11.x
- Language: PHP 8.3
- Frontend: Blade Templates, Tailwind CSS 3.x, JavaScript (ES6+), FontAwesome 6.x
- Database: MySQL / PostgreSQL / SQLite
- Asset Bundling: Vite

## Prerequisites

- PHP >= 8.3 with required extensions (OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON)
- Composer >= 2.x
- Node.js >= 18.x and NPM

## Environment Configuration (.env.example)

The actual `.env` file containing local environment credentials is excluded from version control via `.gitignore` for security. When cloning or deploying the repository, developers must duplicate `.env.example` to create a new `.env` file before executing key generation and database migrations.

## Installation Guide

1. Clone the repository:
   ```bash
   git clone https://github.com/YOUR_USERNAME/Robopath.git
   cd Robopath
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies:
   ```bash
   npm install
   ```

4. Environment Setup (Copy from .env.example):
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. Configure database credentials inside `.env`, then run database migrations and seeders:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. Create storage link for uploaded evidence images:
   ```bash
   php artisan storage:link
   ```

7. Build frontend assets:
   ```bash
   npm run build
   ```

8. Start the local development server:
   ```bash
   php artisan serve
   ```

   Access the application at `http://127.0.0.1:8000`.

## API Endpoints

- GET `/api/telemetry` - Retrieve active robot coordinates, active deliveries, and system alerts.
- POST `/api/deliveries` - Dispatch a new delivery task to an available robot unit.
- PUT `/api/deliveries/{id}/complete` - Mark an active delivery as completed.
- POST `/api/reports` - Submit a new incident report with optional evidence photo.
- PUT `/api/reports/{id}/resolve` - Mark an active hardware alert as resolved.
- POST `/api/robots/{id}/telemetry` - Update individual robot unit telemetry.
- POST `/api/system/reset` - Reset all active deliveries, reports, and restore units to home base.

## License

This project is open-source software licensed under the MIT license.
