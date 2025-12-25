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

    if ($invite['used_by'] !== null) {
        renderError('This invite has already been used');
        return;
    }

    renderJoinForm($code);
}

function handlePost(string $code): void
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $nickname = trim($input['nickname'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'No invite code provided']);
        return;
    }

    $invite = InviteService::validate($code);
    
    if ($invite === null) {
        $existingInvite = InviteService::getByCode($code);
        if ($existingInvite === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Invalid invite code']);
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
        // Create user with password
        $user = UserService::create($nickname, $password);

        // Consume invite
        InviteService::consume($code, $user['id']);

        // Return success
        echo json_encode([
            'token' => $user['token'],
            'user' => [
                'id' => $user['id'],
                'nickname' => $user['nickname']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
    }
}

function renderJoinForm(string $code): void
{
    $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Join Spjall</h1>
        <p class="subtitle">Create your account</p>
        
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

    <script>
        const form = document.getElementById('joinForm');
        const errorDiv = document.getElementById('error');
        const submitBtn = document.getElementById('submitBtn');
        const inviteCode = '{$code}';

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
