<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$users = $pdo->query('SELECT admin_id, admin_email, images FROM Users ORDER BY admin_id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Danh sách người dùng</title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
<main>
    <h1>Danh sách người dùng</h1>
    <p>Dữ liệu được đọc từ bảng <strong>Users</strong> trong database <strong>QLNhanSu</strong>.</p>
    <p><a href="admin.php">Mở trang quản trị (thêm, sửa, xóa)</a></p>

    <?php if (!$users): ?>
        <p>Chưa có dữ liệu người dùng.</p>
    <?php else: ?>
    <div class="user-grid">
        <?php foreach ($users as $user): ?>
        <article class="user-card">
            <?php if (!empty($user['images'])): ?>
                <img class="avatar" src="<?php echo h($user['images']); ?>" alt="Ảnh người dùng">
            <?php else: ?>
                <div class="avatar avatar-empty" aria-label="Chưa có ảnh">Ảnh</div>
            <?php endif; ?>
            <div class="user-info">
                <h2><?php echo h($user['admin_email']); ?></h2>
                <p>Mã người dùng: <?php echo (int)$user['admin_id']; ?></p>
                <p>Mật khẩu: ******** (được bảo vệ)</p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
