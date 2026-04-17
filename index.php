<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifat Enterprise | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
     /* ── Variables (mirror style.css) ── */
        :root {
            --bg-base:        #07090f;
            --bg-surface:     #0e1118;
            --bg-elevated:    #141720;
            --bg-overlay:     rgba(255,255,255,0.04);
            --bg-hover:       rgba(255,255,255,0.07);

            --border:         rgba(255,255,255,0.07);
            --border-strong:  rgba(255,255,255,0.13);

            --accent-1:       #6c63ff;
            --accent-2:       #a78bfa;
            --accent-warm:    #f472b6;
            --accent-green:   #34d399;

            --text-primary:   #f0f0f8;
            --text-secondary: rgba(240,240,248,0.55);
            --text-muted:     rgba(240,240,248,0.3);

            --radius-sm:  8px;
            --radius-md:  14px;
            --radius-lg:  20px;
            --radius-xl:  28px;

            --shadow-card:  0 4px 24px rgba(0,0,0,0.45), 0 1px 0 rgba(255,255,255,0.05) inset;
            --shadow-hover: 0 8px 40px rgba(0,0,0,0.6), 0 0 0 1px rgba(108,99,255,0.25);
            --shadow-glow:  0 0 50px rgba(108,99,255,0.15);

            --transition-smooth: 0.28s cubic-bezier(0.4,0,0.2,1);
            --transition-spring: 0.4s cubic-bezier(0.34,1.56,0.64,1);
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg-base);
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(108,99,255,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 110%, rgba(167,139,250,0.12) 0%, transparent 60%),
                url("assets/images/background.jpg") center / cover no-repeat fixed;
            background-blend-mode: normal, normal, overlay;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            overflow: hidden;
            animation: pageReveal 0.7s cubic-bezier(0.4,0,0.2,1) forwards;
        }

        @keyframes pageReveal {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── Ambient orbs ── */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            animation: drift 18s ease-in-out infinite alternate;
        }
        body::before {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(108,99,255,0.12), transparent 70%);
            top: -200px; left: -200px;
        }
        body::after {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(244,114,182,0.08), transparent 70%);
            bottom: -180px; right: -150px;
            animation-delay: -9s;
        }
        @keyframes drift {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(40px,30px) scale(1.05); }
        }

        /* ── Card ── */
        .login-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-xl);
            padding: 48px 44px;
            width: 420px;
            max-width: 92vw;
            position: relative;
            box-shadow: var(--shadow-card), var(--shadow-glow);
            overflow: hidden;
            animation: cardIn 0.6s cubic-bezier(0.34,1.2,0.64,1) 0.1s both;
        }

        @keyframes cardIn {
            from { opacity:0; transform:translateY(24px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }

        /* Accent top line */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-1), var(--accent-warm), var(--accent-2));
        }

        /* Subtle inner glow */
        .login-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(ellipse 80% 40% at 50% 0%, rgba(108,99,255,0.06), transparent 60%);
            pointer-events: none;
        }

        /* ── Logo ── */
        .logo {
            text-align: center;
            margin-bottom: 8px;
        }
        .logo h1 {
            font-family: 'Syne', sans-serif;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--text-primary) 30%, var(--accent-2));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome {
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 400;
            line-height: 1.6;
            margin-bottom: 36px;
        }

        /* ── Form groups ── */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 400;
            outline: none;
            transition: all var(--transition-smooth);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .form-group input:focus {
            border-color: rgba(108,99,255,0.55);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.12);
            background: var(--bg-surface);
        }

        /* ── Error alert ── */
        .alert-error {
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.25);
            border-radius: var(--radius-sm);
            color: #f87171;
            font-size: 13px;
            padding: 11px 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Submit button ── */
        .login-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--accent-1), #4f46e5);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.02em;
            transition: all var(--transition-smooth);
            box-shadow: 0 4px 14px rgba(108,99,255,0.4);
            margin-top: 4px;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08), transparent);
            opacity: 0;
            transition: opacity var(--transition-smooth);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108,99,255,0.55);
            filter: brightness(1.06);
        }
        .login-btn:hover::before { opacity: 1; }
        .login-btn:active { transform: translateY(0); }

        /* ── Footer note ── */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            color: var(--text-muted);
            font-size: 12px;
        }

        .login-footer span {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 14px;
            background: var(--bg-overlay);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 11px;
            color: var(--text-muted);
        }

        @media (max-width: 480px) {
            .login-card { padding: 36px 24px; }
        }    
    </style>
</head>
<body>

    <div class="login-card">

        <!-- Logo -->
        <div class="logo">
            <h1>Rifat.</h1>
        </div>
        <p class="welcome">Welcome back — sign in to your dashboard.</p>

        <!-- Error message (shown when login fails) -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                ⚠️
                <?php
                    $errs = [
                        'invalid'  => 'Invalid email or password. Please try again.',
                        'empty'    => 'Please fill in all fields.',
                        'access'   => 'You do not have access to this page.',
                    ];
                    echo htmlspecialchars($errs[$_GET['error']] ?? 'Login failed. Please try again.');
                ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="login-btn">Sign In →</button>

        </form>

        <div class="login-footer">
            <p>Rifat Enterprise Inventory System</p>
            <span>🔒 Secure internal access only</span>
        </div>

    </div>

</body>
</html>