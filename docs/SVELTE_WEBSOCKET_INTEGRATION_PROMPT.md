# Svelte WebSocket Real-Time Location Tracking Integration

## Objective
Integrate Laravel WebSocket location tracking into your Svelte application to display real-time employee and vehicle locations on a map with live updates.

---

## What Svelte Should Do

### 1. **Setup WebSocket Connection**
- Install `laravel-echo` and `pusher-js` packages
- Configure Echo client with your Laravel backend WebSocket server
- Initialize once on app mount and maintain persistent connection

### 2. **Subscribe to Location Channels**
- Subscribe to relevant location channels based on user role and tenant
- Channel pattern: `location.{trackableType}.{trackableId}`
  - Example: `location.employee.5` (employee with ID 5)
  - Example: `location.vehicle.12` (vehicle with ID 12)
- Users can only subscribe to locations they have permission to view

### 3. **Listen to LocationUpdated Events**
- Listen for `LocationUpdated` event on subscribed channels
- Payload received:
```json
{
  "latitude": 37.7749,
  "longitude": -122.4194,
  "speed": 45.5,
  "accuracy": 10.2,
  "recorded_at": "2026-04-07T15:30:45Z",
  "entity_name": "John Doe",
  "trackable_type": "employee",
  "trackable_id": 5
}
```

### 4. **Update Map in Real-Time**
- Update marker position for the employee/vehicle on map
- Show speed, accuracy, and last update time
- Animate marker movement (smooth transitions recommended)
- Update UI components (status bar, location details, etc.)

### 5. **Handle Connection States**
- Show connection status indicator (connected/disconnected)
- Handle reconnection logic
- Manage subscription lifecycle (subscribe/unsubscribe)

---

## Implementation Steps

### Step 1: Install Dependencies
```bash
npm install laravel-echo pusher-js
# or
yarn add laravel-echo pusher-js
```

### Step 2: Configure Echo (src/lib/echo.js or similar)
```javascript
import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');

export const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
    encrypted: true,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
    },
    enabledTransports: ['ws', 'wss'],
});

export default echo;
```

### Step 3: Create Location Store (Svelte Store)
```javascript
// src/stores/locationStore.js
import { writable } from 'svelte/store';

export const locations = writable(new Map()); // Map<string, location>
export const activeChannels = writable(new Set()); // Set<string>
export const connectionStatus = writable('disconnected'); // 'connected' | 'disconnected'

export function updateLocation(trackableType, trackableId, locationData) {
    locations.update(map => {
        const key = `${trackableType}.${trackableId}`;
        map.set(key, {
            ...locationData,
            key,
            updatedAt: new Date()
        });
        return map;
    });
}

export function subscribeToLocation(echo, trackableType, trackableId) {
    const channel = `location.${trackableType}.${trackableId}`;
    
    activeChannels.update(set => {
        set.add(channel);
        return set;
    });

    echo.private(channel)
        .listen('LocationUpdated', (event) => {
            updateLocation(trackableType, trackableId, event);
        })
        .error((error) => {
            console.error(`Channel subscription error for ${channel}:`, error);
        });
}

export function unsubscribeFromLocation(echo, trackableType, trackableId) {
    const channel = `location.${trackableType}.${trackableId}`;
    
    activeChannels.update(set => {
        set.delete(channel);
        return set;
    });

    echo.leave(channel);
}
```

### Step 4: Setup Connection Monitoring
```javascript
// src/lib/connectionMonitor.js
import { connectionStatus } from '../stores/locationStore';

export function initConnectionMonitor(echo) {
    echo.connector.socket.on('connected', () => {
        connectionStatus.set('connected');
        console.log('WebSocket connected');
    });

    echo.connector.socket.on('disconnected', () => {
        connectionStatus.set('disconnected');
        console.log('WebSocket disconnected');
    });

    echo.connector.socket.on('error', (error) => {
        console.error('WebSocket error:', error);
        connectionStatus.set('error');
    });
}
```

