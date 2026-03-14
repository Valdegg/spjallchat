<?php

declare(strict_types=1);

/**
 * Join endpoint - handles invite redemption (registration)
 * 
 * GET  /join/{code} - Show registration form
 * POST /join/{code} - Submit nickname + password, create user, return token
 */

// Load dependencies
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/InviteService.php';
require_once __DIR__ . '/../src/UserService.php';

// Initialize database
Database::init(__DIR__ . '/../data/spjallchat.db');

// Extract invite code from URL
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (preg_match('#^/([A-Za-z0-9]+)$#', $pathInfo, $matches)) {
        $code = $matches[1];
    }
}

$code = strtoupper(trim($code));

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet($code);
} elseif ($method === 'POST') {
    handlePost($code);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleGet(string $code): void
{
    if (empty($code)) {
        renderError('No invite code provided');
        return;
    }

    $invite = InviteService::getByCode($code);

    if ($invite === null) {
        renderError('Invalid invite code');
        return;
    }

    // Roundtable invite: check spots
    if ($invite['conversation_id'] !== null) {
        $useCount = Database::queryOne(
            'SELECT COUNT(*) as cnt FROM invite_uses WHERE invite_id = ?',
            [$invite['id']]
        );
        $spotsUsed = (int)$useCount['cnt'];
        $totalSpots = (int)$invite['total_spots'];

        if ($spotsUsed >= $totalSpots) {
            renderError('This invite is full — all spots have been taken');
            return;
        }

        // Get roundtable members for context
        $members = Database::query(
            "SELECT u.id, u.nickname FROM users u
             INNER JOIN conversation_members cm ON u.id = cm.user_id
             WHERE cm.conversation_id = ?",
            [$invite['conversation_id']]
        );

        $inviteMeta = [
            'type' => 'roundtable',
            'conversation_id' => (int)$invite['conversation_id'],
            'total_spots' => $totalSpots,
            'spots_remaining' => $totalSpots - $spotsUsed,
            'members' => array_map(fn($m) => ['id' => (int)$m['id'], 'nickname' => $m['nickname']], $members)
        ];

        renderJoinForm($code, $inviteMeta);
        return;
    }

    // Plain invite: check used_by
    if ($invite['used_by'] !== null) {
        renderError('This invite has already been used');
        return;
    }

    renderJoinForm($code, null);
}

function handlePost(string $code): void
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'No invite code provided']);
        return;
    }

    // Check if this is an existing user joining a roundtable
    $token = $input['token'] ?? null;
    if ($token !== null) {
        handleExistingUserJoin($code, $token);
        return;
    }

    // New user registration flow
    $nickname = trim($input['nickname'] ?? '');
    $password = $input['password'] ?? '';

    $invite = InviteService::validate($code);

    if ($invite === null) {
        $existingInvite = InviteService::getByCode($code);
        if ($existingInvite === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Invalid invite code']);
        } else if ($existingInvite['conversation_id'] !== null) {
            http_response_code(410);
            echo json_encode(['error' => 'This invite is full']);
        } else {
            http_response_code(410);
            echo json_encode(['error' => 'This invite has already been used']);
        }
        return;
    }

    // Validate nickname
    $nicknameError = UserService::validateNickname($nickname);
    if ($nicknameError !== null) {
        http_response_code(400);
        echo json_encode(['error' => $nicknameError]);
        return;
    }

    // Validate password
    $passwordError = UserService::validatePassword($password);
    if ($passwordError !== null) {
        http_response_code(400);
        echo json_encode(['error' => $passwordError]);
        return;
    }

    try {
        $user = UserService::create($nickname, $password);

        // Add user to Lobby (conversation_id = 1)
        Database::execute(
            'INSERT OR IGNORE INTO conversation_members (conversation_id, user_id, joined_at) VALUES (1, ?, ?)',
            [$user['id'], time()]
        );

        // Handle invite consumption based on type
        if ($invite['conversation_id'] !== null) {
            // Roundtable invite: claim spot and add to conversation
            $claimed = InviteService::consumeRoundtableSpot($invite['id'], $user['id']);
            if (!$claimed) {
                http_response_code(410);
                echo json_encode(['error' => 'This invite is full']);
                return;
            }

            // Add user to the roundtable
            Database::execute(
                'INSERT OR IGNORE INTO conversation_members (conversation_id, user_id, joined_at) VALUES (?, ?, ?)',
                [$invite['conversation_id'], $user['id'], time()]
            );

            echo json_encode([
                'token' => $user['token'],
                'user' => ['id' => $user['id'], 'nickname' => $user['nickname']],
                'conversation_id' => (int)$invite['conversation_id']
            ]);
        } else {
            // Plain invite: mark as used
            InviteService::consume($code, $user['id']);

            echo json_encode([
                'token' => $user['token'],
                'user' => ['id' => $user['id'], 'nickname' => $user['nickname']]
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
    }
}

function handleExistingUserJoin(string $code, string $token): void
{
    $user = Auth::validateToken($token);
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $invite = InviteService::validate($code);
    if ($invite === null || $invite['conversation_id'] === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or expired invite']);
        return;
    }

    $conversationId = (int)$invite['conversation_id'];

    // Check if already a member
    $existing = Database::queryOne(
        'SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?',
        [$conversationId, $user['id']]
    );
    if ($existing !== null) {
        echo json_encode([
            'already_member' => true,
            'conversation_id' => $conversationId
        ]);
        return;
    }

    // Claim spot
    $claimed = InviteService::consumeRoundtableSpot($invite['id'], $user['id']);
    if (!$claimed) {
        http_response_code(410);
        echo json_encode(['error' => 'This invite is full']);
        return;
    }

    // Add to conversation
    Database::execute(
        'INSERT INTO conversation_members (conversation_id, user_id, joined_at) VALUES (?, ?, ?)',
        [$conversationId, $user['id'], time()]
    );

    echo json_encode([
        'joined' => true,
        'conversation_id' => $conversationId
    ]);
}

function renderJoinForm(string $code, ?array $inviteMeta): void
{
    $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $metaJson = $inviteMeta ? json_encode($inviteMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null';

    // Build context text for roundtable invites
    $contextHtml = '';
    if ($inviteMeta !== null) {
        $memberNames = array_map(fn($m) => htmlspecialchars($m['nickname'], ENT_QUOTES, 'UTF-8'), $inviteMeta['members']);
        $spotsText = $inviteMeta['spots_remaining'] . ' spot' . ($inviteMeta['spots_remaining'] !== 1 ? 's' : '') . ' remaining';
        $contextHtml = '<p class="subtitle roundtable-context">Join the roundtable with ' . implode(', ', $memberNames) . '<br><span class="spots">' . $spotsText . '</span></p>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Spjall</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'JetBrains Mono', 'SF Mono', 'Consolas', monospace;
            background: #0a0a0f;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #101018;
            border: 1px solid #252530;
            padding: 32px;
            max-width: 360px;
            width: 100%;
        }

        h1 {
            font-size: 1.25rem;
            font-weight: 400;
            margin-bottom: 4px;
            color: #fff;
        }

        .subtitle {
            color: #555;
            font-size: 0.8rem;
            margin-bottom: 24px;
        }

        .roundtable-context {
            color: #5a9;
            font-size: 0.75rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .roundtable-context .spots {
            color: #666;
            font-size: 0.7rem;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #666;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-family: inherit;
            background: #0a0a0f;
            border: 1px solid #252530;
            color: #fff;
            outline: none;
        }

        input:focus {
            border-color: #5a9;
        }

        input::placeholder {
            color: #333;
        }

        .hint {
            font-size: 0.65rem;
            color: #444;
            margin-top: 4px;
        }

        button {
            width: 100%;
            padding: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            background: #486;
            border: none;
            color: #fff;
            cursor: pointer;
            margin-top: 8px;
        }

        button:hover {
            background: #5a9;
        }

        button:disabled {
            background: #333;
            cursor: not-allowed;
        }

        .error {
            background: rgba(170, 85, 85, 0.2);
            border: 1px solid #a55;
            padding: 10px 12px;
            color: #faa;
            font-size: 0.8rem;
            margin-top: 12px;
            display: none;
        }

        .error.visible {
            display: block;
        }

        .login-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.75rem;
            color: #555;
        }

        .login-link a {
            color: #5a9;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        #existing-user-view {
            display: none;
        }

        #existing-user-view p {
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        #existing-user-view .username {
            color: #5a9;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- New user registration form -->
        <div id="new-user-view">
            <h1>Join Spjall</h1>
            <p class="subtitle">Create your account</p>
            {$contextHtml}

            <form id="joinForm">
                <div class="field">
                    <label for="nickname">Username</label>
                    <input
                        type="text"
                        id="nickname"
                        name="nickname"
                        placeholder="Choose a username"
                        autocomplete="username"
                        autofocus
                        required
                        minlength="2"
                        maxlength="20"
                    >
                    <p class="hint">2-20 characters</p>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Choose a password"
                        autocomplete="new-password"
                        required
                        minlength="4"
                    >
                    <p class="hint">At least 4 characters</p>
                </div>

                <div id="error" class="error"></div>

                <button type="submit" id="submitBtn">Create Account</button>
            </form>

            <p class="login-link">Already have an account? <a href="/login">Log in</a></p>
        </div>

        <!-- Existing user roundtable join view -->
        <div id="existing-user-view">
            <h1>Join Roundtable</h1>
            {$contextHtml}
            <p>Logged in as <span class="username" id="existing-username"></span></p>

            <div id="existing-error" class="error"></div>

            <button id="joinBtn">Join Roundtable</button>
            <p class="login-link" style="margin-top: 12px;"><a href="#" id="switch-account">Use a different account</a></p>
        </div>
    </div>

    <script>
        const inviteCode = '{$code}';
        const inviteMeta = {$metaJson};
        const token = localStorage.getItem('spjall_token');
        const savedUser = localStorage.getItem('spjall_user');

        // Show existing user view if logged in AND this is a roundtable invite
        if (token && savedUser && inviteMeta && inviteMeta.type === 'roundtable') {
            const user = JSON.parse(savedUser);
            document.getElementById('new-user-view').style.display = 'none';
            document.getElementById('existing-user-view').style.display = 'block';
            document.getElementById('existing-username').textContent = user.nickname;

            // Join button handler
            document.getElementById('joinBtn').addEventListener('click', async () => {
                const btn = document.getElementById('joinBtn');
                const errorDiv = document.getElementById('existing-error');
                btn.disabled = true;
                btn.textContent = 'Joining...';
                errorDiv.classList.remove('visible');

                try {
                    const response = await fetch('/join/' + inviteCode, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ token: token })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to join');
                    }

                    if (data.already_member) {
                        localStorage.setItem('spjall_pending_conversation', data.conversation_id.toString());
                        window.location.href = '/';
                        return;
                    }

                    if (data.joined) {
                        localStorage.setItem('spjall_pending_conversation', data.conversation_id.toString());
                        window.location.href = '/';
                    }
                } catch (err) {
                    errorDiv.textContent = err.message;
                    errorDiv.classList.add('visible');
                    btn.disabled = false;
                    btn.textContent = 'Join Roundtable';
                }
            });

            // Switch account link
            document.getElementById('switch-account').addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('existing-user-view').style.display = 'none';
                document.getElementById('new-user-view').style.display = 'block';
            });
        }

        // New user registration form
        const form = document.getElementById('joinForm');
        const errorDiv = document.getElementById('error');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nickname = document.getElementById('nickname').value.trim();
            const password = document.getElementById('password').value;

            if (!nickname || !password) return;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';
            errorDiv.classList.remove('visible');

            try {
                const response = await fetch('/join/' + inviteCode, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nickname, password })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Failed to create account');
                }

                localStorage.setItem('spjall_token', data.token);
                localStorage.setItem('spjall_user', JSON.stringify(data.user));

                // If roundtable invite, store pending conversation
                if (data.conversation_id) {
                    localStorage.setItem('spjall_pending_conversation', data.conversation_id.toString());
                }

                window.location.href = '/';

            } catch (err) {
                errorDiv.textContent = err.message;
                errorDiv.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>
HTML;
}

function renderError(string $message): void
{
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    http_response_code(400);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Invite - Spjall</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'JetBrains Mono', 'SF Mono', 'Consolas', monospace;
            background: #0a0a0f;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #101018;
            border: 1px solid #252530;
            padding: 32px;
            max-width: 360px;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: 8px;
            color: #faa;
        }
        p { color: #555; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$message}</h1>
        <p>Ask a friend for a valid invite link</p>
    </div>
</body>
</html>
HTML;
}
