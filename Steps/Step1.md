# Step 1: Data Model & API Contract ✅ DONE

This is the foundation phase. We define **what data exists** and **how clients talk to the server**.

---

## Overview

| Deliverable | Purpose |
|-------------|---------|
| `schema.sql` | Database tables for users, conversations, messages, invites |
| `API_CONTRACT.md` | WebSocket message protocol (events, payloads, errors) |

These two have **no dependencies on each other** and unblock all subsequent work.

---

## 1A: Database Schema

### Tables to Create

#### `users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INTEGER PRIMARY KEY | Auto-increment |
| `nickname` | TEXT UNIQUE NOT NULL | Display name, must be unique |
| `token` | TEXT UNIQUE NOT NULL | Auth token (stored in localStorage) |
| `created_at` | INTEGER NOT NULL | Unix timestamp |

#### `invites`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INTEGER PRIMARY KEY | Auto-increment |
| `code` | TEXT UNIQUE NOT NULL | Random string for invite URL |
| `created_by` | INTEGER | FK → users.id (nullable for seed invites) |
| `used_by` | INTEGER | FK → users.id (null until consumed) |
| `created_at` | INTEGER NOT NULL | Unix timestamp |
| `used_at` | INTEGER | Unix timestamp when consumed |

#### `conversations`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INTEGER PRIMARY KEY | Auto-increment |
| `type` | TEXT NOT NULL | `'lobby'`, `'dm'`, `'group'` |
| `created_at` | INTEGER NOT NULL | Unix timestamp |

#### `conversation_members`
| Column | Type | Notes |
|--------|------|-------|
| `conversation_id` | INTEGER NOT NULL | FK → conversations.id |
| `user_id` | INTEGER NOT NULL | FK → users.id |
| `joined_at` | INTEGER NOT NULL | Unix timestamp |
| PRIMARY KEY | | (`conversation_id`, `user_id`) |

#### `messages`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INTEGER PRIMARY KEY | Auto-increment |
| `conversation_id` | INTEGER NOT NULL | FK → conversations.id |
| `user_id` | INTEGER NOT NULL | FK → users.id (sender) |
| `content` | TEXT NOT NULL | Message text (plain text only) |
| `created_at` | INTEGER NOT NULL | Unix timestamp |

### Indexes
- `messages`: INDEX on (`conversation_id`, `created_at`) for history queries
- `invites`: INDEX on `code` for lookup

---

## 1B: API Contract (WebSocket Protocol)

### Message Format

All messages are JSON with this structure:

```json
{
  "type": "event_name",
  "payload": { ... },
  "id": "optional-request-id"
}
```

### Client → Server Events

| Event | Payload | Description |
|-------|---------|-------------|
| `auth` | `{ token: string }` | Authenticate connection |
| `send_message` | `{ conversation_id: number, content: string }` | Send a message |
| `create_dm` | `{ user_id: number }` | Start DM with a user |
| `create_group` | `{ user_ids: number[] }` | Create group chat |
| `load_history` | `{ conversation_id: number, before?: number, limit?: number }` | Load messages |
| `create_invite` | `{}` | Generate new invite link |
| `ping` | `{}` | Heartbeat |

### Server → Client Events

| Event | Payload | Description |
|-------|---------|-------------|
| `auth_ok` | `{ user: User, conversations: Conversation[], online_users: User[] }` | Auth successful |
| `auth_error` | `{ message: string }` | Auth failed |
| `message` | `{ id, conversation_id, user_id, content, created_at }` | New message |
| `user_online` | `{ user: User }` | User came online |
| `user_offline` | `{ user_id: number }` | User went offline |
| `conversation_created` | `{ conversation: Conversation }` | New DM/group created |
| `history` | `{ conversation_id, messages: Message[], has_more: boolean }` | History response |
| `invite_created` | `{ code: string, url: string }` | Invite link generated |
| `error` | `{ message: string, code?: string }` | Generic error |
| `pong` | `{}` | Heartbeat response |

### Data Types

```typescript
interface User {
  id: number;
  nickname: string;
}

interface Conversation {
  id: number;
  type: 'lobby' | 'dm' | 'group';
  members: User[];  // For DM/group; empty for lobby
}

interface Message {
  id: number;
  conversation_id: number;
  user_id: number;
  content: string;
  created_at: number;  // Unix timestamp
}
```

---

## Deliverables Checklist

- [ ] Create `schema.sql` with all tables and indexes
- [ ] Create `API_CONTRACT.md` with full protocol specification
- [ ] Review with friend to confirm compatibility with his foundation

---

## Next Steps (After Step 1)

Once schema and contract are defined:

1. **Step 2: Identity & Invites** — Implement user creation, token auth, invite flow
2. **Step 3: Presence** — Online/offline tracking and broadcast
3. **Step 4: Messaging Core** — Send, receive, persist messages
4. **Step 5: Conversations** — Lobby, DMs, Groups
5. **Step 6: Frontend** — Build the UI

---

## Dependencies Satisfied

After Step 1 completes, we unblock:
- ✅ Identity & Invite Links
- ✅ Data Model & Persistence
- ✅ All downstream features