### Step 5: Use in Components (Example Map Component)
```svelte
<!-- src/routes/tracking/Map.svelte -->
<script>
    import { onMount } from 'svelte';
    import echo from '$lib/echo';
    import { 
        locations, 
        activeChannels, 
        connectionStatus,
        subscribeToLocation,
        unsubscribeFromLocation 
    } from '$stores/locationStore';
    import { initConnectionMonitor } from '$lib/connectionMonitor';
    import MapComponent from '$lib/components/MapComponent.svelte';
    import LocationMarker from '$lib/components/LocationMarker.svelte';

    let mapInstance;
    let userRole = ''; // Get from auth store
    let tenantId = null; // Get from auth store
    let employees = [];
    let vehicles = [];

    onMount(async => {
        // Initialize connection monitoring
        initConnectionMonitor(echo);

        // Fetch employees/vehicles for tenant
        if (userRole === 'admin') {
            const response = await fetch(`/api/admin/${tenantId}/employees`, {
                headers: { Authorization: `Bearer ${authToken}` }
            });
            employees = await response.json();

            // Subscribe to all employee locations
            employees.forEach(emp => {
                subscribeToLocation(echo, 'employee', emp.id);
            });
        }

        // Similar for vehicles...
    });

    function handleMarkerClick(entity) {
        console.log('Clicked entity:', entity);
        // Open detail panel, show speed, accuracy, etc.
    }
</script>

<div class="tracking-container">
    <div class="connection-status" class:connected={$connectionStatus === 'connected'}>
        {#if $connectionStatus === 'connected'}
            <span class="status-indicator connected"></span>
            <span>Live Tracking Active</span>
        {:else}
            <span class="status-indicator disconnected"></span>
            <span>Connecting...</span>
        {/if}
    </div>

    <MapComponent bind:mapInstance>
        {#each Array.from($locations.values()) as location (location.key)}
            <LocationMarker 
                location={location}
                on:click={() => handleMarkerClick(location)}
            />
        {/each}
    </MapComponent>

    <div class="location-list">
        {#each Array.from($locations.values()) as location (location.key)}
            <div class="location-item">
                <h4>{location.entity_name}</h4>
                <p>Speed: {location.speed} km/h</p>
                <p>Accuracy: ±{location.accuracy}m</p>
                <p>Updated: {new Date(location.recorded_at).toLocaleTimeString()}</p>
            </div>
        {/each}
    </div>
</div>

<style>
    .tracking-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    .connection-status {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px;
        background-color: #f0f0f0;
        border-bottom: 1px solid #ddd;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-indicator.connected {
        background-color: #4caf50;
        animation: pulse 2s infinite;
    }

    .status-indicator.disconnected {
        background-color: #f44336;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .location-list {
        width: 300px;
        overflow-y: auto;
        background: white;
        border-left: 1px solid #ddd;
    }

    .location-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }

    .location-item:hover {
        background-color: #f5f5f5;
    }
</style>
```

### Step 6: Environment Configuration (.env)
```
VITE_PUSHER_APP_KEY=your-pusher-key
VITE_PUSHER_HOST=localhost
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=ws
VITE_PUSHER_APP_CLUSTER=mt1
```

---

## Key Features to Implement

### Feature 1: Real-Time Marker Updates
- Subscribe to `location.employee.{id}` channels
- On `LocationUpdated` event, update marker position
- Use smooth animation for marker movement
- Update speed/accuracy badge

### Feature 2: Connection Status Indicator
- Show green indicator when connected
- Show red indicator when disconnected
- Auto-reconnect with exponential backoff
- Display "Connecting..." message

### Feature 3: Location History Toggle
- Allow showing/hiding real-time vs static locations
- Option to show trail/path history
- Clear history on demand

### Feature 4: Efficient Subscriptions
- Only subscribe to locations user has permission to view
- Unsubscribe when navigating away from page
- Handle multiple subscriptions efficiently

### Feature 5: Error Handling
- Handle channel authorization failures
- Gracefully handle connection timeouts
- Show user-friendly error messages
- Prevent memory leaks from dangling subscriptions

---

## Security Considerations

1. **Authentication**: Always pass Bearer token in Echo auth headers
2. **Authorization**: Backend validates user can access channel (handled by LocationChannel.php)
3. **HTTPS/WSS**: Use wss:// in production (secured WebSocket)
4. **Token Refresh**: Implement token refresh before expiration
5. **No Sensitive Data**: Don't log/store auth tokens in localStorage without encryption

---

## Performance Optimization

1. **Debounce Updates**: If receiving many updates, debounce UI re-renders
2. **Virtual Scrolling**: If many locations, use virtual scrolling for list
3. **Marker Clustering**: Group nearby markers to reduce DOM nodes
4. **Unsubscribe Unused**: Always unsubscribe from channels you no longer need
5. **Memory Management**: Clean up location store when clearing old entries

---

## Testing Checklist

- [ ] WebSocket connects on app load
- [ ] Subscriptions created for all visible entities
- [ ] Markers update in real-time when location changes
- [ ] Speed and accuracy display correctly
- [ ] Connection status shows accurate state
- [ ] Disconnection handled gracefully
- [ ] Reconnection works after network loss
- [ ] Unsubscribe removes listener properly
- [ ] No memory leaks with repeated subscriptions/unsubscriptions
- [ ] Authorization failures handled (can't access other tenant's locations)
- [ ] Works on mobile (Flutter) and web (Svelte)

---

## Common Issues & Solutions

### Issue: "Channel authorization failed"
**Solution**: Check that:
- User role is admin or employee
- Bearer token is valid
- LocationChannel.php allows access for this user
- WebSocket server is running

