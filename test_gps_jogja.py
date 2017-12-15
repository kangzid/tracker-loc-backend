import requests
import time
import random

BASE_URL = "http://127.0.0.1:8000/api"

print("=======================================")
print(" GPS Simulation Tool - Jogja Area")
print("=======================================")
print("Pilih Target:")
print("1. Karyawan (Employee)")
print("2. Kendaraan (Vehicle)")
target_type = input("Pilih target (1/2): ")

if target_type == "1":
    print("\n[INFO] Untuk Karyawan, Anda membutuhkan Bearer Token (bisa didapatkan dari response Login / di DB tabel personal_access_tokens).")
    token = input("Masukkan Bearer Token Karyawan: ")
    emp_id = input("Masukkan Employee ID (Angka, contoh: 1): ")
else:
    print("\n[INFO] Untuk Kendaraan, Anda membutuhkan Tracking Token (contoh: 2d08d4cc...).")
    token = input("Masukkan Tracking Token Kendaraan: ")

print("\nPilih Mode Simulasi:")
print("1. Diam / Stasioner (1 titik yang sama - menguji efisiensi history)")
print("2. Bergerak (Berpindah titik dari Tugu Jogja ke Malioboro - menguji route path)")
mode = input("Pilih mode (1/2): ")

# Titik awal: Area Tugu Jogja
lat = -7.7829
lon = 110.3671

headers = {
    "Accept": "application/json"
}

if target_type == "1":
    headers["Authorization"] = f"Bearer {token}"
    endpoint = f"{BASE_URL}/locations"
else:
    headers["X-Tracking-Token"] = token
    endpoint = f"{BASE_URL}/gps/track"

print(f"\nMemulai pengiriman data ke {endpoint}...")
print("Tekan Ctrl+C untuk berhenti.\n")

try:
    for i in range(1, 1000):
        if mode == "2":
            # Simulasi bergerak ke arah selatan (Menuju Malioboro)
            # Bergerak sekitar 10-20 meter per detik
            lat -= random.uniform(0.00005, 0.00015)
            lon -= random.uniform(0.00001, 0.00005)
            speed = random.uniform(10.0, 40.0)
        else:
            # Jika stasioner, sedikit goyang (noise GPS +- 2 meter) tapi tidak melebihi 10 meter
            lat_noise = lat + random.uniform(-0.00001, 0.00001)
            lon_noise = lon + random.uniform(-0.00001, 0.00001)
            lat = lat_noise
            lon = lon_noise
            speed = 0.0
        
        payload = {
            "latitude": lat,
            "longitude": lon,
            "speed": speed,
            "accuracy": random.uniform(2.0, 10.0)
        }
        
        if target_type == "1":
            payload["trackable_type"] = "employee"
            payload["trackable_id"] = int(emp_id)

        response = requests.post(endpoint, json=payload, headers=headers)
        
        if response.status_code in [200, 201]:
            print(f"[{i}] SUCCESS - Lat: {lat:.6f}, Lon: {lon:.6f}, Speed: {speed:.1f} km/h")
        else:
            print(f"[{i}] FAILED  - Status: {response.status_code}")
            print("Details:", response.text)
            
        time.sleep(3) # Kirim setiap 3 detik agar cepat terlihat perbedaannya
except KeyboardInterrupt:
    print("\nSimulasi dihentikan oleh user.")
except Exception as e:
    print(f"\nTerjadi kesalahan: {e}")
