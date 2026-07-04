<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

requireAdminLogin();
requirePermission('manage_admins');

$db = Database::getInstance();
$currentAdmin = getCurrentAdmin();
$isSuperAdmin = $currentAdmin['role'] === 'super_admin';

$targetId = (int) ($_GET['id'] ?? 0);
$target = null;

if ($targetId) {
    $stmt = $db->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch() ?: null;

    if (!$target) {
        redirectWithMessage('admins.php', 'المشرف المطلوب غير موجود', 'danger');
    }

    // لا يمكن لغير المدير العام تعديل حساب مدير عام آخر
    if ($target['role'] === 'super_admin' && !$isSuperAdmin) {
        redirectWithMessage('admins.php', 'ليس لديك صلاحية تعديل حساب مدير عام', 'danger');
    }
}

$isEdit = $target !== null;
$isSelf = $isEdit && (int) $target['id'] === (int) $currentAdmin['id'];
$allPermissions = getAllPermissions();
$currentPermissions = $isEdit ? getAdminPermissions((int) $target['id']) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'حدث خطأ في التحقق من الطلب';
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $requestedRole = $_POST['role'] ?? 'admin';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $permissions = $_POST['permissions'] ?? [];

        if (empty($username)) {
            $errors[] = 'اسم المستخدم مطلوب';
        } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
            $errors[] = 'اسم المستخدم يجب أن يحتوي على حروف إنجليزية وأرقام و(_ .) فقط، بحد أدنى 3 أحرف';
        }

        if (!$isEdit && strlen($password) < 8) {
            $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        } elseif ($isEdit && !empty($password) && strlen($password) < 8) {
            $errors[] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
        }

        // منع ترقية أي حساب لمدير عام إلا من قِبل مدير عام آخر
        if ($requestedRole === 'super_admin' && !$isSuperAdmin) {
            $requestedRole = 'admin';
        }
        if (!in_array($requestedRole, ['super_admin', 'admin', 'editor'], true)) {
            $requestedRole = 'admin';
        }

        // منع المستخدم من تعديل دوره الخاص أو تعطيل حسابه لتفادي فقدان الوصول للوحة التحكم
        if ($isSelf) {
            $requestedRole = $target['role'];
            $isActive = 1;
        }

        if (empty($errors)) {
            $checkStmt = $db->prepare('SELECT id FROM admins WHERE username = ? AND id != ?');
            $checkStmt->execute([$username, $targetId]);
            if ($checkStmt->fetch()) {
                $errors[] = 'اسم المستخدم مستخدم بالفعل، الرجاء اختيار اسم آخر';
            }
        }

        // ضمان بقاء مدير عام نشط واحد على الأقل في النظام
        if (empty($errors) && $isEdit && $target['role'] === 'super_admin' && ($requestedRole !== 'super_admin' || !$isActive)) {
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'super_admin' AND is_active = 1 AND id != " . (int) $target['id']);
            if ((int) $countStmt->fetch()['cnt'] === 0) {
                $errors[] = 'لا يمكن إجراء هذا التعديل لأنه سيترك النظام بدون أي مدير عام نشط';
            }
        }

        if (empty($errors)) {
            if ($isEdit) {
                $fields = ['username = ?', 'full_name = ?', 'email = ?', 'role = ?', 'is_active = ?'];
                $params = [$username, $fullName, $email, $requestedRole, $isActive];

                if (!empty($password)) {
                    $fields[] = 'password = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $params[] = $target['id'];
                $stmt = $db->prepare('UPDATE admins SET ' . implode(', ', $fields) . ' WHERE id = ?');
                $stmt->execute($params);

                if ($requestedRole !== 'super_admin') {
                    updateAdminPermissions((int) $target['id'], $permissions);
                }

                logActivity('update_admin', 'تعديل بيانات المشرف: ' . $username);
                redirectWithMessage('admins.php', 'تم تحديث بيانات المشرف بنجاح');
            } else {
                $stmt = $db->prepare('INSERT INTO admins (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $requestedRole, $isActive]);
                $newId = (int) $db->lastInsertId();

                if ($requestedRole !== 'super_admin') {
                    updateAdminPermissions($newId, $permissions);
                }

                logActivity('create_admin', 'إنشاء مشرف جديد: ' . $username);
                redirectWithMessage('admins.php', 'تم إضافة المشرف بنجاح');
            }
        }
    }
}

$selectedRole = $target['role'] ?? ($_POST['role'] ?? 'admin');

$pageTitle = $isEdit ? 'تعديل مشرف' : 'إضافة مشرف جديد';
$breadcrumb = 'الرئيسية / المشرفون / ' . $pageTitle;
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?php foreach ($errors as $err) echo e($err) . '<br>'; ?></div>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div class="card">
            <div class="card-header"><span class="card-title">البيانات الأساسية</span></div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">اسم المستخدم <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" required dir="ltr"
                           value="<?= e($target['username'] ?? $_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($target['full_name'] ?? $_POST['full_name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="<?= e($target['email'] ?? $_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <?= $isEdit ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور' ?>
                    <?= !$isEdit ? '<span class="required">*</span>' : '' ?>
                </label>
                <div class="password-input-wrapper" style="position:relative;">
                    <input type="password" name="password" class="form-control" style="padding-left:46px;"
                           <?= !$isEdit ? 'required' : '' ?>
                           placeholder="<?= $isEdit ? 'اتركه فارغاً للإبقاء على كلمة المرور الحالية' : '8 أحرف على الأقل' ?>">
                    <button type="button" class="password-toggle" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--color-silver-muted);cursor:pointer;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">الدور الوظيفي</label>
                    <select name="role" class="form-control" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php if ($isSuperAdmin): ?>
                            <option value="super_admin" <?= $selectedRole === 'super_admin' ? 'selected' : '' ?>>مدير عام (كل الصلاحيات)</option>
                        <?php endif; ?>
                        <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>مشرف</option>
                        <option value="editor" <?= $selectedRole === 'editor' ? 'selected' : '' ?>>محرر</option>
                    </select>
                    <?php if ($isSelf): ?>
                        <input type="hidden" name="role" value="<?= e($target['role']) ?>">
                        <p class="form-hint">لا يمكنك تغيير دورك الوظيفي الخاص</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">الحالة</label>
                    <div class="toggle-label-row" style="padding-top:8px;border-bottom:none;">
                        <div class="toggle-label-text"><strong>حساب نشط</strong></div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="is_active" <?= (!$isEdit || $target['is_active']) ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">الصلاحيات</span></div>

            <?php if ($selectedRole === 'super_admin'): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>حساب المدير العام يملك تلقائياً كافة الصلاحيات على النظام بأكمله</span>
                </div>
            <?php else: ?>
                <p class="form-hint" style="margin-bottom:14px;">حدد الصلاحيات التي يمكن لهذا المشرف الوصول إليها في لوحة التحكم</p>
                <div class="permissions-grid">
                    <?php foreach ($allPermissions as $key => $perm): ?>
                        <div class="permission-item">
                            <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="perm_<?= e($key) ?>"
                                   <?= in_array($key, $currentPermissions, true) ? 'checked' : '' ?>>
                            <label for="perm_<?= e($key) ?>">
                                <strong style="display:block;"><?= e($perm['label']) ?></strong>
                                <span style="font-size:0.78rem;color:var(--color-silver-muted);"><?= e($perm['description']) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> <?= $isEdit ? 'حفظ التعديلات' : 'إضافة المشرف' ?></button>
        <a href="admins.php" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> إلغاء</a>
    </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