### Issue: "No listeners/updates received"
**Solution**: Verify:
- Channel name matches pattern: `location.{type}.{id}`
- LocationController is dispatching LocationUpdated event
- WebSocket server logs show channel subscription
- Browser console shows no JavaScript errors

### Issue: "Slow/laggy marker movement"
**Solution**:
- Implement CSS animations instead of JavaScript for smoothness
- Debounce location updates if receiving too many
- Use requestAnimationFrame for updates
- Reduce marker animation duration

### Issue: "WebSocket reconnection not working"
**Solution**:
- Check internet connection
- Verify WebSocket server is still running
- Check for firewall blocking WebSocket port (6001)
- Ensure forceTLS is correct for your environment

---

## Code Example: Complete Map Component with Real-Time Updates

```svelte
<script>
    import { onMount, onDestroy } from 'svelte';
    import echo from '$lib/echo';
    import { locations, subscribeToLocation, unsubscribeFromLocation } from '$stores/locationStore';
    import L from 'leaflet'; // or your map library

    let map;
    let markers = new Map(); // trackable_type.trackable_id -> marker
    let selectedEntity = null;

    onMount(async () => {
        // Initialize map
        map = L.map('map').setView([37.7749, -122.4194], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // Subscribe to location updates
        locations.subscribe(locs => {
            locs.forEach((location, key) => {
                const [type, id] = key.split('.');
                
                if (!markers.has(key)) {
                    // Create new marker
                    const marker = L.marker(
                        [location.latitude, location.longitude],
                        { 
                            title: location.entity_name,
                            icon: getIconForType(type)
                        }
                    ).addTo(map);
                    
                    marker.bindPopup(`
                        <strong>${location.entity_name}</strong><br>
                        Speed: ${location.speed} km/h<br>
                        Updated: ${new Date(location.recorded_at).toLocaleString()}
                    `);
                    
                    markers.set(key, marker);
                } else {
                    // Update existing marker
                    const marker = markers.get(key);
                    marker.setLatLng([location.latitude, location.longitude]);
                    marker.setPopupContent(`
                        <strong>${location.entity_name}</strong><br>
                        Speed: ${location.speed} km/h<br>
                        Updated: ${new Date(location.recorded_at).toLocaleString()}
                    `);
                }
            });
        });

        // Fetch initial data
        loadInitialLocations();
    });

    async function loadInitialLocations() {
        const response = await fetch('/api/locations', {
            headers: { Authorization: `Bearer ${authToken}` }
        });
        const data = await response.json();

        data.employees.forEach(emp => {
            subscribeToLocation(echo, 'employee', emp.id);
        });

        data.vehicles.forEach(vehicle => {
            subscribeToLocation(echo, 'vehicle', vehicle.id);
        });
    }

    function getIconForType(type) {
        if (type === 'employee') {
            return L.icon({
                iconUrl: '/icons/employee-marker.png',
                iconSize: [32, 32]
            });
        } else if (type === 'vehicle') {
            return L.icon({
                iconUrl: '/icons/vehicle-marker.png',
                iconSize: [40, 40]
            });
        }
    }

    onDestroy(() => {
        // Cleanup
        if (map) map.remove();
        markers.forEach((marker, key) => {
            const [type, id] = key.split('.');
            unsubscribeFromLocation(echo, type, id);
        });
    });
</script>

<div id="map" style="height: 100%; width: 100%;"></div>
```

---

## Next Steps After Implementation

1. **Test with Postman**: Send location updates via API and verify Svelte receives them
2. **Integration Testing**: Test with multiple users/roles simultaneously
3. **Performance Testing**: Monitor WebSocket payload size and frequency
4. **Mobile Testing**: Test on Flutter app and web app simultaneously
5. **Production Deployment**: Configure WSS (secure WebSocket) for production

---

## Questions for Backend Team

If anything is unclear during implementation, ask:
1. What's the exact channel name format? → `location.{trackableType}.{trackableId}`
2. What event name should I listen for? → `LocationUpdated`
3. What payload will I receive? → See payload example above
4. How often will events be sent? → Only when location changes (1-2x per minute per entity)
5. Can I subscribe to multiple channels? → Yes, as many as needed
6. What happens if I don't have permission? → Backend returns 403, subscription rejected
7. How do I know when connected? → Listen to Echo connection events
8. What should I do on disconnect? → Show "Connecting..." UI and auto-reconnect happens automatically

---

## Useful Resources

- [Laravel Echo Documentation](https://laravel.com/docs/11.x/broadcasting)
- [Pusher Channels Documentation](https://pusher.com/docs/channels/)
- [Svelte Stores Documentation](https://svelte.dev/docs/svelte-store)
- [Leaflet Map Library](https://leafletjs.com/)
- [Laravel Broadcasting Guide](https://laravel.com/docs/11.x/broadcasting)
