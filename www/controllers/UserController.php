<?php

require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../core/RateLimiter.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../helpers/Lang.php';

// Set language
if (session_status() === PHP_SESSION_NONE)
    session_start();
$lang = $_SESSION['lang'] ?? 'en';
Lang::load($lang);

class UserController extends BaseController
{
    public function index()
    {
        auth_required();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $userModel = new User();
        $totalUsers = $userModel->count();
        $totalPages = ceil($totalUsers / $limit);

        $users = $userModel->all($limit, $offset);

        $this->view('users/index', compact('users', 'page', 'totalPages'));
    }

    public function show($id)
    {
        auth_required();
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(404);
            $this->redirect('/users');
        }

        $user = (new User)->find($id);
        if (!$user) {
            http_response_code(404);
            $this->redirect('/users');
        }

        $this->view('users/show', compact('user'));
    }

    public function create()
    {
        // auth_required();
        $creation_id = bin2hex(random_bytes(4)); // Unique ID for this creation form
        $this->view('users/create', ['creation_id' => $creation_id]);
    }

    public function store()
    {
        // auth_required();
        csrf_check(); // Validate CSRF token

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users/create');
        }

        // Anti-abuse: Rate Limiting for Registration
        $limiter = new RateLimiter();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($limiter->isBlocked($ip)) {
            (new Logger)->log('blocked_ip_register', null, 'system', "IP Blocked due to excessive registration attempts");
            $_SESSION['error'] = __('error_too_many_attempts');
            $this->redirect('/users/create');
        }

        // Input Validation
        $name = trim($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($name) || mb_strlen($name) > 100) {
            $_SESSION['error'] = __('error_invalid_data');
            $this->redirect('/users/create');
        }

        if (!$email) {
            $_SESSION['error'] = 'Invalid email format';
            $this->redirect('/users/create');
        }

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long';
            $this->redirect('/users/create');
        }

        $user = new User();

        if (!$user->create($_POST)) {
            $limiter->logAttempt($ip); // Log failed attempt (potential spam)
            $_SESSION['error'] = __('error_invalid_data');
            $this->redirect('/users/create');
        }

        $limiter->clearAttempts($ip);

        $this->redirect('/users');
    }

}
