# 🏢 COMPANY REGISTRATION FEATURE - DOCUMENTATION

## ✅ IMPLEMENTED WITH CLEAN ARCHITECTURE

### **Architecture Overview**

```
┌─────────────────────────────────────────────────────────────┐
│                     CLEAN ARCHITECTURE                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Request → FormRequest → Controller → Service → Repository  │
│                              ↓                               │
│                           Model                              │
│                              ↓                               │
│                          Database                            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 FILES CREATED

### **1. Migrations**
- `2026_04_10_100000_create_company_registrations_table.php`
- `2026_04_10_100001_add_must_change_password_to_users_table.php`

### **2. Models**
- `app/Models/CompanyRegistration.php`

### **3. Repositories**
- `app/Repositories/RepositoryInterface.php`
- `app/Repositories/BaseRepository.php`
- `app/Repositories/CompanyRegistrationRepository.php`
- `app/Repositories/AttendanceRepository.php`
- `app/Repositories/TaskRepository.php`

### **4. Services**
- `app/Services/CompanyRegistrationService.php`
- `app/Services/GeofenceService.php` (already exists)

### **5. Form Requests**
- `app/Http/Requests/CompanyRegistrationRequest.php`
- `app/Http/Requests/ApproveRegistrationRequest.php`
- `app/Http/Requests/RejectRegistrationRequest.php`

### **6. Controllers**
- `app/Http/Controllers/Api/CompanyRegistrationController.php`

### **7. Traits**
- `app/Traits/ApiResponse.php` (Consistent API responses)

### **8. Config Files**
- `config/attendance.php`
- `config/locatrack.php`

---

## 🚀 API ENDPOINTS

### **Public Endpoint**

#### **1. Register Company**
```http
POST /api/company/register
Content-Type: application/json

{
    "company_name": "PT Maju Bersama",
    "contact_email": "admin@perusahaan.com",
    "contact_phone": "08123456789"
}
```

**Response Success (201)**:
```json
{
    "success": true,
    "message": "Pendaftaran berhasil! Tim kami akan menghubungi Anda melalui WhatsApp dalam 1x24 jam untuk aktivasi akun.",
    "data": {
        "id": 1,
        "company_name": "PT Maju Bersama",
        "contact_email": "admin@perusahaan.com",
        "contact_phone": "08123456789",
        "status": "pending",
        "created_at": "2026-04-10T10:00:00.000000Z",
        "updated_at": "2026-04-10T10:00:00.000000Z"
    }
}
```

**Response Error (422)**:
```json
{
    "success": false,
    "message": "Validasi gagal",
    "errors": {
        "contact_email": ["Email sudah terdaftar"]
    }
}
```

---

### **Superadmin Endpoints**

#### **2. Get Pending Registrations**
```http
GET /api/superadmin/registrations/pending?per_page=20
Authorization: Bearer {superadmin_token}
```

**Response (200)**:
```json
{
    "success": true,
    "message": "Pending registrations retrieved successfully",
    "data": [
        {
            "id": 1,
            "company_name": "PT Maju Bersama",
            "contact_email": "admin@perusahaan.com",
            "contact_phone": "08123456789",
            "status": "pending",
            "created_at": "2026-04-10T10:00:00.000000Z"
        }
    ],
    "pagination": {
        "total": 5,
        "per_page": 20,
        "current_page": 1,
        "last_page": 1,
        "from": 1,
        "to": 5
    }
}
```

#### **3. Get Approved Registrations**
```http
GET /api/superadmin/registrations/approved?per_page=20
Authorization: Bearer {superadmin_token}
```

#### **4. Get Rejected Registrations**
```http
GET /api/superadmin/registrations/rejected?per_page=20
Authorization: Bearer {superadmin_token}
```

#### **5. Get Registration by ID**
```http
GET /api/superadmin/registrations/{id}
Authorization: Bearer {superadmin_token}
```

#### **6. Approve Registration**
```http
POST /api/superadmin/registrations/{id}/approve
Authorization: Bearer {superadmin_token}
```

**Response Success (200)**:
```json
{
    "success": true,
    "message": "Registration approved successfully",
    "data": {
        "registration": {
            "id": 1,
            "company_name": "PT Maju Bersama",
            "contact_email": "admin@perusahaan.com",
            "contact_phone": "08123456789",
            "status": "approved",
            "approved_by": 1,
            "approved_at": "2026-04-10T11:00:00.000000Z",
            "created_admin_id": 5
        },
        "admin": {
            "id": 5,
            "name": "PT Maju Bersama",
            "email": "admin@perusahaan.com",
            "role": "admin"
        },
        "credentials": {
            "email": "admin@perusahaan.com",
            "password": "Xy9#mK2pL4Qz"
        },
        "whatsapp_template": "Halo PT Maju Bersama,\n\nAkun LocaTrack Anda sudah aktif!\n\n🔐 Kredensial Login:\nEmail: admin@perusahaan.com\nPassword: Xy9#mK2pL4Qz\nLink: http://localhost:8000/login\n\nSilakan login dan ganti password Anda.\nButuh bantuan? Reply chat ini."
        }
    }
}
```

#### **7. Reject Registration**
```http
POST /api/superadmin/registrations/{id}/reject
Authorization: Bearer {superadmin_token}
Content-Type: application/json

