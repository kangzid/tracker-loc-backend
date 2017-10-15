# 🏢 COMPANY REGISTRATION GUIDE - PRODUCTION

## 🎯 SUPERADMIN GUIDE: APPROVE & DELETE REGISTRATIONS

**Dokumen ini khusus untuk Superadmin mengelola pendaftaran perusahaan di environment PRODUCTION**

**Base URL Production**: `https://locatrack-prod.yourdomain.com/api`

---

## 📋 QUICK ACCESS ENDPOINTS

| Action            | Method | Endpoint                                 | Auth Required       |
| ----------------- | ------ | ---------------------------------------- | ------------------- |
| Lihat Pending     | `GET`  | `/superadmin/registrations/pending`      | ✅ Superadmin Token |
| Lihat Approved    | `GET`  | `/superadmin/registrations/approved`     | ✅ Superadmin Token |
| Lihat Rejected    | `GET`  | `/superadmin/registrations/rejected`     | ✅ Superadmin Token |
| **APPROVE**       | `POST` | `/superadmin/registrations/{id}/approve` | ✅ Superadmin Token |
| **DELETE/REJECT** | `POST` | `/superadmin/registrations/{id}/reject`  | ✅ Superadmin Token |
| Detail Reg        | `GET`  | `/superadmin/registrations/{id}`         | ✅ Superadmin Token |
| Statistik         | `GET`  | `/superadmin/registrations/statistics`   | ✅ Superadmin Token |

---

## 🚀 STEP-BY-STEP: APPROVE REGISTRATION

### **1. Login Superadmin**

```
POST https://locatrack-prod.yourdomain.com/api/login
{
    "email": "superadmin@locatrack.com",
    "password": "your_superadmin_password"
}
```

### **2. Cek Pending Registrations**

```
GET https://locatrack-prod.yourdomain.com/api/superadmin/registrations/pending?per_page=20
Authorization: Bearer {your_superadmin_token}
```

**Response**:

```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "company_name": "PT Maju Jaya Abadi",
            "contact_email": "admin@majujaya.com",
            "contact_phone": "081234567890",
            "status": "pending",
            "created_at": "2026-04-15T10:30:00Z"
        }
    ]
}
```

### **3. Review Detail (Opsional)**

```
GET https://locatrack-prod.yourdomain.com/api/superadmin/registrations/123
```

### **4. APPROVE Registration** ⭐ **AUTO TRIAL SUBSCRIPTION**

```
POST https://locatrack-prod.yourdomain.com/api/superadmin/registrations/123/approve
Authorization: Bearer {your_superadmin_token}
```

**Auto Features:**

- ✅ Create admin account
- ✅ Generate secure password
- ✅ **Trial Subscription 14 days, 2 employees, 2 vehicles**
- ✅ WhatsApp template ready

**Response Success**:

```json
{
    "success": true,
    "message": "Registration approved successfully",
    "data": {
        "registration": {
            "id": 123,
            "company_name": "PT Maju Jaya Abadi",
            "status": "approved"
        },
        "admin": {
            "id": 456,
            "name": "PT Maju Jaya Abadi",
            "email": "admin@majujaya.com",
            "role": "admin"
        },
        "credentials": {
            "email": "admin@majujaya.com",
            "password": "K7#mP9$xL2vQ8"
        },
        "whatsapp_template": "..."
    }
}
```

### **5. Kirim WhatsApp KE CLIENT**

1. **Copy** `credentials.email` dan `credentials.password`
2. **Copy** `whatsapp_template`
3. **Paste** ke WhatsApp → kirim ke `contact_phone`
4. **Simpan** record untuk tracking

---

## 🗑️ STEP-BY-STEP: DELETE/REJECT REGISTRATION

### **1. Pilih Registration untuk Reject**

Dari list pending, catat `id` yang bermasalah.

### **2. REJECT Registration**

```
POST https://locatrack-prod.yourdomain.com/api/superadmin/registrations/123/reject
Authorization: Bearer {your_superadmin_token}
Content-Type: application/json

{
    "rejection_reason": "Dokumen perusahaan tidak lengkap / Email tidak valid / Nama perusahaan sudah terdaftar"
}
```

