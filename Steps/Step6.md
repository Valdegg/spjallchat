# Step 6: Frontend UI

Minimal, retro chat interface. HTML + CSS + vanilla JavaScript only.

---

## Design Philosophy

- **Retro/IRC aesthetic** — monospace fonts, minimal colors, no fluff
- **No frameworks** — pure HTML, CSS, JS
- **Simple layout** — chat on left, sidebar on right
- **Text only** — no images, no markdown, no formatting

---

## Layout

```
┌─────────────────────────────────────────┬──────────────┐
│                                         │   SIDEBAR    │
│              CHAT WINDOW                │              │
│                                         │  ┌────────┐  │
│  ┌─────────────────────────────────┐    │  │ Lobby  │  │
│  │ <nick> message text here        │    │  └────────┘  │
│  │ <nick> another message          │    │              │
│  │ <nick> more chat history        │    │  Online (3)  │
│  │                                 │    │  • alice     │
│  │                                 │    │  • bob       │
│  │                                 │    │  • charlie   │
│  │                                 │    │              │
│  │                                 │    │  Offline (2) │
│  │                                 │    │  ○ dave      │
│  └─────────────────────────────────┘    │  ○ eve       │
│                                         │              │
│  ┌─────────────────────────────────┐    │              │
│  │ Type message... [Enter to send] │    │              │
│  └─────────────────────────────────┘    │              │
└─────────────────────────────────────────┴──────────────┘
```

---

## Files to Create

```
public/
├── index.html      # Main app page
├── css/
│   └── style.css   # All styles
└── js/
    └── app.js      # WebSocket + UI logic
```

---

## Task Breakdown

### 6A: HTML Structure

**File:** `public/index.html`

- [ ] Basic HTML5 structure
- [ ] Chat container (left, ~75% width)
- [ ] Sidebar container (right, ~25% width)
- [ ] Message list area (scrollable)
- [ ] Text input field
- [ ] Sidebar sections: Lobby, Online, Offline

### 6B: CSS Styling

**File:** `public/css/style.css`

- [ ] Retro/terminal aesthetic
- [ ] Monospace font (JetBrains Mono, Consolas, or similar)
- [ ] Dark background, light text
- [ ] Minimal borders and spacing
- [ ] Scrollable message area
- [ ] Fixed input at bottom
- [ ] Sidebar fixed on right

### 6C: JavaScript - WebSocket Connection

**File:** `public/js/app.js`

- [ ] Check for token in localStorage
- [ ] If no token, redirect to join page
- [ ] Connect to WebSocket server
- [ ] Send `auth` event with token
- [ ] Handle `auth_ok` / `auth_error`
- [ ] Handle reconnection on disconnect

### 6D: JavaScript - Message Handling

- [ ] Display messages in chat area
- [ ] Format: `<nickname> message text`
- [ ] Auto-scroll to bottom on new message
- [ ] Handle `message` event from server
- [ ] Send message on Enter key
- [ ] Clear input after send

### 6E: JavaScript - Sidebar

- [ ] Display Lobby link (always)
- [ ] List online users
- [ ] List offline users (from `auth_ok`, updated on presence events)
- [ ] Update on `user_online` / `user_offline` events
- [ ] Click user to start DM (optional)

### 6F: JavaScript - History

- [ ] Load initial history on conversation open
- [ ] Load more on scroll to top
- [ ] Handle `history` event

---

## WebSocket Events to Handle

### Receive (Server → Client)

| Event | Action |
|-------|--------|
| `auth_ok` | Store user, load conversations, populate sidebar |
| `auth_error` | Show error, redirect to join |
| `message` | Append to chat |
| `user_online` | Add to online list |
| `user_offline` | Move to offline list |
| `conversation_created` | Add to sidebar |
| `history` | Prepend messages to chat |
| `pong` | Reset heartbeat timer |
| `error` | Show error message |

### Send (Client → Server)

| Event | When |
|-------|------|
| `auth` | On connect |
| `send_message` | User presses Enter |
| `load_history` | On scroll to top / initial load |
| `ping` | Every 30 seconds |

---

## UI States

### Loading
```
Connecting...
```

### Connected
```
Full chat interface
```

### Disconnected
```
Connection lost. Reconnecting...
```

### Error
```
Error message displayed
```

---

## Color Palette (Retro Terminal)

```css
--bg: #0a0a0f;           /* Near black */
--bg-secondary: #12121a; /* Slightly lighter */
--text: #c0c0c0;         /* Light gray */
--text-dim: #606060;     /* Dim gray */
--accent: #4a9eff;       /* Blue for links/self */
--border: #2a2a3a;       /* Subtle border */
--online: #4aff7a;       /* Green dot */
--offline: #606060;      /* Gray dot */
```

---

## Typography

```css
font-family: 'JetBrains Mono', 'SF Mono', 'Consolas', 'Monaco', monospace;
font-size: 14px;
line-height: 1.5;
```

---

## Deliverables Checklist

- [ ] `public/index.html` — Main app structure
- [ ] `public/css/style.css` — Retro styling
- [ ] `public/js/app.js` — WebSocket + UI logic
- [ ] WebSocket connection working
- [ ] Messages display correctly
- [ ] Sidebar shows online/offline users
- [ ] Message sending works
- [ ] History loading works

---

## Testing Plan

1. Start PHP server: `php -S localhost:8080 -t public`
2. Open `http://localhost:8080`
3. Should redirect to join if no token
4. After joining, should connect WebSocket
5. Should see Lobby and online users
6. Type message and press Enter
7. Message should appear in chat

---

## Notes

- WebSocket URL will need to be configured (friend's server)
- For testing without WebSocket, can mock events
- Keep it simple — no animations, no transitions
- Retro means functional, not fancy

