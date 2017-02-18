# API Testing Guide - LocaTrack

Base URL: `http://localhost:8000/api`

## 1. Authentication

### Login Admin

```
URL: http://localhost:8000/api/login
Method: POST
Content-Type: application/json

{
    "email": "admin@locatrack.com",
    "password": "password123"
}
```

### Login Employee

```
URL: http://localhost:8000/api/login
Method: POST
Content-Type: application/json

{
    "email": "john@locatrack.com",
    "password": "password123"
}
```

### Logout

```
URL: http://localhost:8000/api/logout
Method: POST
Authorization: Bearer {token}
```

## 2. Admin CRUD Endpoints

### Dashboard

```
URL: http://localhost:8000/api/dashboard/stats
Method: GET
Authorization: Bearer {admin_token}
```

### Users Management

#### Get All Users

```
URL: http://localhost:8000/api/users
Method: GET
Authorization: Bearer {admin_token}
```

#### Get All Admins

```
URL: http://localhost:8000/api/users/admins
Method: GET
Authorization: Bearer {admin_token}
```

#### Get User by ID

```
URL: http://localhost:8000/api/users/{id}
Method: GET
Authorization: Bearer {admin_token}
```

### Employees CRUD

#### Get All Employees

```
URL: http://localhost:8000/api/employees
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Employee by ID

```
URL: http://localhost:8000/api/employees/{id}
Method: GET
Authorization: Bearer {admin_token}
```

#### Create Employee

```
URL: http://localhost:8000/api/employees
Method: POST
Authorization: Bearer {admin_token}
Content-Type: application/json

// Create Employee
{
    "name": "New Employee Test",
    "email": "newemployee@test.com",
    "password": "password123",
    "role": "employee",
    "employee_id": "EMP003",
    "phone": "081234567891",
    "address": "Jl. Sudirman No. 45, Jakarta",
    "department": "HR",
    "position": "HR Staff"
}

// Create Admin
{
    "name": "New Admin",
    "email": "newadmin@test.com",
    "password": "password123",
    "role": "admin"
}
```

#### Update Employee

```
URL: http://localhost:8000/api/employees/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "Update Name",
    "email": "newemployee@test.com",
    "password": "password123",
    "role": "employee",
    "employee_id": "EMP003",
    "phone": "081234567895",
    "address": "Jl. Sudirman No. 45, Jakarta",
    "department": "HR",
    "position": "HR Staff"
}
```

#### Delete Employee

```
URL: http://localhost:8000/api/employees/{id}
Method: DELETE
Authorization: Bearer {admin_token}
```

### Vehicles CRUD

#### Get All Vehicles

```
URL: http://localhost:8000/api/vehicles
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Vehicle by ID

```
URL: http://localhost:8000/api/vehicles/{id}
Method: GET
Authorization: Bearer {admin_token}
```

#### Create Vehicle

```
URL: http://localhost:8000/api/vehicles
Method: POST
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "vehicle_number": "B1234XYZ",
    "vehicle_type": "Car",
    "brand": "Toyota",
    "model": "Avanza",
    "year": 2023
}
```

#### Update Vehicle

```
URL: http://localhost:8000/api/vehicles/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

// Update vehicle data
{
    "vehicle_number": "B5678ABC",
    "vehicle_type": "Motorcycle",
    "brand": "Honda",
    "model": "Vario",
    "year": 2024
}

// Deactivate vehicle
{
    "vehicle_number": "B1234XYZ",
    "vehicle_type": "Car",
    "brand": "Toyota",
    "model": "innova",
    "year": 2021,
    "is_active": false
}
```

#### Delete Vehicle

```
URL: http://localhost:8000/api/vehicles/{id}
Method: DELETE
Authorization: Bearer {admin_token}
```

#### Get Active Vehicles

```
URL: http://localhost:8000/api/vehicles-active
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Inactive Vehicles

```
URL: http://localhost:8000/api/vehicles-inactive
Method: GET
Authorization: Bearer {admin_token}
```

### Tasks CRUD

#### Get All Tasks

```
URL: http://localhost:8000/api/tasks
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Task by ID

