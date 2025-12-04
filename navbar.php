<?php
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$role_id = $user['role_id'];

// Fetch user's privileges
$stmt = $pdo->prepare("SELECT p.privilege_id, p.privilege_name 
    FROM role_privilege rp 
    JOIN privilege p ON rp.privilege_id = p.privilege_id 
    WHERE rp.role_id = ?");
$stmt->execute([$role_id]);
$privileges = [];
while ($row = $stmt->fetch()) {
    $privileges[$row['privilege_id']] = $row['privilege_name'];
}

// Fetch latest 10 notifications for this user
$stmt = $pdo->prepare("SELECT activity, created_at FROM activity_logs WHERE activity LIKE 'NOTICE:%' AND activity LIKE ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute(["%to_user_id:{$_SESSION['user_id']}%"]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = count($notifications);
?>

<nav class="navbar navbar-expand-lg">
    <span class="menu-toggle" onclick="toggleSidebar()">
        <img src="logo.png" alt="BRGYCare Logo" class="logo">
    </span>
    <a class="navbar-brand" href="dashboard.php">BRGYCare</a>
    <div class="ml-auto d-flex align-items-center position-relative">
        <!-- Notification Bell (all logic in one!) -->
        <div class="notification-wrapper" style="position: relative; margin-right: 15px;">
            <span class="notification-bell" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="notification-badge"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </span>
            <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
                <div class="notification-header">
                    <h6>Notifications</h6>
                </div>
                <div id="notificationList" class="notification-list">
                    <?php
                    if (empty($notifications)) {
                        echo '<div style="padding: 30px; text-align: center; color: #718096;">
                                <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No notifications yet
                                </div>';
                    } else {
                        foreach ($notifications as $notif) {
                            $content = substr($notif['activity'], 0, strpos($notif['activity'], ' to_user_id:'));
                            $time = date('M d, Y h:i A', strtotime($notif['created_at']));
                            echo "<div class='notification-item'>
                                    <div>" . htmlspecialchars($content) . "</div>
                                    <div class='notification-time'>{$time}</div>
                                </div>";
                        }
                    }
                    ?>
                </div>
                <div class="notification-footer">
                    <a href="all_notifications.php">View All Notifications</a>
                </div>
            </div>
        </div>
        <!-- Profile Dropdown -->
        <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" aria-expanded="false">
                <i class="far fa-user"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right" style="background: rgba(43, 108, 176, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <a class="dropdown-item" href="profile.php" style="color: #fff;">Profile</a>
                <?php if (isset($privileges[10]) && $privileges[10] == 'access_family'): ?>
                    <a class="dropdown-item" href="family_members.php" style="color: #fff;">Family Members</a>
                <?php endif; ?>
                <a class="dropdown-item" href="settings.php" style="color: #fff;">Settings</a>
                <a class="dropdown-item" href="logout.php" style="color: #fff;">Logout</a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleNotifications() {
    $('#notificationDropdown').toggle();
}
$(document).click(function(e) {
    if (!$(e.target).closest('.notification-wrapper').length) {
        $('#notificationDropdown').hide();
    }
});
</script>
<style>
.notification-bell { color: #fff; padding: 10px; cursor: pointer; transition: color 0.2s; position: relative; display: inline-block; font-size: 1.2rem; }
.notification-bell:hover { color: #e2e8f0; }
.notification-badge { position: absolute; top: 5px; right: 5px; background: #e53e3e; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; min-width: 18px; text-align: center; }
.notification-dropdown { position: absolute; top: 100%; right: 0; width: 350px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); z-index: 1060; margin-top: 10px; max-height: 450px; overflow: hidden; display: flex; flex-direction: column; }
.notification-header { padding: 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f7fafc; }
.notification-header h6 { margin: 0; color: #1a202c; font-weight: 600; }
.notification-list { max-height: 350px; overflow-y: auto; }
.notification-item { padding: 12px 15px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s ease; }
.notification-item:hover { background: #f7fafc; }
.notification-time { font-size: 0.75rem; color: #718096; margin-top: 4px; }
.notification-footer { padding: 10px 15px; border-top: 1px solid #e2e8f0; text-align: center; background: #f7fafc; }
.notification-footer a { color: #2b6cb0; text-decoration: none; font-size: 0.9rem; }
.notification-footer a:hover { text-decoration: underline; }
.logo { max-width: 50px; border-radius: 100px; margin-bottom: 30px; }
@media (max-width: 768px) {
    .notification-dropdown { width: 300px; right: -50px; }
}
</style>
