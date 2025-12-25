# Segment Tasks

Concrete implementation tasks mapped to each segment.

---

## Segment 1: Core Infrastructure & Runtime ✅ DONE

- [x] WebSocket server running and accepting connections — `websocket_server.php`
- [x] SQLite database configured
- [x] Connection persistence per session

---

## Segment 2: Identity, Sessions & Invites ✅ DONE

### Invite System
- [x] **2.1** Generate invite codes (random string, stored in DB) — `InviteService::create()`
- [x] **2.2** Create shareable invite URL (`/join/{code}`) — `InviteService::getUrl()`
- [x] **2.3** Validate invite code on use — `InviteService::validate()`
- [x] **2.4** Mark invite as consumed (single-use) — `InviteService::consume()`
- [x] **2.5** Allow any authenticated user to generate invites — `create_invite` event

### User Creation
- [x] **2.6** Registration form with username + password — `public/join.php`
- [x] **2.7** Validate username (length, characters, uniqueness) — `UserService::validateNickname()`
- [x] **2.8** Validate password (minimum length) — `UserService::validatePassword()`
- [x] **2.9** Hash password securely — `password_hash()`
- [x] **2.10** Create user record in DB — `UserService::create()`
- [x] **2.11** Generate auth token for WebSocket — `Auth::generateToken()`

### Login
- [x] **2.12** Login form with username + password — `public/login.php`
- [x] **2.13** Authenticate user — `UserService::authenticate()`
- [x] **2.14** Return token on successful login

### Session Management
- [x] **2.15** Validate token on WebSocket connect — `SpjallServer::handleAuth()`
- [x] **2.16** Handle reconnection (same user, new WS connection) — `SpjallServer`

---

## Segment 3: Presence & Connection Management ✅ DONE

### Connection Tracking
- [x] **3.1** Track connected users (user_id → connection mapping) — `PresenceService`
- [x] **3.2** Handle user connect event — `SpjallServer::onConnect()`
- [x] **3.3** Handle user disconnect event (clean + unexpected) — `SpjallServer::onDisconnect()`
- [x] **3.4** Support multiple connections per user — `PresenceService` tracks array of connections

### Online Status
- [x] **3.5** Maintain online users list in memory — `PresenceService::$onlineUsers`
- [x] **3.6** Broadcast `user_online` event when user connects — `SpjallServer::handleAuth()`
- [x] **3.7** Broadcast `user_offline` event when user disconnects — `SpjallServer::onDisconnect()`
- [x] **3.8** Send full online users list on new connection — in `auth_ok` payload

### Health
- [x] **3.9** Implement heartbeat/ping-pong (detect stale connections) — `ping`/`pong` events

---

## Segment 4: Conversation Model ✅ DONE

### Lobby
- [x] **4.1** Define lobby as a special conversation (id: 1) — seeded in `schema.sql`
- [x] **4.2** All users are implicitly members of lobby — `canAccessConversation()` allows all
- [x] **4.3** Lobby cannot be deleted or modified — no delete/modify endpoints

### Direct Messages
- [x] **4.4** Create DM conversation between two users — `handleCreateDm()`
- [x] **4.5** Prevent duplicate DM conversations (same pair) — `findExistingDm()`
- [x] **4.6** DM identified by unique conversation ID — standard ID system

### Group Chats
- [x] **4.7** Create group conversation with N participants — `handleCreateGroup()`
- [x] **4.8** Store group membership in DB — `conversation_members` table
- [x] **4.9** Groups have no name (just participant list) — no name column
- [x] **4.10** Creator is not special (no admin concept) — no owner/role columns

### Conversation Queries
- [x] **4.11** List all conversations for a user — `getUserConversations()`
- [x] **4.12** Get conversation by ID — `canAccessConversation()`
- [x] **4.13** Get participants for a conversation — returned in conversation object

---

## Segment 5: Messaging (Realtime + Persistence) ✅ DONE

