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

#### Update User/Admin

```
URL: http://localhost:8000/api/users/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "name": "Updated Admin Name",
    "email": "updated@admin.com",
    "password": "newpassword123",
    "role": "admin",
    "is_active": true
}
```

#### Delete User/Admin

```
URL: http://localhost:8000/api/users/{id}
Method: DELETE
Authorization: Bearer {admin_token}

// Note: Cannot delete your own account
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

### Attendances Management

#### Get All Attendances

```
URL: http://localhost:8000/api/attendances
Method: GET
Authorization: Bearer {admin_token}
```

#### Get Employee Monthly Attendances (Admin Only)

```
URL: http://localhost:8000/api/admin/attendances/employee/{employee_id}
Method: GET
Authorization: Bearer {admin_token}
Query Parameters:
- month (optional): 1-12 (default: current month)
- year (optional): YYYY (default: current year)

// Example: Get current month
http://localhost:8000/api/admin/attendances/employee/1

// Example: Get September 2025
http://localhost:8000/api/admin/attendances/employee/1?month=9&year=2025

// Response includes all days in month with attendance data
{
    "month": 9,
    "year": 2025,
    "days_in_month": 30,
    "attendances": [
        {
            "date": "2025-09-01",
            "day_name": "Sunday",
            "attendance": null
        },
        {
            "date": "2025-09-02",
            "day_name": "Monday", 
            "attendance": {
                "id": 1,
                "check_in": "08:00:00",
                "check_out": "17:00:00",
                "status": "present"
            }
        }
    ]
}
```

#### Edit Attendance (Admin Only - Max 7 days old)

```
URL: http://localhost:8000/api/admin/attendances/{id}
Method: PUT
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "check_in": "08:30",
    "check_out": "17:00",
    "status": "late",
    "notes": "Terlambat karena macet"
}

// Note: Can only edit attendance within 7 days from attendance date
```

#### Delete Attendance (Admin Only - Max 7 days old)

```
URL: http://localhost:8000/api/admin/attendances/{id}
Method: DELETE
Authorization: Bearer {admin_token}

// Note: Can only delete attendance within 7 days from attendance date
```

#### Cleanup Old Attendances (Admin Only)

```
URL: http://localhost:8000/api/admin/attendances/cleanup
Method: POST
Authorization: Bearer {admin_token}

// Manually delete all attendances older than 3 months
// Auto cleanup runs monthly via scheduled task
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

### Check Location (Before Attendance)

```
URL: http://localhost:8000/api/attendances/check-location
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666
}

// Response if inside office area:
{
    "is_in_office": true,
    "message": "Anda berada di area kantor"
}

// Response if outside office area:
{
    "is_in_office": false,
    "message": "Anda berada di luar area kantor"
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

### Employee Dashboard

```
URL: http://localhost:8000/api/employee/dashboard
Method: GET
Authorization: Bearer {employee_token}
```

## 4. Notifications (No Firebase Required)

### Get All Notifications

```
URL: http://localhost:8000/api/notifications
Method: GET
Authorization: Bearer {employee_token}
```

### Get Unread Notifications

```
URL: http://localhost:8000/api/notifications/unread
Method: GET
Authorization: Bearer {employee_token}

// Use this endpoint for polling every 30 seconds in Flutter
// Response:
[
    {
        "id": 1,
        "title": "Tugas Baru Hari Ini",
        "message": "Survey Lokasi",
        "type": "new_task",
        "data": {
            "task_id": 1,
            "priority": "high"
        },
        "is_read": false,
        "created_at": "2025-01-10T08:00:00.000000Z"
    }
]
```

### Mark Notification as Read

```
URL: http://localhost:8000/api/notifications/{id}/read
Method: POST
Authorization: Bearer {employee_token}
```

### Mark All Notifications as Read

```
URL: http://localhost:8000/api/notifications/read-all
Method: POST
Authorization: Bearer {employee_token}
```

## 5. Tasks (Employee)

### Get My Tasks

```
URL: http://localhost:8000/api/my-tasks
Method: GET
Authorization: Bearer {employee_token}
Query Parameters:
- status (optional): pending, in_progress, completed, cancelled
- priority (optional): low, medium, high, urgent

// Example: Get pending tasks
http://localhost:8000/api/my-tasks?status=pending
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

## 6. Location Tracking

### Store Location (Auto Tracking)

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

// Note: Only keeps latest location per employee (no accumulation)
```

### Live Tracking (Admin)

```
URL: http://localhost:8000/api/locations/live
Method: GET
Authorization: Bearer {admin_token}
```

### Employee Location History

```
URL: http://localhost:8000/api/locations/employee/{employee_id}/history
Method: GET
Authorization: Bearer {admin_token}
Query Parameters:
- date (optional): YYYY-MM-DD
- start_date & end_date (optional): YYYY-MM-DD

// Example: Get today's history
http://localhost:8000/api/locations/employee/1/history?date=2025-01-10
```

### Share Location

```
URL: http://localhost:8000/api/locations/share
Method: POST
Authorization: Bearer {employee_token}
Content-Type: application/json

{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "duration": 60
}

// Response:
{
    "share_token": "abc123...",
    "share_url": "http://localhost:8000/api/shared-location/abc123...",
    "expires_at": "2025-01-10T09:00:00.000000Z"
}
```

### Get Shared Location (Public)

```
URL: http://localhost:8000/api/shared-location/{token}
Method: GET
// No authorization required
```

## 7. Error Responses

### Geofencing Error (Outside Office Area)

```
// When trying to check-in/out outside office area:
HTTP 422 Unprocessable Entity
{
    "error": "OUTSIDE_GEOFENCE",
    "message": "Anda berada di luar area kantor. Silakan mendekat ke area kantor untuk melakukan absensi.",
    "title": "Lokasi Tidak Valid"
}

// Use this response to show popup notification in Flutter
```

## 8. Flutter Implementation Notes

### Notification Polling
```dart
// Poll for notifications every 30 seconds
Timer.periodic(Duration(seconds: 30), (timer) async {
    final response = await http.get(
        Uri.parse('$baseUrl/notifications/unread'),
        headers: {'Authorization': 'Bearer $token'}
    );
    
    if (response.statusCode == 200) {
        final notifications = json.decode(response.body);
        if (notifications.isNotEmpty) {
            // Show popup notification
            showNotificationPopup(notifications[0]);
        }
    }
});
```

### Geofencing Check
```dart
// Check location before attendance
final checkResponse = await http.post(
    Uri.parse('$baseUrl/attendances/check-location'),
    headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json'
    },
    body: json.encode({
        'latitude': currentLat,
        'longitude': currentLng
    })
);

if (checkResponse.statusCode == 200) {
    final result = json.decode(checkResponse.body);
    if (result['is_in_office']) {
        // Proceed with attendance
    } else {
        // Show error message
    }
}
```

## Base URL for Production

When deployed to cPanel:
```
Base URL: https://yourdomain.com/api
```

Replace `http://localhost:8000/api` with your production URL.

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
