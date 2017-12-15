import json

file_path = 'docs/LocaTrack-Backend-API.postman_collection.json'

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

def fix_payloads(items):
    for item in items:
        if 'name' in item and 'request' in item and 'body' in item['request']:
            name = item['name']
            
            try:
                body_raw = item['request']['body'].get('raw', '{}')
                body_json = json.loads(body_raw)

                # Fix Create / Update Geofence
                if name in ['Create Geofence', 'Update Geofence']:
                    if 'latitude' in body_json:
                        body_json['center_lat'] = body_json.pop('latitude')
                    if 'longitude' in body_json:
                        body_json['center_lng'] = body_json.pop('longitude')
                    if 'type' not in body_json:
                        body_json['type'] = 'office'
                        
                    item['request']['body']['raw'] = json.dumps(body_json, indent=4)

                # Fix Create / Update Vehicle
                if name in ['Create Vehicle', 'Update Vehicle']:
                    if 'license_plate' in body_json:
                        body_json['vehicle_number'] = body_json.pop('license_plate')
                    if 'vehicle_type' not in body_json:
                        body_json['vehicle_type'] = 'Mobil'
                        
                    item['request']['body']['raw'] = json.dumps(body_json, indent=4)
                    
            except Exception as e:
                pass
        
        # recurse
        if 'item' in item:
            fix_payloads(item['item'])

fix_payloads(data['item'])

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("Postman collection payloads fixed!")
