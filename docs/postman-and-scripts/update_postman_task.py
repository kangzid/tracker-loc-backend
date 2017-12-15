import json

file_path = 'docs/LocaTrack-Backend-API.postman_collection.json'

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

get_my_tasks = {
    "name": "Get My Tasks (All)",
    "request": {
        "auth": { "type": "bearer", "bearer": [{ "key": "token", "value": "{{token}}", "type": "string" }] },
        "method": "GET",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
            "raw": "{{base_url}}/my-tasks",
            "host": ["{{base_url}}"],
            "path": ["my-tasks"]
        },
        "description": "Mengambil semua tugas (tasks) yang ditugaskan kepada saya (employee) dengan pagination."
    },
    "response": []
}

filter_my_tasks = {
    "name": "Filter Tasks by Priority",
    "request": {
        "auth": { "type": "bearer", "bearer": [{ "key": "token", "value": "{{token}}", "type": "string" }] },
        "method": "GET",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
            "raw": "{{base_url}}/my-tasks?priority=urgent",
            "host": ["{{base_url}}"],
            "path": ["my-tasks"],
            "query": [
                {
                    "key": "priority",
                    "value": "urgent",
                    "description": "low, medium, high, atau urgent"
                }
            ]
        },
        "description": "Mengambil semua tugas berdasarkan prioritas."
    },
    "response": []
}

get_task_by_id = {
    "name": "Get Task Detail by ID",
    "request": {
        "auth": { "type": "bearer", "bearer": [{ "key": "token", "value": "{{token}}", "type": "string" }] },
        "method": "GET",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
            "raw": "{{base_url}}/tasks/:id",
            "host": ["{{base_url}}"],
            "path": ["tasks", ":id"],
            "variable": [
                {
                    "key": "id",
                    "value": "1"
                }
            ]
        },
        "description": "Melihat detail lengkap satu tugas tertentu menggunakan ID-nya."
    },
    "response": []
}


def fix_tasks_folder(items):
    for item in items:
        # Check folder names
        if 'name' in item and item['name'] == 'Tasks' and 'item' in item:
            # We assume we are in "Employee App > Tasks" or "Admin Panel > Tasks"
            # To be safe, we will just add them if not exists in ANY "Tasks" folder,
            # or specifically if "Complete Task" exists inside it.
            if any(i.get('name') == 'Complete Task' for i in item['item']):
                # It's the Employee App Tasks folder!
                if not any(i.get('name') == 'Get My Tasks (All)' for i in item['item']):
                    item['item'].insert(0, get_my_tasks)
                if not any(i.get('name') == 'Filter Tasks by Priority' for i in item['item']):
                    item['item'].insert(1, filter_my_tasks)
                if not any(i.get('name') == 'Get Task Detail by ID' for i in item['item']):
                    item['item'].insert(2, get_task_by_id)

        # Modify Complete Task
        if 'name' in item and item['name'] == 'Complete Task' and 'request' in item and 'body' in item['request']:
            try:
                body_raw = item['request']['body'].get('raw', '{}')
                body_json = json.loads(body_raw)
                if 'latitude' not in body_json:
                    body_json['latitude'] = -6.21462
                    body_json['longitude'] = 106.84513
                    item['request']['body']['raw'] = json.dumps(body_json, indent=4)
                    item['request']['description'] = "Menyelesaikan task. Dapat menyertakan 'completion_notes' opsional dan 'latitude', 'longitude' jika karyawan perlu mencatat koordinat saat menyelesaikan tugasnya."
            except Exception as e:
                pass
                
        # recurse
        if 'item' in item:
            fix_tasks_folder(item['item'])

fix_tasks_folder(data['item'])

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("Tasks folder updated successfully!")
