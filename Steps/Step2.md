# Step 2: Identity, Sessions & Invites ✅ DONE

This step implements user identity — how people register and log in.

---

## Overview

| Component | Purpose |
|-----------|---------|
| Invite system | Generate and validate invite codes |
| User creation | Register new users with username + password |
| Login | Authenticate returning users |
| Session/auth | Token-based authentication for WebSocket |

---

## Authentication Flow

### New User (Registration)
1. Get invite code from friend
2. Visit `/join/{code}`
3. Enter username + password
4. Account created, token stored, redirected to app

### Returning User (Login)
1. Visit `/login`
2. Enter username + password
3. Token returned, stored, redirected to app

### WebSocket Auth
1. Frontend has token in localStorage
2. Connects to WebSocket server
3. Sends `auth` event with token
4. Server validates token, returns `auth_ok` with initial state

---

## Files

```
/spjallchat/
├── src/
│   ├── Database.php         # SQLite connection wrapper
│   ├── Auth.php             # Token generation & validation
│   ├── InviteService.php    # Invite create/validate/consume
│   ├── UserService.php      # User create/authenticate/lookup
│   └── SpjallServer.php     # WebSocket auth handling
├── public/
│   ├── index.php            # Router + main entry
│   ├── join.php             # Registration form
│   └── login.php            # Login form
└── schema.sql               # Database schema
```

---

## Database Schema

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nickname TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    token TEXT UNIQUE NOT NULL,
    created_at INTEGER NOT NULL
);
```

---

## UserService Methods

```php
class UserService {
    // Registration
    public static function create(string $nickname, string $password): array
    public static function validateNickname(string $nickname): ?string
    public static function validatePassword(string $password): ?string
    
    // Login
    public static function authenticate(string $nickname, string $password): ?array
    
    // Lookup
    public static function getById(int $id): ?array
    public static function getByToken(string $token): ?array
    public static function getByNickname(string $nickname): ?array
}
```

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/join/{code}` | Show registration form |
| POST | `/join/{code}` | Submit username + password, get token |
| GET | `/login` | Show login form |
| POST | `/login` | Submit username + password, get token |

---

## WebSocket Events

| Event | Direction | Purpose |
|-------|-----------|---------|
| `auth` | Client → Server | Authenticate with token |
| `auth_ok` | Server → Client | Auth successful + initial state |
| `auth_error` | Server → Client | Auth failed |
| `create_invite` | Client → Server | Generate new invite |
| `invite_created` | Server → Client | Return invite code/URL |

---

## Deliverables ✅

- [x] `src/Database.php` — SQLite connection
- [x] `src/Auth.php` — Token generation/validation
- [x] `src/InviteService.php` — Invite CRUD
- [x] `src/UserService.php` — User CRUD + password auth
- [x] `public/join.php` — Registration endpoint
- [x] `public/login.php` — Login endpoint
- [x] `src/SpjallServer.php` — WebSocket auth handling
- [x] Seed invite in database (`setup.sh`)
