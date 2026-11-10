<?php

require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../core/RateLimiter.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../helpers/Lang.php';

// Set language (basic checking from session or default)
if (session_status() === PHP_SESSION_NONE)
    session_start();
$lang = $_SESSION['lang'] ?? 'en';
Lang::load($lang);

class AuthController extends BaseController
{
    public function loginForm()
    {
        $this->view('auth/login');
    }

    public function login()
    {
        csrf_check();

        $limiter = new RateLimiter();
        $logger = new Logger();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($limiter->isBlocked($ip)) {
            $logger->log('blocked_ip', null, $email, "IP Blocked due to excessive attempts");
            $_SESSION['error'] = __('error_too_many_attempts');
            $this->view('auth/login');
            exit;
        }

        $user = (new User)->findByEmail($email);

        // Constant-time mitigation against user enumeration timing attacks:
        // Run dummy verification if account doesn't exist so execution time is indistinguishable.
        $dummyHash = '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXlzYWx0ZHVtbXlzYWx0$eCvhgM9y3Y885s6lXwJ47Y2u1eK0jO5M3q7Y3R2Q0P4';

        if (!$user) {
            password_verify($password, $dummyHash);
            $limiter->logAttempt($ip);
            $logger->log('login_failed', null, $email, "User not found");
            $_SESSION['error'] = __('error_incorrect_password');
            $this->view('auth/login');
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            $limiter->logAttempt($ip);
            $logger->log('login_failed', null, $email, "Invalid password");
            $_SESSION['error'] = __('error_incorrect_password');
            $this->view('auth/login');
            exit;
        }

        // Login success - regenerate session ID to prevent Session Fixation (CWE-384)
        $limiter->clearAttempts($ip);
        session_regenerate_id(true);
        $_SESSION['user'] = $user['id'];
        $logger->log('login_success', $user['id'], $user['email']);
        $this->redirect('/');
    }

