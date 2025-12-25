<?php

/**
 * Test script for Identity system (Step 2)
 * Run with: php test_identity.php
 */

declare(strict_types=1);

echo "=== Spjallchat Identity System Test ===\n\n";

// Load dependencies
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/InviteService.php';
require_once __DIR__ . '/src/UserService.php';
require_once __DIR__ . '/src/Handlers/AuthHandler.php';

// Initialize database with fresh test DB
$testDbPath = __DIR__ . '/data/test_identity.db';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

Database::init($testDbPath);

// Run schema
echo "1. Setting up database schema...\n";
$schema = file_get_contents(__DIR__ . '/schema.sql');
Database::get()->exec($schema);
echo "   ✓ Schema created\n\n";

// Test 1: Token generation
echo "2. Testing token generation...\n";
$token1 = Auth::generateToken();
$token2 = Auth::generateToken();
assert(strlen($token1) === 64, 'Token should be 64 chars');
assert($token1 !== $token2, 'Tokens should be unique');
echo "   ✓ Tokens are 64 chars and unique\n\n";

// Test 2: Invite creation
echo "3. Testing invite creation...\n";
$inviteCode = InviteService::create(null); // Seed invite, no creator
assert(strlen($inviteCode) === 8, 'Invite code should be 8 chars');
echo "   ✓ Created invite: $inviteCode\n\n";

// Test 3: Invite validation
echo "4. Testing invite validation...\n";
$invite = InviteService::validate($inviteCode);
assert($invite !== null, 'Valid invite should return data');
assert($invite['used_by'] === null, 'Invite should not be used yet');
echo "   ✓ Invite is valid and unused\n\n";

// Test 4: Nickname validation
echo "5. Testing nickname validation...\n";
$errors = [
    UserService::validateNickname('a'),           // Too short
    UserService::validateNickname(str_repeat('a', 25)), // Too long
    UserService::validateNickname('hello world'), // Space not allowed
    UserService::validateNickname('user@name'),   // @ not allowed
];
assert($errors[0] !== null, 'Should reject short nickname');
assert($errors[1] !== null, 'Should reject long nickname');
assert($errors[2] !== null, 'Should reject spaces');
assert($errors[3] !== null, 'Should reject special chars');

$valid = UserService::validateNickname('valid_user-123');
assert($valid === null, 'Should accept valid nickname');
echo "   ✓ Nickname validation works correctly\n\n";

// Test 5: User creation
echo "6. Testing user creation...\n";
$user = UserService::create('TestUser');
assert(isset($user['id']), 'User should have ID');
assert($user['nickname'] === 'TestUser', 'Nickname should match');
assert(strlen($user['token']) === 64, 'User should have token');
echo "   ✓ Created user: {$user['nickname']} (ID: {$user['id']})\n\n";

// Test 6: Duplicate nickname rejection
echo "7. Testing duplicate nickname rejection...\n";
try {
    UserService::create('TestUser');
    assert(false, 'Should have thrown exception');
} catch (InvalidArgumentException $e) {
    echo "   ✓ Correctly rejected duplicate: {$e->getMessage()}\n\n";
}

// Test 7: Invite consumption
echo "8. Testing invite consumption...\n";
$consumed = InviteService::consume($inviteCode, $user['id']);
assert($consumed === true, 'Consume should succeed');
$usedInvite = InviteService::validate($inviteCode);
assert($usedInvite === null, 'Used invite should fail validation');
echo "   ✓ Invite consumed and cannot be reused\n\n";

// Test 8: Token validation
echo "9. Testing token validation...\n";
$validatedUser = Auth::validateToken($user['token']);
assert($validatedUser !== null, 'Valid token should return user');
assert($validatedUser['id'] === $user['id'], 'User ID should match');

$invalidUser = Auth::validateToken('invalid_token');
assert($invalidUser === null, 'Invalid token should return null');
echo "   ✓ Token validation works\n\n";

// Test 9: Auth handler
echo "10. Testing WebSocket auth handler...\n";
$authResult = AuthHandler::handle(['token' => $user['token']]);
assert($authResult['type'] === 'auth_ok', 'Should return auth_ok');
assert($authResult['payload']['user']['id'] === $user['id'], 'User should match');
assert(isset($authResult['payload']['conversations']), 'Should include conversations');
echo "   ✓ Auth handler returns correct response\n\n";

// Test 10: Auth handler with invalid token
echo "11. Testing auth handler with invalid token...\n";
$authError = AuthHandler::handle(['token' => 'bad_token']);
assert($authError['type'] === 'auth_error', 'Should return auth_error');
echo "   ✓ Auth handler rejects invalid token\n\n";

// Cleanup
unlink($testDbPath);

echo "=== All tests passed! ===\n";

