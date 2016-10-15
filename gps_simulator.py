#!/usr/bin/env python3
"""
GPS Simulator - Simulasi GPS device mengirim lokasi ke server
Untuk testing tanpa hardware GPS
"""

import requests
import time
import random
import json

# Configuration
API_URL = "http://localhost:8000/api/gps"
TRACKING_TOKEN = "53f424c1e149bd551a0e43a95e418fd57fc9cf317e6550bf5dddaaacdbe07c9e"  # Ganti dengan token vehicle Anda

# Starting location (Jakarta)
START_LAT = -6.200000
START_LNG = 106.816666

def test_connection():
    """Test koneksi ke server"""
    print("🔍 Testing connection...")
    
    headers = {
        "X-Tracking-Token": TRACKING_TOKEN
    }
    
    try:
        response = requests.get(f"{API_URL}/ping", headers=headers)
        data = response.json()
        
        if data.get("success"):
            print("✅ Connection successful!")
            print(f"   Vehicle: {data['data']['vehicle_number']}")
            print(f"   Type: {data['data']['vehicle_type']}")
            return True
        else:
            print(f"❌ Connection failed: {data.get('message')}")
            return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

def send_location(lat, lng, speed=0, accuracy=10.0):
    """Kirim lokasi ke server"""
    headers = {
        "X-Tracking-Token": TRACKING_TOKEN,
        "Content-Type": "application/json"
    }
    
    payload = {
        "latitude": lat,
        "longitude": lng,
        "speed": speed,
        "accuracy": accuracy
    }
    
    try:
        response = requests.post(f"{API_URL}/track", headers=headers, json=payload)
        data = response.json()
        
        if data.get("success"):
            print(f"✅ Location sent: {lat:.6f}, {lng:.6f} | Speed: {speed:.1f} km/h")
            return True
        else:
            print(f"❌ Failed: {data.get('message')}")
            return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

def simulate_movement():
    """Simulasi pergerakan kendaraan"""
    print("\n🚗 Starting GPS simulation...")
    print("   Press Ctrl+C to stop\n")
    
    lat = START_LAT
    lng = START_LNG
    
    try:
        while True:
            # Simulasi pergerakan random (dalam radius kecil)
            lat += random.uniform(-0.001, 0.001)  # ~100 meter
            lng += random.uniform(-0.001, 0.001)
            speed = random.uniform(0, 80)  # 0-80 km/h
            
            send_location(lat, lng, speed)
            
            # Kirim setiap 5 detik
            time.sleep(5)
            
    except KeyboardInterrupt:
        print("\n\n🛑 Simulation stopped")

def get_status():
    """Cek status vehicle"""
    print("\n📊 Getting vehicle status...")
    
    headers = {
        "X-Tracking-Token": TRACKING_TOKEN
    }
    
    try:
        response = requests.get(f"{API_URL}/status", headers=headers)
        data = response.json()
        
        if data.get("success"):
            vehicle = data['data']['vehicle']
            location = data['data']['location']
            
            print(f"\n✅ Vehicle Status:")
            print(f"   Number: {vehicle['vehicle_number']}")
            print(f"   Type: {vehicle['vehicle_type']}")
            print(f"   Active: {vehicle['is_active']}")
            print(f"\n📍 Last Location:")
            print(f"   Lat: {location['latitude']}")
            print(f"   Lng: {location['longitude']}")
            print(f"   Updated: {location['last_update']}")
        else:
            print(f"❌ Failed: {data.get('message')}")
    except Exception as e:
        print(f"❌ Error: {e}")

def main():
    """Main menu"""
    print("=" * 50)
    print("🚗 GPS Tracking Simulator")
    print("=" * 50)
    
    if not test_connection():
        print("\n⚠️  Please check your TRACKING_TOKEN and server URL")
        return
    
    while True:
        print("\n" + "=" * 50)
        print("Menu:")
        print("1. Send single location")
        print("2. Start continuous simulation")
        print("3. Get vehicle status")
        print("4. Exit")
        print("=" * 50)
        
        choice = input("\nSelect option (1-4): ")
        
        if choice == "1":
            lat = float(input("Enter latitude: ") or START_LAT)
            lng = float(input("Enter longitude: ") or START_LNG)
            speed = float(input("Enter speed (km/h): ") or 0)
            send_location(lat, lng, speed)
            
        elif choice == "2":
            simulate_movement()
            
        elif choice == "3":
            get_status()
            
        elif choice == "4":
            print("\n👋 Goodbye!")
            break
        else:
            print("❌ Invalid option")

if __name__ == "__main__":
    main()
