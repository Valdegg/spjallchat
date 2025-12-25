# Step 4: Conversation Model ✅ DONE

Defines how conversations (Lobby, DMs, Groups) work.

---

## Overview

| Type | Description |
|------|-------------|
| Lobby | Global chat, all users can see (ID = 1) |
| DM | 1-on-1 private conversation |
| Group | Ad-hoc group with selected participants |

---

## Dependencies

- ✅ Step 1: Schema exists (`conversations`, `conversation_members`)
- ✅ Step 2: Identity working
- ✅ Step 3: Presence working

---

## Implementation

All conversation logic lives in `SpjallServer.php`:

| Method | Purpose |
|--------|---------|
| `handleCreateDm()` | Create DM between two users |
| `handleCreateGroup()` | Create group with N users |
| `getUserConversations()` | List all conversations for a user |
| `canAccessConversation()` | Check membership |
| `findExistingDm()` | Prevent duplicate DMs |

---

## Task Status

### Lobby ✅ DONE

- [x] **4.1** Define lobby as special conversation (id = 1)
  - `schema.sql` seeds lobby with ID 1
  - `type = 'lobby'`

- [x] **4.2** All users are implicitly members of lobby
  - `canAccessConversation()` returns true for lobby
  - No membership check needed

- [x] **4.3** Lobby cannot be deleted or modified
  - No delete/modify endpoints exist

### Direct Messages ✅ DONE

- [x] **4.4** Create DM conversation between two users
  - `handleCreateDm()` creates DM + adds both as members

- [x] **4.5** Prevent duplicate DM conversations (same pair)
  - `findExistingDm()` checks for existing DM
  - Returns existing conversation if found

- [x] **4.6** DM identified by unique conversation ID
  - Uses standard `conversations.id`
  - Members stored in `conversation_members`

### Group Chats ✅ DONE

- [x] **4.7** Create group conversation with N participants
  - `handleCreateGroup()` with `user_ids` array

- [x] **4.8** Store group membership in DB
  - Inserts all members into `conversation_members`

- [x] **4.9** Groups have no name (just participant list)
  - No `name` column in schema

- [x] **4.10** Creator is not special (no admin concept)
  - No `owner` or `role` columns

### Conversation Queries ✅ DONE

- [x] **4.11** List all conversations for a user
  - `getUserConversations()` returns lobby + DMs + groups

- [x] **4.12** Get conversation by ID
  - `canAccessConversation()` validates access

- [x] **4.13** Get participants for a conversation
  - Returned in `conversation.members` array

---

## Key Flows

### Create DM

```
Client: { type: "create_dm", payload: { user_id: 2 } }

Server:
  1. Check target user exists
  2. Check for existing DM → return if exists
  3. Create conversation (type: 'dm')
  4. Add both users to conversation_members
  5. Send conversation_created to both users
```

### Create Group

```
Client: { type: "create_group", payload: { user_ids: [2, 3, 4] } }

Server:
  1. Validate all users exist
  2. Add creator to list
  3. Create conversation (type: 'group')
  4. Add all users to conversation_members
  5. Send conversation_created to all participants
```

---

## Tested ✅

DM and Group creation work via `SpjallServer`. Need multi-user test to verify broadcasts.

---

## Summary

**13 of 13 tasks complete.**
- ✅ Lobby always accessible
- ✅ DM creation with duplicate prevention
- ✅ Group creation with N participants
- ✅ No ownership/roles (equality)

