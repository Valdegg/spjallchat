    Foundation & Realtime Transport → Depends on: (none)
	•	Identity & Invite Links → Depends on: Foundation & Realtime Transport
	•	Data Model & Persistence → Depends on: Foundation & Realtime Transport
	•	Messaging Core (send/receive/ack) → Depends on: Foundation & Realtime Transport, Identity & Invite Links, Data Model & Persistence
	•	Presence (online users) → Depends on: Foundation & Realtime Transport, Identity & Invite Links
	•	Direct Messages → Depends on: Messaging Core (send/receive/ack), Data Model & Persistence
	•	Group Subsets (ad-hoc group chats) → Depends on: Messaging Core (send/receive/ack), Identity & Invite Links, Data Model & Persistence
	•	Lobby Chat → Depends on: Messaging Core (send/receive/ack), Data Model & Persistence
	•	Sidebar Selection & Conversation Index → Depends on: Lobby Chat, Direct Messages, Group Subsets (ad-hoc group chats), Presence (online users), Data Model & Persistence
	•	History Loading (per conversation) → Depends on: Data Model & Persistence, plus whichever conversation type it’s loading (Lobby Chat / Direct Messages / Group Subsets (ad-hoc group chats))