<?php

require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../helpers/Lang.php';

// Set language
if (session_status() === PHP_SESSION_NONE)
    session_start();
$lang = $_SESSION['lang'] ?? 'en';
Lang::load($lang);

class HomeController extends BaseController
{
    public function index()
    {
        auth_required();

        $authUserId = $_SESSION['user'];
        $user = (new User)->find($authUserId);

        $logger = new Logger();
        $logs = $logger->getUserLogs($authUserId);

        $this->view('home', [
            'logs' => $logs,
            'user' => $user
        ]);
    }
}
