# LocaTrack API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
API menggunakan Laravel Sanctum untuk authentication. Setelah login, gunakan token yang diterima di header:
```
Authorization: Bearer {your_token}
```

## Endpoints

### Authentication

#### Login
```http
POST /login
```
**Body:**
```json
{
    "email": "admin@locatrack.com",
    "password": "password123"
}
```

#### Register
```http
POST /register
```
**Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "employee",
    "employee_id": "EMP003",
    "phone": "081234567890",
    "department": "IT",
    "position": "Developer"
}
```

#### Logout
```http
POST /logout
```

#### Profile
```http
GET /profile
```

### Attendance (Absensi)

#### Get Attendances
```http
GET /attendances
```

#### Check In/Out
```http
POST /attendances
```
**Body:**
```json
{
    "latitude": -6.2088,
    "longitude": 106.8456,
    "type": "check_in"
}
```

#### Today Attendance
```http
GET /attendances/today
```

### Location Tracking

#### Store Location
```http
POST /locations
```
**Body:**
```json
{
    "latitude": -6.2088,
    "longitude": 106.8456,
    "trackable_type": "employee",
    "trackable_id": 1,
    "speed": 0,
    "accuracy": 5.0
}
```

#### Live Tracking
```http
GET /locations/live
```

#### Employee Location History
```http
GET /locations/employee/{employeeId}/history?date=2024-01-01
```

#### Vehicle Location History
```http
GET /locations/vehicle/{vehicleId}/history?start_date=2024-01-01&end_date=2024-01-31
```

#### Share Location
```http
POST /locations/share
```
**Body:**
```json
{
    "latitude": -6.2088,
    "longitude": 106.8456,
    "duration": 60
}
```

### Tasks

#### Get Tasks
```http
GET /tasks
```

#### Create Task (Admin only)
```http
POST /tasks
```
**Body:**
```json
{
    "title": "Survey Lokasi Proyek",
    "description": "Melakukan survey lokasi untuk proyek baru",
    "assigned_to": 1,
    "latitude": -6.1751,
    "longitude": 106.8650,
    "address": "Jl. Sudirman No. 123",
    "priority": "high",
    "due_date": "2024-12-31 17:00:00"
}
```

#### Update Task
```http
PUT /tasks/{id}
```

#### Delete Task (Admin only)
```http
DELETE /tasks/{id}
```

#### My Tasks
```http
GET /my-tasks?status=pending&priority=high
```

#### Start Task
```http
POST /tasks/{id}/start
```

#### Complete Task
```http
POST /tasks/{id}/complete
```
**Body:**
```json
{
    "completion_notes": "Task completed successfully"
}
```

### Vehicles (Admin only)

#### Get Vehicles
```http
GET /vehicles
```

#### Create Vehicle
```http
POST /vehicles
```
**Body:**
```json
{
    "vehicle_number": "B 9999 XYZ",
    "vehicle_type": "Car",
    "brand": "Honda",
    "model": "Civic",
    "year": 2023
}
```

#### Update Vehicle
```http
PUT /vehicles/{id}
```

#### Delete Vehicle
```http
DELETE /vehicles/{id}
```

#### Active Vehicles
```http
GET /vehicles-active
```

#### Update Vehicle Location
```http
POST /vehicles/{id}/location
```
**Body:**
```json
{
    "latitude": -6.2088,
    "longitude": 106.8456
}
```

### Dashboard

#### Admin Dashboard Stats
```http
GET /dashboard/stats
```

#### Employee Dashboard
```http
GET /employee/dashboard
```

## Default Users

### Admin
- Email: admin@locatrack.com
- Password: password123

### Employee 1
- Email: john@locatrack.com
- Password: password123
- Employee ID: EMP001

### Employee 2
- Email: jane@locatrack.com
- Password: password123
- Employee ID: EMP002

## Status Codes

- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

## Error Response Format
```json
{
    "message": "Error message",
    "errors": {
        "field": ["Validation error message"]
    }
}
```