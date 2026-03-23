<?php
/**
 * Activity Logging Helper
 * Logs staff actions into the staff_activity_log table.
 * 
 * Usage:
 *   require_once __DIR__ . '/activity_log.php';
 *   log_activity(db(), 'delivery', 'reservation', $reservationId, 'Delivered reservation #123 to John');
 */

function log_activity(
    PDO $pdo,
    string $action,
    string $entityType = '',
    int $entityId = 0,
    string $description = ''
): void {
    $user = $_SESSION['user'] ?? null;
    if (!$user)
        return;

    $userId = (int) $user['id'];
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO staff_activity_log (user_id, action, entity_type, entity_id, description)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $entityType ?: null,
            $entityId ?: null,
            $description ?: null,
        ]);
    } catch (Throwable $e) {
        app_log('ERROR', 'Activity log insert failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        // Silently fail — logging should never break the main workflow
    }
}
