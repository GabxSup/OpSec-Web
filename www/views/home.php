<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" style="width:28px;height:28px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                SecureApp
            </div>

            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="/" class="nav-link active">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        <?= __('overview') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/users" class="nav-link">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <?= __('users') ?>
                    </a>
                </li>
            </ul>

            <div style="margin-top: auto;">
                <a href="/logout" class="nav-link" style="color: #f87171;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    <?= __('logout') ?>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div>
                    <h2 style="font-size: 1.1rem; font-weight: 500; color: var(--text-muted);"><?= __('welcome_back') ?></h2>
                    <h1 style="font-size: 1.5rem; text-align: left; margin: 0; background: none; -webkit-text-fill-color: initial; color: white;">
                        <?= htmlspecialchars($user['name'] ?? 'User') ?>
                    </h1>
                </div>

                <div class="user-profile">
                    <span style="font-size: 0.85rem; color: var(--text-muted); margin-right: 12px;">
                        <?= htmlspecialchars($user['email'] ?? '') ?>
                    </span>
                    <div class="avatar-small">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                </div>
            </header>

            <!-- Real Session Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= count($logs) ?></div>
                    <div class="stat-label"><?= __('logged_activities') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #60a5fa; font-size: 1.1rem; padding-top: 6px;">
                        <?= !empty($user['google_id']) ? 'Google OAuth 2.0' : 'Argon2id' ?>
                    </div>
                    <div class="stat-label"><?= __('auth_protocol') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #4ade80; font-size: 1.1rem; padding-top: 6px;">
                        <?= __('session_strict') ?>
                    </div>
                    <div class="stat-label"><?= __('session_security') ?></div>
                </div>
            </div>

            <!-- Security Audit Logs -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3><?= __('security_log') ?></h3>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('event') ?></th>
                                <th><?= __('ip_address') ?></th>
                                <th><?= __('system_details') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                        <?= __('no_recent_activity') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= date('d M, H:i', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <?php
                                            $badgeColor = match ($log['action']) {
                                                'login_success' => '#4ade80',
                                                'login_failed' => '#f87171',
                                                'logout' => '#94a3b8',
                                                default => '#a78bfa'
                                            };
                                            ?>
                                            <span style="color: <?= $badgeColor ?>; font-weight: 500;">
                                                <?= htmlspecialchars(__('action_' . $log['action'])) ?>
                                            </span>
                                        </td>
                                        <td style="font-family: monospace; color: var(--text-muted);">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 0.85rem;">
                                            <?= htmlspecialchars($log['details'] ?? 'N/A') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>

</html>