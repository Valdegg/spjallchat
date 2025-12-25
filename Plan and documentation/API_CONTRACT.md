# API Contract

The formal contract between frontend and backend. This defines all WebSocket events and their payloads.

---

## Connection

- **Protocol:** WebSocket
- **Authentication:** Token-based (send `auth` event immediately after connecting)
- **Message format:** JSON

---

## Message Envelope

All messages use this structure:

```json
{
  "type": "event_name",
  "payload": { ... }
}
```

---

## Data Types

### User

```json
{
  "id": 1,
  "nickname": "valdimar"
}
```

### Conversation

```json
{
  "id": 1,
  "type": "lobby",
  "members": []
}
```

```json
{
  "id": 2,
  "type": "dm",
  "members": [
    { "id": 1, "nickname": "valdimar" },
    { "id": 2, "nickname": "friend" }
  ]
}
```

```json
{
  "id": 3,
  "type": "group",
  "members": [
    { "id": 1, "nickname": "valdimar" },
    { "id": 2, "nickname": "friend" },
    { "id": 3, "nickname": "another" }
  ]
}
```

### Message

```json
{
  "id": 123,
  "conversation_id": 1,
  "user_id": 1,
  "content": "Hello everyone!",
  "created_at": 1703520000
}
```

---

## Client → Server Events

### `auth`

Authenticate the WebSocket connection. Must be sent first.

**Payload:**
```json
{
  "token": "abc123..."
}
```

**Response:** `auth_ok` or `auth_error`

---

### `send_message`

Send a message to a conversation.

**Payload:**
```json
{
  "conversation_id": 1,
  "content": "Hello!"
}
```

**Response:** Message is broadcast to all participants via `message` event.

**Errors:**
- Not a member of conversation
- Empty content
- Content too long (max 4000 chars)

---

### `create_dm`

Start a direct message conversation with another user.

**Payload:**
```json
{
  "user_id": 2
}
```

**Response:** `conversation_created` event (to both participants)

**Notes:**
- If DM already exists between the two users, returns existing conversation
- Cannot create DM with yourself

---

### `create_group`

Create a group conversation with selected users.

**Payload:**
```json
{
  "user_ids": [2, 3, 4]
}
```

**Response:** `conversation_created` event (to all participants)

**Notes:**
- Creator is automatically included
- Minimum 2 other users required (3 total including creator)

---

### `load_history`

Request message history for a conversation.

**Payload:**
```json
{
  "conversation_id": 1,
  "before": 1703520000,
  "limit": 50
}
```

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `conversation_id` | Yes | — | Which conversation |
| `before` | No | now | Load messages before this timestamp |
| `limit` | No | 50 | Max messages to return (max 100) |

**Response:** `history` event

---

### `create_invite`

Generate a new invite link.

**Payload:**
```json
{}
```

**Response:** `invite_created` event

---

### `ping`

Heartbeat to keep connection alive.

**Payload:**
```json
{}
```

**Response:** `pong` event

---

## Server → Client Events

### `auth_ok`

Authentication successful. Contains initial state.

**Payload:**
```json
{
  "user": {
    "id": 1,
    "nickname": "valdimar"
  },
  "conversations": [
    {
      "id": 1,
      "type": "lobby",
      "members": []
    },
    {
      "id": 2,
      "type": "dm",
      "members": [
        { "id": 2, "nickname": "friend" }
      ]
    }
  ],
  "online_users": [
    { "id": 2, "nickname": "friend" },
    { "id": 3, "nickname": "another" }
  ]
}
```

**Notes:**
- `conversations` includes all conversations the user is part of
- For DMs, `members` excludes the current user (only shows the other person)
- Lobby `members` is always empty (everyone is implicitly a member)

---

### `auth_error`

Authentication failed.

**Payload:**
```json
{
  "message": "Invalid token"
}
```

---

### `message`

New message in a conversation.

**Payload:**
```json
{
  "id": 123,
  "conversation_id": 1,
  "user_id": 1,
  "content": "Hello!",
  "created_at": 1703520000
}
```