{
    "rejection_reason": "Data perusahaan tidak lengkap"
}
```

**Response Success (200)**:
```json
{
    "success": true,
    "message": "Registration rejected successfully",
    "data": {
        "id": 1,
        "company_name": "PT Maju Bersama",
        "contact_email": "admin@perusahaan.com",
        "contact_phone": "08123456789",
        "status": "rejected",
        "approved_by": 1,
        "approved_at": "2026-04-10T11:00:00.000000Z",
        "rejection_reason": "Data perusahaan tidak lengkap"
    }
}
```

#### **8. Get Statistics**
```http
GET /api/superadmin/registrations/statistics
Authorization: Bearer {superadmin_token}
```

**Response (200)**:
```json
{
    "success": true,
    "message": "Statistics retrieved successfully",
    "data": {
        "total": 25,
        "pending": 5,
        "approved": 18,
        "rejected": 2
    }
}
```

---

## 🔄 USER FLOW

### **1. Client Registration Flow**

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CLIENT: Isi Form Daftar Perusahaan                      │
│    POST /api/company/register                               │
│    - company_name: PT Maju Bersama                         │
│    - contact_email: admin@perusahaan.com                   │
│    - contact_phone: 08123456789                            │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. SYSTEM: Validasi & Simpan                               │
│    - Check email unique                                     │
│    - Save to company_registrations table                    │
│    - Status: pending                                        │
│    - Return success message                                 │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. SUPERADMIN: Review di Dashboard                         │
│    GET /api/superadmin/registrations/pending                │
│    - Lihat list pending registrations                       │
│    - Review data perusahaan                                 │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. SUPERADMIN: Approve                                      │
│    POST /api/superadmin/registrations/{id}/approve          │
│    System auto:                                             │
│    - Create user (role: admin)                             │
│    - Generate random password                               │
│    - Set must_change_password = true                        │
│    - Update registration status = approved                  │
│    - Return credentials + WhatsApp template                 │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. SUPERADMIN: Kirim via WhatsApp                          │
│    - Copy credentials dari response                         │
│    - Copy WhatsApp template                                 │
│    - Send via WhatsApp ke contact_phone                     │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. CLIENT: Login & Change Password                         │
│    POST /api/login                                          │
│    - Email & password dari WhatsApp                         │
│    - System check must_change_password                      │
│    - Force redirect to change password                      │
│    PUT /api/change-password                                 │
│    - Set must_change_password = false                       │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏗️ DATABASE SCHEMA

### **company_registrations Table**

```sql
CREATE TABLE company_registrations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) UNIQUE NOT NULL,
    contact_phone VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_admin_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_contact_email (contact_email),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_admin_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### **users Table (Updated)**

```sql
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT FALSE AFTER is_active;
```

---

## 🎨 FRONTEND INTEGRATION

### **Landing Page Form**

```html
<form @submit.prevent="registerCompany">
    <h2>Daftar Perusahaan</h2>
    <p>Mulai kelola armada dan absensi secara instan</p>
    
    <input 
        v-model="form.company_name" 
        placeholder="Nama Perusahaan"
        required
    />
    
    <input 
        v-model="form.contact_email" 
        type="email"
        placeholder="Email Kontak"
        required
    />
    
    <input 
        v-model="form.contact_phone" 
        placeholder="Nomor Telepon (WhatsApp)"
        required
    />
    
    <button type="submit">Daftar Sekarang</button>
    
    <p>Sudah punya akun? <a href="/login">Login</a></p>
</form>
```

### **JavaScript/Svelte**

```javascript
async function registerCompany() {
    try {
        const response = await fetch('http://localhost:8000/api/company/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                company_name: form.company_name,
                contact_email: form.contact_email,
                contact_phone: form.contact_phone,
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            alert(data.message);
            // Redirect or show confirmation
        } else {
            // Show validation errors
            console.error(data.errors);
        }
    } catch (error) {
        console.error('Registration failed:', error);
    }
}
```

---

## 📝 POSTMAN COLLECTION ADDITION

Add this to your Postman collection under "Public" folder:

