# Spjall Quickstart

Get the chat running in 2 minutes.

---

## Prerequisites

- PHP 8.0+
- SQLite3

---

## 1. Setup Database

```bash
cd /path/to/spjallchat

# Create data directory + initialize database + create seed invite
chmod +x setup.sh
./setup.sh
```

**Output:**
```
✓ Created data directory
✓ Database initialized with schema
✓ Created seed invite: WELCOME1
```

---

## 2. Start Servers

**Terminal 1 — HTTP Server:**
```bash
php -S localhost:8080 -t public
```

**Terminal 2 — WebSocket Server:**
```bash
php websocket_server.php
```

---

## 3. Create Your First User

Open browser: **http://localhost:8080/join/WELCOME1**

1. Choose a username
2. Choose a password
3. Click "Create Account"
4. You're in!

---

## 4. Log In (Returning User)

Open browser: **http://localhost:8080/login**

1. Enter username
2. Enter password
3. Click "Log In"

---

## File Overview

```
spjallchat/
├── setup.sh               # Run this first
├── websocket_server.php   # WebSocket server
├── schema.sql             # Database schema
│
├── src/
│   ├── SpjallServer.php   # Main server class
│   ├── Connection.php     # WebSocket connection wrapper
│   ├── Database.php       # SQLite wrapper
│   ├── Auth.php           # Token validation
│   ├── UserService.php    # User management + password auth
│   ├── InviteService.php  # Invite management
│   └── Services/
│       └── PresenceService.php  # Online user tracking
│
├── public/
│   ├── index.php          # Router
│   ├── index.html         # Chat UI
│   ├── join.php           # Registration page
│   ├── login.php          # Login page
│   ├── css/style.css      # Styles
│   └── js/app.js          # Frontend logic
│
└── data/
    └── spjallchat.db      # SQLite database
```

---

## How Auth Works

1. **Registration**: Invite code → username + password → account created
2. **Login**: Username + password → authenticated
3. **Session**: Token stored in localStorage, used for WebSocket auth
4. **WebSocket**: Client sends `auth` event with token on connect

---

## WebSocket Events

### Client → Server

| Event | Payload |
|-------|---------|
| `auth` | `{ token: "..." }` |
| `ping` | `{}` |
| `send_message` | `{ conversation_id: 1, content: "Hello" }` |
| `create_dm` | `{ user_id: 2 }` |
| `create_group` | `{ user_ids: [2, 3] }` |
| `load_history` | `{ conversation_id: 1, limit: 50 }` |
| `create_invite` | `{}` |

### Server → Client

| Event | When |
|-------|------|
| `auth_ok` | Auth successful |
| `auth_error` | Auth failed |
| `pong` | Response to ping |
| `message` | New message |
| `user_online` | User came online |
| `user_offline` | User went offline |
| `conversation_created` | New DM/group |
| `history` | Message history |
| `invite_created` | New invite generated |
| `error` | Something went wrong |

---

## Troubleshooting

**"Invalid username or password"**
→ Check your credentials. Usernames are case-insensitive.

**"Invite already used"**
→ Invites are single-use. Generate a new one from the chat.

**"Database not found"**
→ Run `./setup.sh`

**WebSocket won't connect**
→ Make sure `php websocket_server.php` is running on port 8081

**PHP not found**
→ Install PHP: `brew install php` (macOS) or `apt install php` (Linux)
