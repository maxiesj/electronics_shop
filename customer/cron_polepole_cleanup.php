<?php
declare(strict_types=1);
include __DIR__ . '/../db.php';

date_default_timezone_set('Africa/Nairobi');
$now = new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
$requestedSlot = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--slot=') === 0) {
        $requestedSlot = substr($arg, 7);
    }
}
// Days 21-25 use 13:00 only; days 26-30 use 09:00 and 18:00.
$validSlots = ['09:00', '13:00', '18:00'];
if (!in_array($requestedSlot, $validSlots, true)) {
    fwrite(STDERR, "A valid --slot of 09:00, 13:00, or 18:00 is required." . PHP_EOL);
    exit(1);
}
$slot = $requestedSlot;

$conn->query("CREATE TABLE IF NOT EXISTS polepole_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    reminder_date DATE NOT NULL,
    reminder_slot ENUM('09:00','13:00','18:00') NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'in_app',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_plan_slot (plan_id, reminder_date, reminder_slot),
    KEY user_lookup (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE polepole_reminders MODIFY reminder_slot ENUM('09:00','13:00','18:00') NOT NULL");

$sql = "SELECT l.id, l.order_id, l.user_id, l.balance_remaining, l.created_at, u.fullname
        FROM layaway_plans l
        JOIN users u ON u.id = l.user_id
        WHERE l.balance_remaining > 0.009 AND LOWER(TRIM(l.status)) = 'active'";
$result = $conn->query($sql);
// INSERT IGNORE works with the unique plan/date/slot key to keep scheduler reruns idempotent.
$insert = $conn->prepare("INSERT IGNORE INTO polepole_reminders
    (plan_id, user_id, order_id, reminder_date, reminder_slot, channel, message)
    VALUES (?, ?, ?, ?, ?, 'in_app', ?)");

$created = 0;
while ($plan = $result->fetch_assoc()) {
    $started = new DateTimeImmutable($plan['created_at'], new DateTimeZone('Africa/Nairobi'));
    $due = $started->modify('+30 days');
    $elapsedSeconds = $now->getTimestamp() - $started->getTimestamp();
    $planDay = (int)floor($elapsedSeconds / 86400) + 1;

    if ($now < $started || $now > $due || $planDay < 21 || $planDay > 30) {
        continue;
    }

    $slotIsAllowed = ($planDay >= 21 && $planDay <= 25 && $slot === '13:00')
        || ($planDay >= 26 && $planDay <= 30 && in_array($slot, ['09:00', '18:00'], true));
    if (!$slotIsAllowed) {
        continue;
    }

    $balance = number_format((float)$plan['balance_remaining'], 2);
    $dueText = $due->format('d M Y');
    $message = "Hello {$plan['fullname']}, a friendly ADONAK ELECTRONICS reminder: order #{$plan['order_id']} has a Lipa Pole Pole balance of KES {$balance}, due by {$dueText}. You may pay any amount toward it before the deadline. Thank you.";
    $today = $now->format('Y-m-d');
    $insert->bind_param('iiisss', $plan['id'], $plan['user_id'], $plan['order_id'], $today, $slot, $message);
    $insert->execute();
    if ($insert->affected_rows === 1) {
        $created++;
    }
}
echo "Lipa Pole Pole day-sequence {$slot} run completed; {$created} new reminder(s) queued." . PHP_EOL;