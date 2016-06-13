# LocaTrack Backend API

LocaTrack is a comprehensive backend API designed for employee attendance tracking, field task management, and real-time vehicle monitoring. Built with Laravel, it provides a robust solution for managing field operations with geofencing capabilities and role-based access control.

## Key Features

### Authentication & Security
- **Secure Authentication**: Implemented using Laravel Sanctum.
- **Role-Based Access Control (RBAC)**: Distinct roles for Administrators and Employees with specific permission sets.
- **Profile Management**: Secure profile updates and data retrieval.

### Attendance Management
- **Geofence-Validated Attendance**: Check-in and check-out actions are validated against defined office geofences.
- **Attendance History**: Detailed logs of daily attendance, including timestamps and location coordinates.
- **Monthly Reports**: Aggregated attendance data for payroll and performance review.
- **Administrative Override**: Admins can manage and correct attendance records if necessary.

### Task Management
- **Task Assignment**: Admins can assign tasks to specific employees with detailed descriptions, priorities, and due dates.
- **Task Workflow**: Complete lifecycle management (Pending, Accepted, In Progress, Completed).
- **Geo-Tagging**: Tasks can be linked to specific geographic locations.
- **Progress Tracking**: Real-time status updates on assigned tasks.

### Location Services & Tracking
- **Real-Time Tracking**: Live location monitoring for active employees and operational vehicles.
- **Location History**: Historical path tracking for audit and route optimization.
- **Live Location Sharing**: Generate temporary, secure links for sharing real-time location with external parties.
- **Geofencing System**: Configurable geofences (e.g., Office, Client Sites) to automate attendance and alerts.

### Fleet Management
- **Vehicle Tracking**: Monitor the real-time status and location of company vehicles.
- **Usage History**: Track vehicle usage patterns and active times.

### Dashboard & Analytics
- **Admin Dashboard**: High-level overview of total employees, active tasks, vehicle status, and daily attendance stats.
- **Employee Dashboard**: Personal performance metrics, pending tasks, and attendance summary.

## Technology Stack

- **Framework**: Laravel 12
- **Database**: MySQL
- **Authentication**: Laravel Sanctum
- **API Architecture**: RESTful API

## Installation and Setup

### Prerequisites
- PHP >= 8.2
- Composer
- MySQL

### Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/kangzid/LocaTrack-backend.git
   cd LocaTrack-backend
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Environment Configuration**
   Copy the example environment file and configure your database credentials.
   ```bash
   cp .env.example .env
   ```
   Update the `.env` file with your database details:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=locatrack
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Run Migrations and Seeders**
   Initialize the database with the required schema and default data.
   ```bash
   php artisan migrate --seed
   ```

6. **Start the Server**
   ```bash
   php artisan serve
   ```
   The API will be accessible at `http://localhost:8000/api`.

## API Documentation

For detailed endpoint documentation, please refer to the following resources located in the `docs/` directory:
- **API Overview**: `docs/API_DOCUMENTATION.md`
- **Testing Guide**: `docs/API_TEST.md`
- **Postman Collection**: `docs/postman_collection.json`

## Default Credentials

### Administrator
- **Email**: `admin@locatrack.com`
- **Password**: `password123`

### Employee
- **Email**: `john@locatrack.com`
- **Password**: `password123`

## Contributing

Contributions are welcome. Please follow the standard pull request workflow:
1. Fork the repository.
2. Create a feature branch.
3. Commit your changes.
4. Push to the branch.
5. Create a Pull Request.