**Notes:**
- Sent to all online participants of the conversation
- Includes the sender (for confirmation)

---

### `user_online`

A user came online.

**Payload:**
```json
{
  "user": {
    "id": 2,
    "nickname": "friend"
  }
}
```

---

### `user_offline`

A user went offline.

**Payload:**
```json
{
  "user_id": 2
}
```

---

### `conversation_created`

A new DM or group was created (and you're a participant).

**Payload:**
```json
{
  "conversation": {
    "id": 5,
    "type": "group",
    "members": [
      { "id": 1, "nickname": "valdimar" },
      { "id": 2, "nickname": "friend" },
      { "id": 3, "nickname": "another" }
    ]
  }
}
```

---

### `history`

Response to `load_history` request.

**Payload:**
```json
{
  "conversation_id": 1,
  "messages": [
    {
      "id": 100,
      "conversation_id": 1,
      "user_id": 2,
      "content": "Earlier message",
      "created_at": 1703510000
    },
    {
      "id": 101,
      "conversation_id": 1,
      "user_id": 1,
      "content": "Later message",
      "created_at": 1703515000
    }
  ],
  "has_more": true
}
```

**Notes:**
- Messages are in chronological order (oldest first)
- `has_more` indicates if there are more messages to load

---

### `invite_created`

Response to `create_invite` request.

**Payload:**
```json
{
  "code": "abc123",
  "url": "/join/abc123"
}
```

---

### `error`

Generic error response.

**Payload:**
```json
{
  "message": "Not a member of this conversation",
  "code": "NOT_MEMBER"
}
```

### Error Codes

| Code | Meaning |
|------|---------|
| `NOT_AUTHENTICATED` | Must send `auth` first |
| `NOT_MEMBER` | Not a member of the conversation |
| `INVALID_CONVERSATION` | Conversation does not exist |
| `INVALID_USER` | User does not exist |
| `EMPTY_CONTENT` | Message content is empty |
| `CONTENT_TOO_LONG` | Message exceeds 4000 characters |
| `INVALID_REQUEST` | Malformed request |

---

### `pong`

Response to `ping`.

**Payload:**
```json
{}
```

---

## HTTP Endpoints

These are optional endpoints for the invite/join flow (before WebSocket connection).

### `GET /join/:code`

Landing page for invite links. Shows nickname input form.

### `POST /join/:code`

Submit nickname to claim invite and create account.

**Request body:**
```json
{
  "nickname": "newuser"
}
```

**Response (success):**
```json
{
  "token": "abc123...",
  "user": {
    "id": 5,
    "nickname": "newuser"
  }
}
```

**Response (error):**
```json
{
  "error": "Nickname already taken"
}
```

```json
{
  "error": "Invalid invite code"
}
```

```json
{
  "error": "Invite already used"
}
```

---

## Sequence Diagrams

### New User Joining

```
User                    Server
 │                         │
 │  GET /join/abc123       │
 │────────────────────────>│
 │                         │
 │  <Join page HTML>       │
 │<────────────────────────│
 │                         │
 │  POST /join/abc123      │
 │  { nickname: "new" }    │
 │────────────────────────>│
 │                         │
 │  { token: "xyz..." }    │
 │<────────────────────────│
 │                         │
 │  [Store token in        │
 │   localStorage]         │
 │                         │
 │  WebSocket connect      │
 │────────────────────────>│
 │                         │
 │  { type: "auth",        │
 │    payload: { token } } │
 │────────────────────────>│
 │                         │
 │  { type: "auth_ok", ... }│
 │<────────────────────────│
```

### Sending a Message

```
User A                  Server                  User B
  │                        │                       │
  │  send_message          │                       │
  │  { conv: 1, ... }      │                       │
  │───────────────────────>│                       │
  │                        │                       │
  │  message               │  message              │
  │  { id: 123, ... }      │  { id: 123, ... }     │
  │<───────────────────────│──────────────────────>│
```

