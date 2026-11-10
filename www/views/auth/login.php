<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('sign_in') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .google-btn {
            background-color: white;
            color: #333;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .google-btn:hover {
            background-color: #f1f1f1;
            opacity: 1;
        }

        .apple-btn {
            background-color: #000;
            color: #fff;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .apple-btn:hover {
            background-color: #222;
            color: #fff;
            opacity: 1;
        }
    </style>
</head>

<body>

    <div class="container" style="max-width: 400px;">
        <h1><?= __('sign_in') ?></h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label for="email"><?= __('email_address') ?></label>
                <input type="email" id="email" name="email" placeholder="example@email.com" required>
            </div>

            <div class="form-group">
                <label for="password"><?= __('password') ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="<?= __('password') ?>" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary"><?= __('sign_in') ?></button>

            <a href="/login/google" class="btn-primary google-btn" style="text-decoration: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18">
                    <path fill="#4285F4"
                        d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" />
                    <path fill="#34A853"
                        d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.715H.957v2.332A8.997 8.997 0 0 0 9 18z" />
                    <path fill="#FBBC05"
                        d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" />
                    <path fill="#EA4335"
                        d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.157 6.656 3.58 9 3.58z" />
                </svg>
                <?= __('sign_in_google') ?>
            </a>

            <a href="/login/apple" class="btn-primary apple-btn" style="text-decoration: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 170 170" fill="currentColor">
                    <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.7-3.04-7.6-7.83-11.71-14.34-6.3-9.87-11.39-20.9-15.26-33.09-3.87-12.19-5.81-23.77-5.81-34.74 0-14.07 3.51-25.96 10.53-35.67 7.02-9.7 15.89-14.68 26.61-14.94 4.54 0 9.87 1.25 15.99 3.75 6.12 2.5 10.22 3.82 12.3 3.96 1.81-.14 6.03-1.46 12.67-3.96 6.64-2.5 11.83-3.68 15.57-3.53 11.22.46 20.35 4.54 27.4 12.24-9.88 5.98-14.71 14.19-14.5 24.62.21 8.28 3.35 15.28 9.42 21 6.07 5.72 13.27 9.07 21.6 10.05-2.02 6.09-4.5 12.3-7.46 18.63zm-32.32-109.9c0 7.37-2.67 14.24-8.01 20.61-5.34 6.37-11.95 10.22-19.83 11.55-.42-1.68-.63-3.37-.63-5.07 0-7.38 2.87-14.33 8.61-20.85 5.74-6.52 12.43-10.27 20.08-11.24-.22 1.68-.22 3.35-.22 5z"/>
                </svg>
                <?= __('sign_in_apple') ?>
            </a>
        </form>

        <div style="margin-top: 20px; text-align: center; border-top: 1px solid var(--border); padding-top: 20px;">
            <span style="color: var(--text-muted); font-size: 0.9rem;"><?= __('no_account') ?></span>
            <a href="/users/create"
                style="color: var(--primary); text-decoration: none; font-weight: bold; margin-left: 5px;"><?= __('create_account') ?></a>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>

</body>

</html>