    public function logout()
    {
        if (isset($_SESSION['user'])) {
            (new Logger)->log('logout', $_SESSION['user']);
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
        $this->redirect('/login');
    }

    public function loginGoogle()
    {
        $clientID    = getenv('GOOGLE_CLIENT_ID');
        $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8000/login/google/callback';

        if (empty($clientID)) {
            $_SESSION['error'] = 'Google Login is not configured. Set GOOGLE_CLIENT_ID in .env';
            $this->view('auth/login');
            return;
        }

        // OAuth CSRF protection: Cryptographic state parameter
        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_state'] = $state;

        $params = [
            'client_id'     => $clientID,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'email profile',
            'access_type'   => 'online',
            'state'         => $state
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        header("Location: $url");
        exit;
    }

    public function loginGoogleCallback()
    {
        if (empty($_GET['code'])) {
            $this->redirect('/login');
        }

        // Verify OAuth CSRF state parameter
        if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
            unset($_SESSION['oauth_state']);
            $_SESSION['error'] = 'Invalid OAuth state verification token.';
            $this->redirect('/login');
        }
        unset($_SESSION['oauth_state']);

        // Load credentials from environment
        $clientID     = getenv('GOOGLE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $redirectUri  = getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8000/login/google/callback';

        if (empty($clientID) || empty($clientSecret)) {
            $_SESSION['error'] = 'Google OAuth is not configured. Check your .env file.';
            $this->redirect('/login');
        }

        // 1. Exchange code for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code'          => $_GET['code'],
            'client_id'     => $clientID,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);
        if (!isset($tokenData['access_token'])) {
            $_SESSION['error'] = 'Failed to obtain Google access token.';
            $this->redirect('/login');
        }

        // 2. Retrieve user info
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $userInfo = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (empty($userInfo['email']) || empty($userInfo['id'])) {
            $_SESSION['error'] = 'Failed to retrieve Google user information.';
            $this->redirect('/login');
        }

        $email = $userInfo['email'];
        $googleId = $userInfo['id'];

        // 3. Find or Create user
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if ($user) {
            if (empty($user['google_id'])) {
                $userModel->updateGoogleId($user['id'], $googleId);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $user['id'];
        } else {
            $randomPass = bin2hex(random_bytes(16));
            $userModel->create([
                'name' => $userInfo['name'] ?? 'Google User',
                'email' => $email,
                'password' => $randomPass,
                'password_confirm' => $randomPass,
                'google_id' => $googleId,
            ]);
            $newUser = $userModel->findByEmail($email);
            session_regenerate_id(true);
            $_SESSION['user'] = $newUser['id'];
            (new Logger)->log('register_google', $newUser['id'], $email);
        }

        (new Logger)->log('login_google', $_SESSION['user'], $email);
        $this->redirect('/');
    }

    public function loginApple()
    {
        $clientID    = getenv('APPLE_CLIENT_ID');
        $redirectUri = getenv('APPLE_REDIRECT_URI') ?: 'http://localhost:8000/login/apple/callback';

        if (empty($clientID)) {
            $_SESSION['error'] = 'Apple Sign In is not configured. Set APPLE_CLIENT_ID in .env';
            $this->view('auth/login');
            return;
        }

        // OAuth CSRF protection: Cryptographic state parameter
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $_SESSION['oauth_state_apple'] = $state;
        $_SESSION['oauth_nonce_apple'] = $nonce;

        $params = [
            'client_id'     => $clientID,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code id_token',
            'scope'         => 'name email',
            'response_mode' => 'form_post',
            'state'         => $state,
            'nonce'         => $nonce,
        ];

        $url = 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);
        header("Location: $url");
        exit;
    }

    public function loginAppleCallback()
    {
        $code    = $_POST['code'] ?? $_GET['code'] ?? null;
        $state   = $_POST['state'] ?? $_GET['state'] ?? null;
        $idToken = $_POST['id_token'] ?? $_GET['id_token'] ?? null;
        $userRaw = $_POST['user'] ?? $_GET['user'] ?? null;

        if (empty($code) && empty($idToken)) {
            $this->redirect('/login');
        }

        // Verify OAuth CSRF state parameter if session maintained
        if (!empty($_SESSION['oauth_state_apple'])) {
            if (empty($state) || !hash_equals($_SESSION['oauth_state_apple'], $state)) {
                unset($_SESSION['oauth_state_apple']);
                $_SESSION['error'] = 'Invalid OAuth state verification token.';
                $this->redirect('/login');
            }
            unset($_SESSION['oauth_state_apple']);
        }

        // Extract and decode claims from the Apple id_token (JWT)
        $email = null;
        $appleId = null;

        if (!empty($idToken)) {
            $jwtParts = explode('.', $idToken);
            if (count($jwtParts) >= 2) {
                $payloadJson = base64_decode(strtr($jwtParts[1], '-_', '+/'));
                $claims = json_decode($payloadJson, true);
                if (is_array($claims)) {
                    $appleId = $claims['sub'] ?? null;
                    $email = $claims['email'] ?? null;
                }
            }
        }

        if (empty($appleId)) {
            $_SESSION['error'] = 'Failed to verify Apple identity credentials.';
            $this->redirect('/login');
        }

        // Parse user name if provided by Apple (only sent on first authorization)
        $userName = 'Apple User';
        if (!empty($userRaw)) {
            $userData = is_string($userRaw) ? json_decode($userRaw, true) : $userRaw;
            if (is_array($userData) && !empty($userData['name'])) {
                $first = $userData['name']['firstName'] ?? '';
                $last  = $userData['name']['lastName'] ?? '';
                $fullName = trim("$first $last");
                if (!empty($fullName)) {
                    $userName = $fullName;
                }
            }
        }

        // Fallback email if Apple hid it and no email claim in id_token
        if (empty($email)) {
            $email = $appleId . '@privaterelay.appleid.com';
        }

        // Find or Create user in database
        $userModel = new User();
        $user = $userModel->findByAppleId($appleId);

        if (!$user) {
            $user = $userModel->findByEmail($email);
        }

        if ($user) {
            if (empty($user['apple_id'])) {
                $userModel->updateAppleId($user['id'], $appleId);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = $user['id'];
        } else {
            $randomPass = bin2hex(random_bytes(16));
            $userModel->create([
                'name'             => $userName,
                'email'            => $email,
                'password'         => $randomPass,
                'password_confirm' => $randomPass,
                'apple_id'         => $appleId,
            ]);
            $newUser = $userModel->findByAppleId($appleId) ?: $userModel->findByEmail($email);
            session_regenerate_id(true);
            $_SESSION['user'] = $newUser['id'];
            (new Logger)->log('register_apple', $newUser['id'], $email);
        }

        (new Logger)->log('login_apple', $_SESSION['user'], $email);
        $this->redirect('/');
    }
}