### **🗑️ DELETE Registration (Approved/Rejected)**

```
DELETE https://locatrack-prod.yourdomain.com/api/superadmin/registrations/123
Authorization: Bearer {your_superadmin_token}
```

**Auto Actions:**

- **Approved**: Cancel subscription + deactivate tenant admin
- **Rejected**: Simple delete record
- **Safe**: No data loss, soft-delete approach

**Response**:

```json
{
    "success": true,
    "message": "Registration deleted successfully"
}
```

**Response Success**:

```json
{
    "success": true,
    "message": "Registration rejected successfully",
    "data": {
        "id": 123,
        "company_name": "PT Maju Jaya Abadi",
        "status": "rejected",
        "rejection_reason": "Dokumen perusahaan tidak lengkap",
        "updated_at": "2026-04-15T11:00:00Z"
    }
}
```

### **3. Kirim Notifikasi Reject (Opsional via WhatsApp)**

```
Halo PT Maju Jaya Abadi,

❌ Pendaftaran Anda ditolak karena:
"Dokumen perusahaan tidak lengkap"

Silakan daftar ulang dengan melengkapi dokumen:
https://locatrack-prod.yourdomain.com/register

Terima kasih,
LocaTrack Team
```

---

## 📱 POSTMAN COLLECTION - PRODUCTION READY

**Import ke Postman untuk testing cepat:**

### **Superadmin Collection**

```json
{
    "info": { "name": "LocaTrack Superadmin - Production" },
    "variable": [
        { "key": "base_url", "value": "https://locatrack-prod.yourdomain.com" }
    ]
}
```

**Endpoints siap pakai:**

1. `GET {{base_url}}/api/superadmin/registrations/pending`
2. `POST {{base_url}}/api/superadmin/registrations/{{id}}/approve`
3. `POST {{base_url}}/api/superadmin/registrations/{{id}}/reject`

---

## 📊 DASHBOARD MONITORING

### **Statistics Endpoint**

```
GET https://locatrack-prod.yourdomain.com/api/superadmin/registrations/statistics
```

**Response**:

```json
{
    "data": {
        "total": 150,
        "pending": 12,
        "approved": 135,
        "rejected": 3
    }
}
```

### **Daily Monitoring Checklist**

- [ ] Cek pending registrations (< 20)
- [ ] Approve valid requests dalam 24 jam
- [ ] Reject dengan alasan jelas
- [ ] Kirim WhatsApp credentials
- [ ] Monitor login admin baru

---

## ⚠️ TROUBLESHOOTING

| Issue                  | Solution                                            |
| ---------------------- | --------------------------------------------------- |
| `401 Unauthorized`     | Pastikan token superadmin valid (expire 24 jam)     |
| `422 Validation Error` | Cek `rejection_reason` tidak kosong saat reject     |
| `404 Not Found`        | Pastikan `id` registration valid & status `pending` |
| Password tidak work    | Generate ulang dengan approve endpoint              |
| Email sudah ada        | Reject & minta client daftar dengan email baru      |

### **Emergency Reset**

Jika ada masalah, hubungi developer:

```
DELETE RECORD (hanya via database langsung):
DELETE FROM company_registrations WHERE id = 123;
```

---

## 🔒 SECURITY NOTES - PRODUCTION

1. **Token expiry**: 24 jam, selalu login ulang
2. **HTTPS only**: Semua endpoint production paksa HTTPS
3. **Audit trail**: Semua approve/reject terekam dengan `approved_by` & timestamp
4. **Backup credentials**: Simpan di secure vault jika client lupa
5. **Rate limiting**: Max 100 requests/minute per IP

---

## 📞 SUPPORT FLOW

```
CLIENT BELUM BISA LOGIN? →
    1. Cek credentials dikirim via WhatsApp?
    2. Password sudah diganti?
    3. Akun `must_change_password = true`?
    4. Role = "admin"?
    5. Tenant isolation OK?
```

---

**Dokumen ini dibuat untuk:** Superadmin Production Environment  
**Version:** 1.0.0  
**Updated:** `date`  
**No system changes required - Pure documentation**
