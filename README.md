# Robopath - Real-Time Autonomous Robot Tracking & Fleet Management

Robopath is a web-based autonomous robot tracking, 3D digital-twin visualization, and fleet management platform built with Laravel, Three.js, Tailwind CSS, and HTML5 WebGL. The system provides real-time multi-floor monitoring of autonomous delivery units across floor maps, dynamic shortest-path routing (A* and Dijkstra algorithms), interactive map and room node editing, task dispatching, incident reporting with photo evidence, and telemetry logging.

This branch (`feature/robot-view-mode`) introduces an immersive **3D Robot Camera Follow System**, allowing operators to experience the robot's physical perspective as it navigates through rooms, corridors, and doorways.

---

## Branch Features: Robot Follow View Modes

When following an active or parked robot in the 3D Dashboard, the camera tracking system provides 3 dynamic view modes:

1. **Mata Robot / POV (First-Person Onboard View - Default)**:
   - Positions the camera directly at the robot's onboard sensor and eye level (`eyeHeight = 0.020` to `0.046m` depending on model scale).
   - Looks straight ahead horizontally through corridors, rooms, and door openings (`lookAhead = 2.0m`), creating a realistic driver / onboard camera experience.
   - Automatically hides floating 2D name tags and status badges on the followed robot to prevent visual obstruction.
   - Decouples OrbitControls damping and polar clamps during FPV mode so the camera height strictly follows the robot chassis without being forced upward.

2. **Tampak Belakang (Third-Person Chase Cam)**:
   - Low-angle cinematic camera tracking directly behind the robot (`camDist = 0.28m` - `0.35m`, `camHeight = 0.08m` - `0.10m`).
   - Smoothly lerps position and rotation as the robot turns around corners and maneuvers through narrow hallways.

3. **Orbit Bebas (Isometric Top-Down Follow)**:
   - Locks the camera target to the robot while maintaining the classic overhead isometric vantage point.
   - Allows operators to rotate, pan, and zoom manually around the robot as it navigates the facility.

### Quick Controls & Keyboard Shortcuts

- **Cycle Camera Mode Button**: Located next to the "Ikuti" (Follow) button on both the bottom floating toolbar (`#btn-cycle-follow-mode`) and the fullscreen top bar (`#fullview-btn-cycle-follow`). Displays the active mode badge (`Mata Robot`, `Belakang`, or `Orbit`).
- **Keyboard Shortcut (`V`)**: Press the `V` key on your keyboard at any time while Follow mode is active to instantly cycle through the 3 camera perspectives.
- **Configurable Camera Height**: Camera parameters can be adjusted directly in `resources/views/dashboard_3d.blade.php` inside `animate()` (`eyeHeight`, `eyeForward`, `lookAhead`, `camDist`, `camHeight`).

---

## Core System Features

- **Multi-Floor 3D Digital Twin**: Interactive WebGL rendering (Three.js) supporting Floor 1 and Floor 2 with GLB 3D models, directional shadows, ambient/sun lighting sliders, and responsive room label scaling.
- **Interactive Map & Room Node Editor (`bot_control_3d`)**: Visual 3D editor for creating and modifying room nodes, transit paths, destinations, and multi-floor stair connections with persistent `graph.json` synchronization.
- **Autonomous Mission & Dispatch Engine**: Assign delivery tasks across locations (Office desks, Table Office 1-4, Meeting Rooms, Transit nodes) with multi-stage lifecycle handling (pickup, travel, dropoff, return-to-base).
- **Hallway & Corridor Navigation**: Strict corridor routing rules enforcing realistic facility navigation and preventing clipping through walls.
- **Battery & Incident Management**: Simulated battery consumption, automatic low-battery return-to-base, collision alerts, and incident reporting forms with evidence photo upload (up to 1MB).
- **Responsive Interface**: 100% responsive floating toolbars, contextual inspector cards, and full-screen modes designed for all screen resolutions without overflow cutoffs.
- **Strict Professional Standards**: Clean design system utilizing FontAwesome 6 icons with zero unicode emojis across all interfaces, logs, and templates.

---

## Technology Stack

- **Backend Framework**: Laravel 11.x (PHP 8.3+)
- **3D Graphics Engine**: Three.js (r128) WebGL with OrbitControls and GLTFLoader
- **Frontend & Styling**: Blade Templates, Tailwind CSS 3.x, FontAwesome 6.x
- **Database**: MySQL / PostgreSQL / SQLite
- **Asset Bundler**: Vite

---

## Installation & Setup

1. **Clone the Repository and Switch to Branch**:
   ```bash
   git clone https://github.com/RyanHidayat058/Robopath.git
   cd Robopath
   git checkout feature/robot-view-mode
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**:
   ```bash
   npm install
   ```

4. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database Migration & Seed**:
   Configure database credentials in `.env`, then run:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Storage Symlink**:
   ```bash
   php artisan storage:link
   ```

7. **Compile Assets**:
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
- `POST /api/deliveries` - Dispatch a delivery mission to an available robot unit.
- `PUT /api/deliveries/{id}/complete` - Mark an active delivery mission as completed.
- `POST /api/reports` - Submit an incident report with optional evidence image.
- `PUT /api/reports/{id}/resolve` - Resolve an active hardware or navigation incident.
- `POST /api/robots/{id}/telemetry` - Update individual robot unit telemetry.
- `POST /api/system/reset` - Reset active missions, reports, and return units to base.

---

## License

This project is open-source software licensed under the MIT license.
