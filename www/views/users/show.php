<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_details') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>

    <div class="container">
        <h1><?= __('user_details') ?></h1>

        <div class="card" style="max-width: 600px; margin: 0 auto; text-align: center; padding: 40px;">
            <div
                style="width: 80px; height: 80px; background: var(--primary); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; color: white;">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>

            <h2 style="margin-bottom: 5px;"><?= htmlspecialchars($user['name'] ?? 'No Name') ?></h2>
            <p style="color: var(--text-muted); margin-bottom: 30px;"><?= htmlspecialchars($user['email']) ?></p>

            <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: left; background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; margin-bottom: 30px;">
                <div>
                    <span
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;"><?= __('name') ?></span>
                    <span style="font-weight: 500;"><?= htmlspecialchars($user['name'] ?? '-') ?></span>
                </div>
                <div>
                    <span
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;"><?= __('email') ?></span>
                    <span style="font-weight: 500;"><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div>
                    <span
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">ID</span>
                    <span style="font-family: monospace;">#<?= $user['id'] ?></span>
                </div>
                <div>
                    <span
                        style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Google
                        ID</span>
                    <?php if (!empty($user['google_id'])): ?>
                        <span style="font-family: monospace; color: #fff;">Linked</span>
                    <?php else: ?>
                        <span
                            style="font-family: monospace; color: #1f2937; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; border: 1px solid #d1d5db; display: inline-block;">
                            Not Linked - Check Connection
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="/users" class="back-link" style="margin: 0;"><?= __('back') ?></a>
            </div>
        </div>
    </div>

</body>

</html>