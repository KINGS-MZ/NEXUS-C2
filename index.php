<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUS C2 - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <div class="logo-icon"><span>N</span></div>
                    <h1>NEXUS</h1>
                </div>
                <p>Command & Control</p>
            </div>
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div id="errorMsg" class="error-message"></div>
                <button type="submit" class="btn-primary">Access Panel</button>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorMsg = document.getElementById('errorMsg');
            const btn = e.target.querySelector('button');
            
            btn.disabled = true;
            btn.textContent = 'Authenticating...';
            errorMsg.textContent = '';
            
            try {
                const response = await fetch('/c2/api/auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'login',
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.textContent = data.error || 'Authentication failed';
                    btn.disabled = false;
                    btn.textContent = 'Access Panel';
                }
            } catch (err) {
                errorMsg.textContent = 'Connection error';
                btn.disabled = false;
                btn.textContent = 'Access Panel';
            }
        });
    </script>
</body>
</html>
