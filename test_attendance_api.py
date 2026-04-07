#!/usr/bin/env python3
import requests
import json

BASE_URL = "http://127.0.0.1:8000"
ADMIN_TOKEN = "12|UbjJ8oT8xXyJ0y4vPfQzSQMZHXfYcJ7lqL2JhHs02d93f86e"
EMPLOYEE_ID = 2

headers = {
    "Authorization": f"Bearer {ADMIN_TOKEN}",
    "Content-Type": "application/json"
}

print("=" * 80)
print("TEST: Admin Manual Attendance Creation Endpoint")
print("=" * 80)

# Test Case 1: Valid creation
print("\n[TEST 1] Creating attendance for employee in admin's tenant (expect 201)")
print("-" * 80)
payload1 = {
    "employee_id": EMPLOYEE_ID,
    "date": "2026-04-06",
    "status": "absent",
    "check_in": "08:00",
    "check_out": "17:00",
    "notes": "Sakit (surat keterangan)"
}
response1 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload1, headers=headers)
print(f"Status Code: {response1.status_code}")
print(f"Response: {json.dumps(response1.json(), indent=2)}")

# Test Case 2: Duplicate detection (409 Conflict)
print("\n[TEST 2] Attempting duplicate - same date (expect 409)")
print("-" * 80)
payload2 = {
    "employee_id": EMPLOYEE_ID,
    "date": "2026-04-06",
    "status": "present",
    "check_in": "09:00",
    "check_out": "17:30",
    "notes": "Retry same date"
}
response2 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload2, headers=headers)
print(f"Status Code: {response2.status_code}")
print(f"Response: {json.dumps(response2.json(), indent=2)}")

# Test Case 3: Different date (should be 201)
print("\n[TEST 3] Creating attendance for different date (expect 201)")
print("-" * 80)
payload3 = {
    "employee_id": EMPLOYEE_ID,
    "date": "2026-04-07",
    "status": "present",
    "check_in": "08:30",
    "check_out": "17:00",
    "notes": "Normal day"
}
response3 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload3, headers=headers)
print(f"Status Code: {response3.status_code}")
print(f"Response: {json.dumps(response3.json(), indent=2)}")

# Test Case 4: Validation error - missing required field (422)
print("\n[TEST 4] Missing required field 'status' (expect 422)")
print("-" * 80)
payload4 = {
    "employee_id": EMPLOYEE_ID,
    "date": "2026-04-08"
}
response4 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload4, headers=headers)
print(f"Status Code: {response4.status_code}")
print(f"Response: {json.dumps(response4.json(), indent=2)}")

# Test Case 5: Invalid status value (422)
print("\n[TEST 5] Invalid status value (expect 422)")
print("-" * 80)
payload5 = {
    "employee_id": EMPLOYEE_ID,
    "date": "2026-04-09",
    "status": "invalid_status",
    "check_in": "08:00",
    "check_out": "17:00"
}
response5 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload5, headers=headers)
print(f"Status Code: {response5.status_code}")
print(f"Response: {json.dumps(response5.json(), indent=2)}")

# Test Case 6: Invalid employee (404)
print("\n[TEST 6] Invalid employee ID (expect 404)")
print("-" * 80)
payload6 = {
    "employee_id": 9999,
    "date": "2026-04-10",
    "status": "present",
    "check_in": "08:00",
    "check_out": "17:00"
}
response6 = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload6, headers=headers)
print(f"Status Code: {response6.status_code}")
print(f"Response: {json.dumps(response6.json(), indent=2)}")

# Test Case 7: Test all status types
print("\n[TEST 7] Testing all valid status types")
print("-" * 80)
status_types = ["present", "late", "sick", "leave", "absent"]
for i, status in enumerate(status_types, start=11):
    payload = {
        "employee_id": EMPLOYEE_ID,
        "date": f"2026-04-{i:02d}",
        "status": status,
        "notes": f"Testing {status} status"
    }
    response = requests.post(f"{BASE_URL}/api/admin/attendances", json=payload, headers=headers)
    print(f"  {status}: Status {response.status_code}")

print("\n" + "=" * 80)
print("TEST SUMMARY COMPLETE")
print("=" * 80)
