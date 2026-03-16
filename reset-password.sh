#!/bin/bash
# Reset a user's password in Spjallchat

DB="data/spjallchat.db"

if [ ! -f "$DB" ]; then
    echo "Database not found at $DB"
    exit 1
fi

# Get username
read -p "Username: " USERNAME

# Check user exists
EXISTS=$(sqlite3 "$DB" "SELECT COUNT(*) FROM users WHERE nickname = '$USERNAME' COLLATE NOCASE;")
if [ "$EXISTS" -eq 0 ]; then
    echo "User '$USERNAME' not found"
    exit 1
fi

# Get new password
read -s -p "New password: " PASSWORD
echo
read -s -p "Confirm password: " PASSWORD2
echo

if [ "$PASSWORD" != "$PASSWORD2" ]; then
    echo "Passwords don't match"
    exit 1
fi

if [ ${#PASSWORD} -lt 4 ]; then
    echo "Password must be at least 4 characters"
    exit 1
fi

# Hash and update
HASH=$(php -r "echo password_hash('$PASSWORD', PASSWORD_DEFAULT);")
sqlite3 "$DB" "UPDATE users SET password_hash='$HASH' WHERE nickname='$USERNAME' COLLATE NOCASE;"

echo "Password reset for '$USERNAME'"
