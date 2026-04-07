# Pusher Configuration Complete ✅

## Backend Setup - DONE ✅

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=2137988
PUSHER_APP_KEY=737575397aad80d7c83b
PUSHER_APP_SECRET=1f4caad4350f5f3b24ae
PUSHER_APP_CLUSTER=ap1
PUSHER_HOST=api-ap1.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https
```

✅ Updated di `.env`  
✅ Config cache cleared  
✅ Ready for Pusher broadcasting!

---

## Svelte Frontend - TODO

Sekarang perlu update Svelte `.env` dengan credentials Pusher:

### Svelte `.env` Configuration

Di Svelte project root, buat atau edit `.env`:

```env
VITE_PUSHER_APP_KEY=737575397aad80d7c83b
VITE_PUSHER_CLUSTER=ap1
```

**Hanya dua ini yang perlu!**

---

## Svelte Websocket Config Update

Edit file `src/lib/services/websocket.service.ts` (atau nama file mirip):

### OLD CODE (Reverb):
```typescript
export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    // ...
});
```

### NEW CODE (Pusher):
```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_CLUSTER,
    encrypted: true,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
    },
});

export default echo;
```

**Key changes:**
- `broadcaster: 'reverb'` → `broadcaster: 'pusher'`
- Add `import Pusher from 'pusher-js'`
- Add `window.Pusher = Pusher`
- Change dari `wsHost/wsPort` ke `cluster`

---

## Svelte Checklist

Untuk tim Svelte:

```
✅ Checklist untuk Svelte

1. [ ] Update .env dengan VITE_PUSHER_APP_KEY & VITE_PUSHER_CLUSTER
2. [ ] Update websocket.service.ts: broadcaster = 'pusher'
3. [ ] Add import untuk pusher-js
4. [ ] Run: npm install pusher-js (jika belum)
5. [ ] Run: npm run dev
6. [ ] Check browser console: "WebSocket connected" message
7. [ ] Test dengan Postman: POST /api/locations
8. [ ] Watch map update real-time ✨

VITE_PUSHER Values:
- App Key: 737575397aad80d7c83b
- Cluster: ap1
```

---

## Testing Backend + Pusher

### 1. Start Backend Server
```bash
cd backend
php artisan serve
# Run di terminal 1
```

### 2. Start Svelte Dev Server
```bash
cd svelte-app
npm run dev
# Run di terminal 2
```

### 3. Test di Browser

**Step 1:** Open Svelte app → http://localhost:5173

**Step 2:** Open DevTools Console
- Look for: `✅ WebSocket connected!` message
- If error, check VITE_PUSHER values

**Step 3:** Open Postman
```
POST http://localhost:8000/api/locations
Authorization: Bearer {auth_token}

{
  "trackable_type": "employee",
  "trackable_id": 1,
  "latitude": -6.2088,
  "longitude": 106.8456,
  "speed": 45.5,
  "accuracy": 10.2
}
```

**Step 4:** Watch Svelte map
- Marker should update in real-time! ✅

---

## Pusher Dashboard Test

Di https://dashboard.pusher.com:

1. Login with Pusher account
2. Select app: locatrack-demo
3. Go to: **Debug Console**
4. Di sebelah kiri:
   ```
   Connections: 1 (harus ada, dari Svelte)
   Messages: (akan naik saat POST /api/locations)
   ```

Ini verifikasi Pusher working! ✅

---

## Quick Reference

| Item | Value |
|------|-------|
| **App ID** | 2137988 |
| **App Key** | 737575397aad80d7c83b |
| **App Secret** | 1f4caad4350f5f3b24ae |
| **Cluster** | ap1 |
| **Backend Status** | ✅ Configured |
| **Frontend Status** | 🔲 TODO |
| **Overall Status** | ⚠️ Awaiting Svelte update |

---

## Next Steps

1. **Svelte team:** Update `.env` dan websocket config
2. **Test:** Verify connection di browser console
3. **Demo:** Send location via Postman, watch map update
4. **Deploy:** Push ke cPanel (same config works!)

---

## Troubleshooting

### "Pusher is not defined"
```
→ Missing: import Pusher from 'pusher-js'
→ Or: npm install pusher-js
```

### "WebSocket not connecting"
```
→ Check VITE_PUSHER_APP_KEY in .env
→ Check VITE_PUSHER_CLUSTER in .env
→ Verify auth token is valid
```

### "Events not received"
```
→ Verify POST /api/locations is working (test with Postman)
→ Check browser console for JavaScript errors
→ Verify Pusher Dashboard shows connection
```

### "Slow/laggy updates"
```
→ Normal delay adalah 1-2 detik dari Pusher
→ Check internet connection speed
```

---

**Backend Pusher setup COMPLETE! ✅**

**Tunggu Svelte team untuk update frontend config! 🚀**
