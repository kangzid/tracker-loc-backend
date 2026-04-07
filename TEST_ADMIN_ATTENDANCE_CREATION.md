# Admin Attendance Creation - Testing & Verification Guide

## Feature Overview

**Endpoint:** `POST /api/admin/attendances`
**Purpose:** Allow admins to manually create attendance records for gap-filling and corrections
**Authorization:** Admin-only (verified via `isAdmin()`)
**Multi-tenant:** Yes (enforces `employee_id` + `admin_id` relationship)

## Code Implementation Review

### Location
- **File:** [app/Http/Controllers/Api/AttendanceController.php](app/Http/Controllers/Api/AttendanceController.php#L294)
- **Method:** `storeAdmin()`
- **Lines:** 294-368

### Key Features Implemented

✅ **Authorization Check**
```php
if (!$request->user()->isAdmin()) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

✅ **Input Validation**
- `employee_id`: Required, integer, exists in employees table
- `date`: Required, date format Y-m-d
- `status`: Required, must be one of: present, late, absent, sick, leave
- `check_in`: Optional, time format H:i
- `check_out`: Optional, time format H:i
- `notes`: Optional, max 255 characters

✅ **Tenant Isolation**
```php
$employee = \App\Models\Employee::where('id', $request->employee_id)
    ->where('admin_id', $adminId)
    ->first();

if (!$employee) {
    return response()->json(['message' => 'Employee not found or does not belong to your tenant'], 404);
}
```

✅ **Duplicate Prevention**
```php
$existingAttendance = Attendance::where('employee_id', $request->employee_id)
    ->where('date', $request->date)
    ->first();

if ($existingAttendance) {
    return response()->json([...], 409);
}
```

✅ **DateTime Parsing**
```php
if ($request->check_in) {
    $attendanceData['check_in'] = Carbon::parse($request->date . ' ' . $request->check_in);
}
```

### Route Definition
- **File:** [routes/api.php](routes/api.php#L64)
- **Route:** `POST /api/admin/attendances`
- **Controller:** `AttendanceController@storeAdmin`

## Expected Test Results

### Test Case 1: Valid Creation (201 Created)
```
Endpoint: POST /api/admin/attendances
Payload: {
    "employee_id": 2,
    "date": "2026-04-06",
    "status": "absent",
    "check_in": "08:00",
    "check_out": "17:00",
    "notes": "Test creation"
}
Expected: 201 Created with attendance data
```

### Test Case 2: Duplicate Prevention (409 Conflict)
```
Endpoint: POST /api/admin/attendances
Payload: Same date as Test Case 1 (2026-04-06)
Expected: 409 Conflict with message about existing record
```

### Test Case 3: Wrong Tenant Employee (404 Not Found)
```
Endpoint: POST /api/admin/attendances
Payload: {
    "employee_id": 9999 (doesn't exist or belongs to different admin),
    "date": "2026-04-10",
    "status": "present"
}
Expected: 404 with "Employee not found or does not belong to your tenant"
```

### Test Case 4: Validation Error - Missing Required Field (422)
```
Endpoint: POST /api/admin/attendances
Payload: {
    "employee_id": 2,
    "date": "2026-04-07"
    // Missing status field
}
Expected: 422 Unprocessable Entity with validation errors
```

### Test Case 5: Invalid Status Value (422)
```
Endpoint: POST /api/admin/attendances
Payload: {
    "employee_id": 2,
    "date": "2026-04-08",
    "status": "invalid_status"
}
Expected: 422 with error: "status must be one of: present, late, absent, sick, leave"
```

### Test Case 6: All Valid Status Types (201)
Test that all 5 status types can be created:
- present
- late
- absent
- sick
- leave

### Test Case 7: Optional Fields (201)
Test creating attendance without optional check_in/check_out times:
```
Payload: {
    "employee_id": 2,
    "date": "2026-04-20",
    "status": "present",
    "notes": "No check times provided"
}
Expected: 201 with check_in and check_out as null
```

### Test Case 8: Unauthorized Access (403 Forbidden)
```
Endpoint: POST /api/admin/attendances
Authorization: Bearer {employee_token}
Expected: 403 Forbidden (only admins can create)
```

## Professional Workflow Verification

### Phase 1: Employee Self-Service (Automatic)
- ✅ Employee checks in via GPS/Geofence (POST /api/attendances)
- ✅ System captures exact check-in time and location
- ✅ Employee checks out (POST /api/attendances type=check_out)

### Phase 2: Admin Management
- ✅ Admin views attendance (GET /api/attendances or /api/admin/attendances/employee/{id})
- ✅ Admin edits status if needed (PUT /api/admin/attendances/{id})
- ✅ **NEW:** Admin creates attendance for gap-fill (POST /api/admin/attendances)
- ✅ Admin deletes records if within 7 days (DELETE /api/admin/attendances/{id})

### Phase 3: Data Integrity
- ✅ Tenant isolation enforced at all endpoints
- ✅ Employee ownership verified in update/delete
- ✅ Date comparison handles timezones correctly
- ✅ Duplicate prevention prevents accidental overwrites

## Test Execution Instructions

### Using Postman
1. Set up Postman environment with:
   - `{{base_url}}`: http://127.0.0.1:8000
   - `{{admin_token}}`: (from admin login response)
   - `{{employee_token}}`: (from employee login response)

2. Import collection with new endpoint tests

3. Run test suite in order (respecting dependencies)

### Using cURL
```bash
# Test 1: Valid creation
curl -X POST http://127.0.0.1:8000/api/admin/attendances \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 2,
    "date": "2026-04-06",
    "status": "absent",
    "check_in": "08:00",
    "check_out": "17:00",
    "notes": "Test creation"
  }'

# Test 2: Duplicate prevention
curl -X POST http://127.0.0.1:8000/api/admin/attendances \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 2,
    "date": "2026-04-06",
    "status": "present"
  }'
