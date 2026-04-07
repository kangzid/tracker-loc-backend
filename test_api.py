#!/usr/bin/env python3
import requests
import json

# Test endpoint
url = "http://localhost:8000/api/gps/ping"
headers = {
    "X-Tracking-Token": "5a29b4f206d17e021b57c51cc9c7d8f58b9504c8d36999ed992a40a3939a7b18"
}

try:
    response = requests.get(url, headers=headers, timeout=5)
    print(f"Status Code: {response.status_code}")
    print(f"Response: {response.text}")
    print(f"JSON: {response.json()}")
except Exception as e:
    print(f"Error: {e}")