```json
{
    "name": "Company Registration",
    "request": {
        "method": "POST",
        "header": [
            {
                "key": "Content-Type",
                "value": "application/json"
            }
        ],
        "body": {
            "mode": "raw",
            "raw": "{\n    \"company_name\": \"PT Maju Bersama\",\n    \"contact_email\": \"admin@perusahaan.com\",\n    \"contact_phone\": \"08123456789\"\n}"
        },
        "url": {
            "raw": "{{base_url}}/company/register",
            "host": ["{{base_url}}"],
            "path": ["company", "register"]
        }
    }
}
```

Add under "Superadmin Panel" folder:

```json
{
    "name": "Company Registrations",
    "item": [
        {
            "name": "Get Pending Registrations",
            "request": {
                "method": "GET",
                "header": [],
                "url": {
                    "raw": "{{base_url}}/superadmin/registrations/pending",
                    "host": ["{{base_url}}"],
                    "path": ["superadmin", "registrations", "pending"]
                }
            }
        },
        {
            "name": "Approve Registration",
            "request": {
                "method": "POST",
                "header": [],
                "url": {
                    "raw": "{{base_url}}/superadmin/registrations/:id/approve",
                    "host": ["{{base_url}}"],
                    "path": ["superadmin", "registrations", ":id", "approve"],
                    "variable": [{"key": "id", "value": "1"}]
                }
            }
        },
        {
            "name": "Reject Registration",
            "request": {
                "method": "POST",
                "header": [],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"rejection_reason\": \"Data tidak lengkap\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/superadmin/registrations/:id/reject",
                    "host": ["{{base_url}}"],
                    "path": ["superadmin", "registrations", ":id", "reject"],
                    "variable": [{"key": "id", "value": "1"}]
                }
            }
        }
    ]
}
```

---

## ✅ TESTING CHECKLIST

### **1. Public Registration**
- [ ] Register dengan data valid
- [ ] Register dengan email duplicate
- [ ] Register dengan data invalid
- [ ] Check validation messages

### **2. Superadmin - Pending**
- [ ] Get list pending registrations
- [ ] Pagination works
- [ ] Get registration by ID

### **3. Superadmin - Approve**
- [ ] Approve registration
- [ ] Check admin user created
- [ ] Check password generated
- [ ] Check WhatsApp template
- [ ] Check must_change_password = true

### **4. Superadmin - Reject**
- [ ] Reject registration
- [ ] Check rejection reason saved
- [ ] Check status updated

### **5. Admin Login**
- [ ] Login with generated credentials
- [ ] Check must_change_password flag
- [ ] Force change password
- [ ] Login with new password

---

## 🔐 SECURITY FEATURES

1. ✅ **Email Uniqueness**: Check against both `company_registrations` and `users` tables
2. ✅ **Strong Password Generation**: Mix of uppercase, lowercase, numbers, special chars
3. ✅ **Force Password Change**: `must_change_password` flag
4. ✅ **Authorization**: Only superadmin can approve/reject
5. ✅ **Validation**: Form Request validation
6. ✅ **Transaction**: DB transaction for approve process

---

## 📊 CONSISTENT API RESPONSE FORMAT

All endpoints now use consistent format via `ApiResponse` trait:

**Success Response**:
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... }
}
```

**Paginated Response**:
```json
{
    "success": true,
    "message": "Data retrieved",
    "data": [ ... ],
    "pagination": {
        "total": 100,
        "per_page": 20,
        "current_page": 1,
        "last_page": 5,
        "from": 1,
        "to": 20
    }
}
```

---

## 🚀 DEPLOYMENT STEPS

1. **Run Migrations**:
```bash
php artisan migrate
```

2. **Test Endpoints**:
```bash
# Test public registration
curl -X POST http://localhost:8000/api/company/register \
  -H "Content-Type: application/json" \
  -d '{"company_name":"PT Test","contact_email":"test@test.com","contact_phone":"08123456789"}'

# Test superadmin endpoints (need token)
curl -X GET http://localhost:8000/api/superadmin/registrations/pending \
  -H "Authorization: Bearer YOUR_SUPERADMIN_TOKEN"
```

3. **Update Frontend**:
- Add registration form to landing page
- Integrate with API
- Handle success/error responses

---

## 📚 NEXT STEPS

### **For Full Clean Architecture (Optional)**:

1. Create more Form Requests for existing endpoints
2. Create Policies for authorization
3. Create more Services for business logic
4. Create more Repositories for data access
5. Refactor existing controllers to use services

**Estimated Time**: 4-6 hours for full refactoring

---

**Status**: ✅ Company Registration Feature Complete with Clean Architecture
**Version**: 1.0.0
**Date**: 2026-04-10
