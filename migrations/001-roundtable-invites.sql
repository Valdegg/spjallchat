ALTER TABLE invites ADD COLUMN conversation_id INTEGER REFERENCES conversations(id);
ALTER TABLE invites ADD COLUMN total_spots INTEGER;

CREATE TABLE IF NOT EXISTS invite_uses (
    invite_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    used_at INTEGER NOT NULL,
    PRIMARY KEY (invite_id, user_id),
    FOREIGN KEY (invite_id) REFERENCES invites(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
