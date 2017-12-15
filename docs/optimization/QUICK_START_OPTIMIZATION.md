# ⚡ QUICK START - APPLY OPTIMIZATIONS

## 🚀 Langkah Cepat (5 Menit)

### 1. Run Database Migrations (Add Indexes)
```bash
php artisan migrate
```

**Output yang diharapkan:**
```
Migrating: 2026_04_10_000000_add_performance_indexes
Migrated:  2026_04_10_000000_add_performance_indexes (123.45ms)
```

### 2. Clear All Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 3. Optimize for Production (Optional)
```bash
php artisan optimize
php artisan config:cache
php artisan route:cache
```

### 4. Test API Endpoints
```bash
# Test attendance endpoint
curl -X GET "http://localhost:8000/api/attendances" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test location tracking
curl -X GET "http://localhost:8000/api/locations/live-tracking" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## ✅ Verification Checklist

### Database Indexes
```sql
-- Check if indexes are created
SHOW INDEX FROM attendances;
SHOW INDEX FROM tasks;
SHOW INDEX FROM locations;
SHOW INDEX FROM employees;
```

**Expected**: Harus ada index dengan nama `idx_*`

### Cache Working
```bash
# Test cache
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# Output: "value"
```

### Middleware Registered
```bash
php artisan route:list | grep "rate.limit"
```

**Expected**: Middleware `rate.limit` terdaftar

---

## 🎯 Apply Rate Limiting ke Routes

Edit `routes/api.php`:

```php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LocationController;

// Public routes dengan rate limiting
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('rate.limit:login');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('rate.limit:login');

// Protected routes dengan rate limiting
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Attendance dengan rate limit
    Route::post('/attendances', [AttendanceController::class, 'store'])
        ->middleware('rate.limit:attendance');
    
    Route::post('/attendances/check-location', [AttendanceController::class, 'checkLocation'])
        ->middleware('rate.limit:attendance');
    
    // Location tracking dengan rate limit
    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('rate.limit:location');
    
    // General API rate limit untuk endpoints lainnya
    Route::get('/attendances', [AttendanceController::class, 'index'])
        ->middleware('rate.limit:api');
    
    Route::get('/tasks', [TaskController::class, 'index'])
        ->middleware('rate.limit:api');
});
```

---

## 📊 Test Performance

### Before vs After Comparison

#### Test 1: Attendance List Query
```bash
# Jalankan di tinker
php artisan tinker

>>> $start = microtime(true);
>>> $attendances = \App\Models\Attendance::with('employee.user')->paginate(20);
>>> $end = microtime(true);
>>> echo "Time: " . (($end - $start) * 1000) . "ms";
```

**Expected**: 
- Before: ~500ms
- After: ~50ms (10x faster)

#### Test 2: Geofence Check
```bash
>>> $start = microtime(true);
>>> $service = app(\App\Services\GeofenceService::class);
>>> $result = $service->isInsideGeofence(-6.200000, 106.816666, 1);
>>> $end = microtime(true);
>>> echo "Time: " . (($end - $start) * 1000) . "ms";
```

**Expected**:
- First call: ~50ms (database query)
- Second call: ~5ms (from cache)

#### Test 3: Database Query Count
```bash
>>> \DB::enableQueryLog();
>>> $attendances = \App\Models\Attendance::with(['employee' => function($q) {
        $q->select('id', 'user_id', 'employee_id');
    }, 'employee.user' => function($q) {
        $q->select('id', 'name', 'email');
    }])->paginate(20);
>>> count(\DB::getQueryLog());
```

**Expected**:
- Before: 20-30 queries (N+1 problem)
- After: 3-5 queries (optimized)

---

## 🔥 Load Testing (Optional)

### Install k6 (Load Testing Tool)
```bash
# Windows (via Chocolatey)
choco install k6

# Or download from: https://k6.io/docs/get-started/installation/
```

### Create Test Script: `load-test.js`
```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 100 },  // Ramp up to 100 users
    { duration: '1m', target: 100 },   // Stay at 100 users
    { duration: '30s', target: 500 },  // Ramp up to 500 users
    { duration: '1m', target: 500 },   // Stay at 500 users
    { duration: '30s', target: 0 },    // Ramp down
  ],
};

export default function () {
  const BASE_URL = 'http://localhost:8000/api';
  const TOKEN = 'YOUR_AUTH_TOKEN_HERE';
  
  const headers = {
    'Authorization': `Bearer ${TOKEN}`,
    'Content-Type': 'application/json',
  };
  
  // Test attendance list
  let res = http.get(`${BASE_URL}/attendances`, { headers });
  check(res, {
    'status is 200': (r) => r.status === 200,
    'response time < 500ms': (r) => r.timings.duration < 500,
  });
  
  sleep(1);
}
```

### Run Load Test
```bash
k6 run load-test.js
```

**Expected Results:**
- 100 users: 95%+ success rate, avg response < 200ms
- 500 users: 90%+ success rate, avg response < 500ms

---

## 🛠️ Troubleshooting

### Issue: Migration fails
```bash
# Check migration status
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback --step=1

# Re-run migration
php artisan migrate
```

### Issue: Cache not working
```bash
# Check cache driver
php artisan tinker
>>> config('cache.default');
# Should return: "file" or "redis"

# Clear and rebuild cache
php artisan cache:clear
php artisan config:cache
```

### Issue: Middleware not working
```bash
# Check registered middleware
php artisan route:list

# Clear route cache
php artisan route:clear

# Re-cache routes
php artisan route:cache
```

### Issue: Slow queries still happening
```sql
-- Check if indexes exist
SHOW INDEX FROM attendances WHERE Key_name LIKE 'idx_%';

-- If no indexes, manually create them
CREATE INDEX idx_attendances_date ON attendances(date);
CREATE INDEX idx_attendances_status ON attendances(status);
CREATE INDEX idx_attendances_employee_date ON attendances(employee_id, date);
```

---

## 📈 Monitoring

### Enable Query Logging (Development Only)
```php
// In AppServiceProvider.php boot() method
if (config('app.debug')) {
    \DB::listen(function ($query) {
        if ($query->time > 100) { // Log queries > 100ms
            \Log::warning('Slow Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time . 'ms'
            ]);
        }
    });
}
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

---

## 🎓 Next Steps

1. ✅ Apply all optimizations (Done!)
2. 📊 Run performance tests
3. 🔥 Run load tests with 100, 500, 2000 users
4. 📝 Document results untuk presentasi
5. 🚀 Deploy to production (cPanel)
6. 📈 Monitor performance in production
7. 🔄 Iterate and improve

---

## 📞 Support

Jika ada masalah:
1. Check `storage/logs/laravel.log`
2. Run `php artisan optimize:clear`
3. Verify database indexes dengan `SHOW INDEX`
4. Test cache dengan `php artisan tinker`

---

**Estimated Time**: 5-10 minutes
**Difficulty**: Easy
**Impact**: High (10x performance improvement)

Good luck! 🚀
