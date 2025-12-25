# Step 3: Presence & Connection Management ✅ DONE

Tracks who is online and broadcasts presence changes.

---

## Overview

| Component | Purpose |
|-----------|---------|
| PresenceService | In-memory tracking of online users |
| SpjallServer | Connection lifecycle + presence broadcasts |

---

## Dependencies

- ✅ Step 1: Schema exists
- ✅ Step 2: Identity & Auth working

---

## Implementation

### Created in Refactor

| File | What It Does |
|------|--------------|
| `src/Services/PresenceService.php` | In-memory presence tracking |
| `src/SpjallServer.php` | Connection lifecycle + broadcasts |
| `src/Connection.php` | Connection wrapper with user association |

---

## Task Status

### Connection Tracking ✅ DONE

- [x] **3.1** Track connected users (user_id → connection mapping)
  - `PresenceService::$userConnections`
  - `PresenceService::$connectionUsers`

- [x] **3.2** Handle user connect event
  - `SpjallServer::onConnect()`

- [x] **3.3** Handle user disconnect event (clean + unexpected)
  - `SpjallServer::onDisconnect()`
  - Broadcasts `user_offline` to all

- [x] **3.4** Support multiple connections per user
  - `PresenceService::$userConnections[$userId]` is an array of connection IDs
  - User only goes "offline" when ALL connections close

### Online Status ✅ DONE

- [x] **3.5** Maintain online users list in memory
  - `PresenceService::$onlineUsers`

- [x] **3.6** Broadcast `user_online` event when user connects
  - `SpjallServer::handleAuth()` broadcasts to all except self

- [x] **3.7** Broadcast `user_offline` event when user disconnects
  - `SpjallServer::onDisconnect()` broadcasts when last connection closes

- [x] **3.8** Send full online users list on new connection
  - Included in `auth_ok` payload: `online_users: [...]`

### Health ✅ DONE

- [x] **3.9** Implement heartbeat/ping-pong (detect stale connections)
  - `ping` → `pong` event handling in `SpjallServer`
  - `Connection::touch()` updates last activity timestamp

- [ ] **3.10** Timeout and cleanup dead connections
  - **Deferred** — requires periodic timer in friend's WebSocket server

---

## Key Methods

### PresenceService

```php
// Mark user online after auth
$isNewlyOnline = $presence->userConnected($userId, $user, $connectionId);

// Handle disconnect
$wentOffline = $presence->connectionDisconnected($connectionId);

// Get online users for auth_ok
$onlineUsers = $presence->getOnlineUsers();

// Check if specific user is online
$isOnline = $presence->isOnline($userId);

// Get all connections for a user (for sending messages)
$connIds = $presence->getConnectionsForUser($userId);
```

### SpjallServer Flow

```
onConnect($connId, $socket)
    └── Creates Connection object, stores in $connections

handleAuth($conn, $payload)
    ├── Validates token
    ├── $conn->setUser($user)
    ├── $presence->userConnected(...)
    ├── If newly online → broadcast user_online
    └── Send auth_ok with online_users

onDisconnect($connId)
    ├── $presence->connectionDisconnected(...)
    ├── If went offline → broadcast user_offline
    └── Remove from $connections
```

---

## Tested ✅

From `php server.php` simulation:

```
[User1] → auth
[User1] ← auth_ok: {"user":...,"online_users":[...]}

[Server] Connection closed: test_conn_1
[User1] ← user_offline: {"user_id":1}

--- Server Stats ---
Connections: 1 → 0
Online users: 1 → 0
```

---

## Remaining Work

| Task | Status | Notes |
|------|--------|-------|
| 3.10 Timeout cleanup | 🔲 Deferred | Needs timer in WebSocket server |

---

## Summary

**9 of 10 tasks complete.** Presence is fully functional:
- ✅ In-memory online user tracking
- ✅ Multi-connection support per user
- ✅ `user_online` / `user_offline` broadcasts
- ✅ Ping/pong heartbeat

