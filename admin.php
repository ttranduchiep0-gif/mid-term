<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function message(string $text): never
{
    $_SESSION['message'] = $text;
    header('Location: admin.php');
    exit;
}
function removeImage(?string $image): void
{
    if ($image && str_starts_with($image, 'uploads/')) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . $image;
        if (is_file($path)) unlink($path);
    }
}
function uploadImage(?string $oldImage): ?string
{
    if (!isset($_FILES['images']) || $_FILES['images']['error'] === UPLOAD_ERR_NO_FILE) return $oldImage;
    $file = $_FILES['images'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Ảnh bị lỗi hoặc vượt quá 3 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif', 'image/webp'=>'webp'];
    $mime = $info['mime'] ?? '';
    if (!isset($extensions[$mime])) throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG, GIF hoặc WEBP.');
    $folder = __DIR__ . '/uploads';
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Không tạo được thư mục uploads.');
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) throw new RuntimeException('Không lưu được ảnh.');
    removeImage($oldImage);
    return 'uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) message('Phiên làm việc hết hạn, hãy thử lại.');
    try {
        $action = (string)($_POST['action'] ?? '');
        $id = filter_var($_POST['admin_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
        if ($action === 'save') {
            $email = trim((string)($_POST['admin_email'] ?? ''));
            $password = (string)($_POST['admin_password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email không hợp lệ.');
            $oldImage = null;
            $oldHash = null;
            if ($id) {
                $find = $pdo->prepare('SELECT images, admin_password FROM Users WHERE admin_id = ?');
                $find->execute([$id]);
                $old = $find->fetch();
                if (!$old) throw new RuntimeException('Không tìm thấy người dùng cần sửa.');
                $oldImage = $old['images'];
                $oldHash = $old['admin_password'];
            }
            if (!$id && strlen($password) < 6) throw new RuntimeException('Mật khẩu cần ít nhất 6 ký tự.');
            $image = uploadImage($oldImage);
            if ($id) {
                $storedPassword = $password !== '' ? $password : $oldHash;
                $stmt = $pdo->prepare('UPDATE Users SET admin_email = ?, admin_password = ?, images = ? WHERE admin_id = ?');
                $stmt->execute([$email, $storedPassword, $image, $id]);
                message('Cập nhật người dùng thành công.');
            }
            $stmt = $pdo->prepare('INSERT INTO Users (admin_email, admin_password, images) VALUES (?, ?, ?)');
            $stmt->execute([$email, $password, $image]);
            message('Thêm người dùng thành công.');
        }
        if ($action === 'delete' && $id) {
            $find = $pdo->prepare('SELECT images FROM Users WHERE admin_id = ?');
            $find->execute([$id]);
            $user = $find->fetch();
            if (!$user) throw new RuntimeException('Không tìm thấy người dùng cần xóa.');
            $stmt = $pdo->prepare('DELETE FROM Users WHERE admin_id = ?');
            $stmt->execute([$id]);
            removeImage($user['images']);
            message('Xóa người dùng thành công.');
        }
        throw new RuntimeException('Thao tác không hợp lệ.');
    } catch (Throwable $error) {
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') message('Email đã tồn tại hoặc dữ liệu bị trùng.');
        message($error instanceof PDOException ? 'Có lỗi database. Hãy kiểm tra lại cấu hình.' : $error->getMessage());
    }
}

$editing = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $stmt = $pdo->prepare('SELECT admin_id, admin_email, images FROM Users WHERE admin_id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$users = $pdo->query(
    'SELECT admin_id, admin_email, admin_password, images
     FROM Users
     ORDER BY admin_id ASC'
)->fetchAll();$notice = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị người dùng</title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
<main>
    <h1>Quản trị người dùng</h1>
    <p><a href="index.php">Xem trang frontend</a></p>
    <?php if ($notice !== ''): ?><p class="message"><?php echo h($notice); ?></p><?php endif; ?>
    <form action="admin.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="admin_id" value="<?php echo h((string)($editing['admin_id'] ?? '')); ?>">
        <fieldset>
            <legend><?php echo $editing ? 'Sửa người dùng' : 'Thêm người dùng'; ?></legend>
            <label for="admin_email">Email:</label>
            <input class="textbox" id="admin_email" name="admin_email" type="email" maxlength="255" required value="<?php echo h($editing['admin_email'] ?? ''); ?>">
            <br>
            <label for="admin_password">Mật khẩu:</label>
            <input class="textbox" id="admin_password" name="admin_password" type="password" <?php echo $editing ? '' : 'required minlength="6"'; ?> autocomplete="new-password" placeholder="<?php echo $editing ? 'Để trống nếu không đổi' : 'Ít nhất 6 ký tự'; ?>">
            <br>
            <label for="images">Ảnh:</label>
            <input class="filebox" id="images" name="images" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
            <br>
            <p class="hint">Ảnh JPG/PNG/GIF/WEBP, tối đa 3 MB. Ảnh lưu trong uploads, database lưu đường dẫn.</p>
            <?php if (!empty($editing['images'])): ?><p class="current-image">Ảnh hiện tại:<br><img src="<?php echo h($editing['images']); ?>" alt="Ảnh người dùng"></p><?php endif; ?>
        </fieldset>
        <input type="submit" value="<?php echo $editing ? 'Lưu thay đổi' : 'Thêm người dùng'; ?>">
        <?php if ($editing): ?> <a class="cancel" href="admin.php">Hủy sửa</a><?php endif; ?>
    </form>

    <h2>Dữ liệu bảng Users</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Mã</th><th>Email</th><th>Mật khẩu</th><th>Ảnh</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo (int)$user['admin_id']; ?></td>
                <td><?php echo h($user['admin_email']); ?></td>
                <td class="password-hash"><?php echo h($user['admin_password']); ?></td>                <td><?php if ($user['images']): ?><img class="product-image" src="<?php echo h($user['images']); ?>" alt="Ảnh người dùng"><?php else: ?>Chưa có ảnh<?php endif; ?></td>
                <td class="row-actions"><a href="?edit=<?php echo (int)$user['admin_id']; ?>">Sửa</a>
                    <form action="admin.php" method="post" onsubmit="return confirm('Bạn có chắc muốn xóa người dùng này?');">
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="admin_id" value="<?php echo (int)$user['admin_id']; ?>">
                        <input type="submit" value="Xóa">
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="5">Chưa có dữ liệu.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</main>
</body>
</html>
