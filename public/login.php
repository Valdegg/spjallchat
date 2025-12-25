<?php

declare(strict_types=1);

/**
 * Login endpoint - for returning users
 * 
 * GET  /login - Show login form
 * POST /login - Authenticate with username + password
 */

// Load dependencies
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserService.php';

// Initialize database
Database::init(__DIR__ . '/../data/spjallchat.db');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    renderLoginForm();
} elseif ($method === 'POST') {
    handleLogin();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleLogin(): void
{
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $nickname = trim($input['nickname'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($nickname) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }

    // Authenticate
    $user = UserService::authenticate($nickname, $password);

    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        return;
    }

    // Return success with token
    echo json_encode([
        'token' => $user['token'],
        'user' => [
            'id' => $user['id'],
            'nickname' => $user['nickname']
        ]
    ]);
}

function renderLoginForm(): void
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Spjall</title>
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
        
        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.75rem;
            color: #555;
        }
        
        .register-link a {
            color: #5a9;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Spjall</h1>
        <p class="subtitle">Log in to continue</p>
        
        <form id="loginForm">
            <div class="field">
                <label for="nickname">Username</label>
                <input 
                    type="text" 
                    id="nickname" 
                    name="nickname" 
                    placeholder="Your username"
                    autocomplete="username"
                    autofocus
                    required
                >
            </div>
            
            <div class="field">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Your password"
                    autocomplete="current-password"
                    required
                >
            </div>
            
            <div id="error" class="error"></div>
            
            <button type="submit" id="submitBtn">Log In</button>
        </form>
        
        <p class="register-link">Need an account? Get an invite from a friend</p>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const errorDiv = document.getElementById('error');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const nickname = document.getElementById('nickname').value.trim();
            const password = document.getElementById('password').value;
            
            if (!nickname || !password) return;
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
            errorDiv.classList.remove('visible');
            
            try {
                const response = await fetch('/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nickname, password })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Failed to log in');
                }
                
                localStorage.setItem('spjall_token', data.token);
                localStorage.setItem('spjall_user', JSON.stringify(data.user));
                window.location.href = '/';
                
            } catch (err) {
                errorDiv.textContent = err.message;
                errorDiv.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Log In';
            }
        });
    </script>
</body>
</html>
HTML;
}