```
URL: http://localhost:8000/api/tasks/{id}
Method: GET
Authorization: Bearer {admin_token}
```

#### Create Task

```
URL: http://localhost:8000/api/tasks
Method: POST
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "title": "Survey Lokasi",
    "description": "Survey lokasi untuk proyek baru",
    "assigned_to": 1,
    "latitude": -6.200000,
    "longitude": 106.816666,
    "priority": "high",
    "due_date": "2025-01-15"
}
```

#### Update Task

```
URL: http://localhost:8000/api/tasks/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "title": "Updated Survey",
    "description": "Updated description",
    "priority": "medium",
    "due_date": "2025-01-20"
}
```

#### Delete Task

```
URL: http://localhost:8000/api/tasks/{id}
Method: DELETE
Authorization: Bearer {admin_token}
```

### Geofences CRUD

#### Get All Geofences

```
URL: http://localhost:8000/api/geofences
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Geofence by ID

```
URL: http://localhost:8000/api/geofences/{id}
Method: GET
Authorization: Bearer {admin_token}
```

#### Create Geofence

```
URL: http://localhost:8000/api/geofences
Method: POST
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "Kantor Pusat",
    "center_lat": -6.200000,
    "center_lng": 106.816666,
    "radius": 100,
    "type": "office"
}
```

#### Update Geofence

```
URL: http://localhost:8000/api/geofences/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "Kantor Cabang",
    "center_lat": -6.300000,
    "center_lng": 106.900000,
    "radius": 150,
    "type": "branch"
}
```

#### Delete Geofence

```
URL: http://localhost:8000/api/geofences/{id}
Method: DELETE
Authorization: Bearer {admin_token}
```

### Attendances (View Only)

```
URL: http://localhost:8000/api/attendances
Method: GET
Authorization: Bearer {admin_token}
```

## 3. Employee Endpoints

### Check In

```
URL: http://localhost:8000/api/attendances
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "type": "check_in"
}
```

### Check Out

```
URL: http://localhost:8000/api/attendances
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "type": "check_out"
}
```

### Get Today Attendance

```
URL: http://localhost:8000/api/attendances/today
Method: GET
Authorization: Bearer {employee_token}
```

### Get Monthly Attendance

```
URL: http://localhost:8000/api/attendances/monthly
Method: GET
Authorization: Bearer {employee_token}
```

### Get My Tasks

```
URL: http://localhost:8000/api/my-tasks
Method: GET
Authorization: Bearer {employee_token}
```

### Accept Task

```
URL: http://localhost:8000/api/tasks/{id}/accept
Method: POST
Authorization: Bearer {employee_token}
```

### Start Task

```
URL: http://localhost:8000/api/tasks/{id}/start
Method: POST
Authorization: Bearer {employee_token}
```

### Complete Task

```
URL: http://localhost:8000/api/tasks/{id}/complete
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "completion_notes": "Task completed successfully"
}
```

### Update Location

```
URL: http://localhost:8000/api/locations
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "trackable_type": "employee",
    "trackable_id": 1,
    "speed": 0,
    "accuracy": 10
}
```

### Get Employee Dashboard

```
URL: http://localhost:8000/api/employee/dashboard
Method: GET
Authorization: Bearer {employee_token}
```

## 4. Vehicle Tracking

### Update Vehicle Location

```
URL: http://localhost:8000/api/vehicles/1/location
Method: POST
Authorization: Bearer {token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "speed": 60,
    "accuracy": 5
}
```

### Get Vehicle Location History

```
URL: http://localhost:8000/api/locations/vehicle/{id}/history
Method: GET
Authorization: Bearer {token}
```

## Testing Flow

1. Login as admin → Get token
2. Create employees and vehicles
3. Login as employee → Get token
4. Test check-in/out and location updates
5. Test vehicle tracking
6. View reports as admin