### Sending Messages
- [x] **5.1** Receive message from client via WebSocket — `send_message` event
- [x] **5.2** Validate message (non-empty, max length) — in `handleSendMessage()`
- [x] **5.3** Verify sender is member of target conversation — `canAccessConversation()`
- [x] **5.4** Assign message ID and timestamp (server-side) — `time()` + `lastInsertId()`

### Broadcasting
- [x] **5.5** Send message to all online participants of conversation — `broadcastToConversation()`
- [x] **5.6** Include sender info, timestamp, conversation ID — full message object

### Persistence
- [x] **5.7** Store message in database — INSERT in `handleSendMessage()`
- [x] **5.8** Messages are immutable (no edit, no delete) — no edit/delete handlers
- [x] **5.9** Index messages by conversation + timestamp — `idx_messages_conversation_time`

### History
- [x] **5.10** Load message history for conversation (paginated) — `handleLoadHistory()`
- [x] **5.11** Return messages in chronological order — reversed after DESC query
- [x] **5.12** Support "load more" (cursor-based) — `before` parameter + `has_more`

---

## Segment 6: Client API Surface ✅ DONE

### WebSocket Events (Client → Server)
- [x] **6.1** `auth` — authenticate connection with token
- [x] **6.2** `send_message` — send a message to a conversation
- [x] **6.3** `create_dm` — start a DM with a user
- [x] **6.4** `create_group` — create a group with selected users
- [x] **6.5** `load_history` — request message history
- [x] **6.6** `ping` — heartbeat
- [x] **6.7** `create_invite` — generate invite link

### WebSocket Events (Server → Client)
- [x] **6.8** `auth_ok` / `auth_error` — auth result
- [x] **6.9** `message` — new message in a conversation
- [x] **6.10** `user_online` / `user_offline` — presence updates
- [x] **6.11** `conversation_created` — new DM/group created
- [x] **6.12** `history` — message history response
- [x] **6.13** `error` — generic error response
- [x] **6.14** `pong` — heartbeat response
- [x] **6.15** `invite_created` — invite link generated

### HTTP Endpoints
- [x] **6.16** `GET /join/{code}` — registration form
- [x] **6.17** `POST /join/{code}` — create account
- [x] **6.18** `GET /login` — login form
- [x] **6.19** `POST /login` — authenticate user

---

## Segment 7: Frontend Integration Layer ✅ DONE

### Initial Load
- [x] **7.1** Check for token in localStorage
- [x] **7.2** If no token, show login/invite screen
- [x] **7.3** If token, connect WebSocket and authenticate
- [x] **7.4** On auth success, load conversations + online users

### UI Components
- [x] **7.5** Sidebar: Lobby link (always visible)
- [x] **7.6** Sidebar: DM list
- [x] **7.7** Sidebar: Group list
- [x] **7.8** Sidebar: Online users list
- [x] **7.9** Sidebar: Offline users list
- [x] **7.10** Chat view: Message list
- [x] **7.11** Chat view: Message input
- [x] **7.12** Chat view: Load history on scroll up

### Real-time Updates
- [x] **7.13** Append new messages to active conversation
- [x] **7.14** Update online/offline indicators
- [x] **7.15** Add new conversations to sidebar when created
- [x] **7.16** Handle reconnection gracefully

### Modals/Flows
- [x] **7.17** Start DM by clicking user
- [x] **7.18** Generate invite link UI

---

## Segment 8: Persistence & Data Integrity ✅ DONE

- [x] **8.1** Schema for users table (with password_hash)
- [x] **8.2** Schema for invites table
- [x] **8.3** Schema for conversations table
- [x] **8.4** Schema for conversation_members table
- [x] **8.5** Schema for messages table
- [x] **8.6** Indexes on messages (conversation_id, created_at)

---

## Summary

| Segment | Status |
|---------|--------|
| 1. Infrastructure | ✅ Done |
| 2. Identity & Invites | ✅ Done |
| 3. Presence | ✅ Done |
| 4. Conversations | ✅ Done |
| 5. Messaging | ✅ Done |
| 6. API Surface | ✅ Done |
| 7. Frontend | ✅ Done |
| 8. Persistence | ✅ Done |

**All segments complete! 🎉**