# Should return 409 Conflict
```

### Using Python
See [test_attendance_api.py](test_attendance_api.py) for comprehensive test script.

```bash
python test_attendance_api.py
```

## Expected Responses

### 201 Created Response
```json
{
    "message": "Attendance record created successfully",
    "data": {
        "id": 5,
        "employee_id": 2,
        "date": "2026-04-06",
        "status": "absent",
        "check_in": "2026-04-06T08:00:00.000000Z",
        "check_out": "2026-04-06T17:00:00.000000Z",
        "check_in_lat": null,
        "check_in_lng": null,
        "check_out_lat": null,
        "check_out_lng": null,
        "notes": "Test creation",
        "created_at": "2026-04-08T...",
        "updated_at": "2026-04-08T...",
        "employee": {
            "id": 2,
            "user_id": 2,
            "admin_id": 1,
            "name": "Jane Doe",
            "employee_id": "EMP002",
            "email": "jane@locatrack.com",
            "phone": "081234567891",
            "department": "IT",
            "position": "Developer",
            "created_at": "...",
            "updated_at": "...",
            "user": {
                "id": 2,
                "name": "Jane Doe",
                "email": "jane@locatrack.com"
            }
        }
    }
}
```

### 409 Conflict Response
```json
{
    "message": "Attendance record already exists for this date",
    "date": "2026-04-06",
    "existing_id": 3,
    "note": "Use update endpoint to modify existing record"
}
```

### 404 Not Found Response
```json
{
    "message": "Employee not found or does not belong to your tenant"
}
```

### 422 Validation Error Response
```json
{
    "errors": {
        "status": ["The status field is required."],
        "date": ["The date field must be a date in Y-m-d format."]
    }
}
```

### 403 Forbidden Response
```json
{
    "message": "Unauthorized"
}
```

## Verification Checklist

- [ ] Code implements all validation checks correctly
- [ ] Authorization restricts to admin-only
- [ ] Tenant isolation prevents cross-tenant access
- [ ] Duplicate prevention returns 409 Conflict
- [ ] Datetime parsing handles all time formats
- [ ] Error responses have correct HTTP status codes
- [ ] Response payload includes employee relationship data
- [ ] Route properly registered in routes/api.php
- [ ] All imports (Carbon, Validator) present
- [ ] API documentation updated with endpoint details

## Integration Points

### Related Endpoints
- `GET /api/attendances` - View own attendance (employee)
- `GET /api/admin/attendances/employee/{id}` - View employee attendance (admin)
- `PUT /api/admin/attendances/{id}` - Update attendance status
- `DELETE /api/admin/attendances/{id}` - Delete attendance (7-day window)

### Database Schema
- **Table:** attendances
- **Columns:** employee_id, date, status, check_in, check_out, check_in_lat, check_in_lng, check_out_lat, check_out_lng, notes
- **Unique Constraint:** (employee_id, date) pair

### Security Considerations
✅ **Tenant Isolation:** Employee must belong to admin's tenant via `admin_id`
✅ **Authorization:** Only authenticated admins can create
✅ **Input Sanitization:** Validator handles all input
✅ **Date Format:** Enforced via regex pattern Y-m-d
✅ **DateTime Parsing:** Uses Carbon for safe timezone handling

## Deployment Checklist

- [x] Code written and tested for logic errors
- [x] Route registered in routes/api.php
- [x] API documentation updated
- [x] Git commit created with feature documentation
- [x] Test script created for comprehensive testing
- [ ] Integration tested on staging server
- [ ] Performance tested with large datasets
- [ ] Monitoring/logging implemented
- [ ] Production deployment scheduled
