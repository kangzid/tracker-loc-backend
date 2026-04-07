#!/usr/bin/env python3
import requests
import json

# Test location update
url = "http://localhost:8000/api/gps/track"
token = "5a29b4f206d17e021b57c51cc9c7d8f58b9504c8d36999ed992a40a3939a7b18"

headers = {
    "X-Tracking-Token": token,
    "Content-Type": "application/json"
}

payload = {
    "latitude": -6.2,
    "longitude": 106.8,
    "speed": 50.5,
    "accuracy": 10.2
}

print("Testing location update...")
print(f"URL: {url}")
print(f"Token: {token}")
print(f"Payload: {json.dumps(payload, indent=2)}")
print()

try:
    response = requests.post(url, headers=headers, json=payload, timeout=5)
    print(f"Status Code: {response.status_code}")
    print(f"Response Text: {response.text}")
    
    try:
        print(f"JSON Response: {json.dumps(response.json(), indent=2)}")
    except:
        print("Could not parse JSON response")
        
except Exception as e:
    print(f"Error: {e}")
    import traceback
    traceback.print_exc()
