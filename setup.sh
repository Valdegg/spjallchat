#!/bin/bash

# Spjallchat Setup Script
# Run this once to initialize the database and create a seed invite

set -e

echo "=== Spjallchat Setup ==="
echo ""

# Create data directory
mkdir -p data
echo "✓ Created data directory"

# Initialize database
sqlite3 data/spjallchat.db < schema.sql
echo "✓ Database initialized with schema"

# Create seed invite
INVITE_CODE="WELCOME1"
sqlite3 data/spjallchat.db "INSERT OR IGNORE INTO invites (code, created_at) VALUES ('$INVITE_CODE', strftime('%s', 'now'));"
echo "✓ Created seed invite: $INVITE_CODE"

echo ""
echo "=== Setup Complete ==="
echo ""
echo "To start the server:"
echo "  php -S localhost:8080 -t public"
echo ""
echo "Then visit:"
echo "  http://localhost:8080/join.php?code=$INVITE_CODE"
echo ""

