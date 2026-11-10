<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_list') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <span style="color: var(--text-muted);">ID: <?= $_SESSION['user_id'] ?? '?' ?></span>
            <a href="/logout" style="color: #ef4444; text-decoration: none; font-size: 0.9rem;"><?= __('logout') ?></a>
        </div>

        <h1><?= __('user_list') ?></h1>

        <div style="margin-bottom: 20px; display: flex; gap: 10px;">
            <a href="/" class="btn-primary"
                style="text-decoration: none; display: inline-block; text-align: center; background: #e5e5e5; color: #171717; width: auto; padding-left: 20px; padding-right: 20px;">
                &larr; <?= __('back') ?>
            </a>
            <a href="/users/create" class="btn-primary"
                style="text-decoration: none; display: inline-block; text-align: center; flex: 1;"><?= __('create_new_user') ?></a>
        </div>

        <div class="card">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500;">ID
                            </th>
                            <th style="text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500;">
                                <?= __('name') ?>
                            </th>
                            <th style="text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500;">
                                <?= __('email') ?>
                            </th>
                            <th style="text-align: right; padding: 12px; color: var(--text-muted); font-weight: 500;">
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 12px; color: var(--text-muted);">#<?= $user['id'] ?></td>
                                <td style="padding: 12px; font-weight: 500;"><?= htmlspecialchars($user['name'] ?? 'N/A') ?>
                                </td>
                                <td style="padding: 12px; color: var(--text-muted);"><?= htmlspecialchars($user['email']) ?>
                                </td>
                                <td style="padding: 12px; text-align: right;">
                                    <a href="/users/<?= $user['id'] ?>" class="btn-primary"
                                        style="padding: 6px 12px; font-size: 0.85rem; text-decoration: none;">
                                        <?= __('view') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="/users?page=<?= $page - 1 ?>" class="btn-primary"
                        style="text-decoration: none; background: #e5e5e5; color: #171717;">&larr; <?= __('prev') ?></a>
                <?php endif; ?>

                <span style="align-self: center; color: var(--text-muted); font-size: 0.9rem;">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="/users?page=<?= $page + 1 ?>" class="btn-primary" style="text-decoration: none;">&rarr;
                        <?= __('next') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>

</html>