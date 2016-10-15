import json

file_path = 'docs/LocaTrack-Backend-API.postman_collection.json'

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

# Request 1: Change Password
change_password_req = {
    "name": "Change Password",
    "request": {
        "auth": {
            "type": "bearer",
            "bearer": [
                {
                    "key": "token",
                    "value": "{{token}}",
                    "type": "string"
                }
            ]
        },
        "method": "PUT",
        "header": [
            {
                "key": "Content-Type",
                "value": "application/json"
            },
            {
                "key": "Accept",
                "value": "application/json"
            }
        ],
        "body": {
            "mode": "raw",
            "raw": "{\n    \"current_password\": \"password123\",\n    \"password\": \"newpassword123\",\n    \"password_confirmation\": \"newpassword123\"\n}",
            "options": {
                "raw": {
                    "language": "json"
                }
            }
        },
        "url": {
            "raw": "{{base_url}}/change-password",
            "host": [
                "{{base_url}}"
            ],
            "path": [
                "change-password"
            ]
        },
        "description": "Mengganti password akun sendiri (berlaku untuk employee, admin, dan superadmin). Membutuhkan auth token dan password lama yang valid."
    },
    "response": []
}

# Request 2: Superadmin Force Reset Admin Password
reset_admin_pwd_req = {
    "name": "Admin - Force Reset Password",
    "request": {
        "auth": {
            "type": "bearer",
            "bearer": [
                {
                    "key": "token",
                    "value": "{{token}}",
                    "type": "string"
                }
            ]
        },
        "method": "PUT",
        "header": [
            {
                "key": "Content-Type",
                "value": "application/json"
            },
            {
                "key": "Accept",
                "value": "application/json"
            }
        ],
        "body": {
            "mode": "raw",
            "raw": "{\n    \"password\": \"newadminpass123\"\n}",
            "options": {
                "raw": {
                    "language": "json"
                }
            }
        },
        "url": {
            "raw": "{{base_url}}/superadmin/admins/:id/reset-password",
            "host": [
                "{{base_url}}"
            ],
            "path": [
                "superadmin",
                "admins",
                ":id",
                "reset-password"
            ],
            "variable": [
                {
                    "key": "id",
                    "value": "1"
                }
            ]
        },
        "description": "Superadmin memaksa ganti password admin tanpa memerlukan password lama atau OTP."
    },
    "response": []
}

# Recursively find folder
def traverse_and_modify(items):
    for item in items:
        # Check folder names
        if 'name' in item:
            # Add change password to Auth folders
            if item['name'] == 'Auth' and 'item' in item:
                # Add if not exists
                if not any(i.get('name') == 'Change Password' for i in item['item']):
                    item['item'].append(change_password_req)
            
            # Add to Superadmin Panel
            if item['name'] == '👑 Superadmin Panel' and 'item' in item:
                if not any(i.get('name') == 'Change Password' for i in item['item']):
                    item['item'].insert(1, change_password_req) # insert after Dashboard
                if not any(i.get('name') == 'Admin - Force Reset Password' for i in item['item']):
                    item['item'].insert(7, reset_admin_pwd_req) # insert around admin management
                    
            # Modifiy Update Employee payload
            if item['name'] == 'Update Employee':
                try:
                    body_raw = item['request']['body']['raw']
                    body_json = json.loads(body_raw)
                    # add password parameter to example
                    body_json['password'] = "forcereset123"
                    item['request']['body']['raw'] = json.dumps(body_json, indent=4)
                    item['request']['description'] = "Update employee data. Note: Admin can force change the employee's password by passing the `password` field in the request body."
                except Exception as e:
                    pass
        
        # recurse
        if 'item' in item:
            traverse_and_modify(item['item'])

traverse_and_modify(data['item'])

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("Postman collection updated successfully!")
