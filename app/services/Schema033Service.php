<?php
// app/services/Schema033Service.php
// Staff attendance (login/logout) and owner broadcast notifications.

class Schema033Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (!SchemaHelper::tableExists($db, 'staff_attendance')) {
            try {
                $db->exec(
                    "CREATE TABLE staff_attendance (
                        id            INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id     INT NOT NULL,
                        user_id       INT NOT NULL,
                        session_date  DATE NOT NULL,
                        login_at      DATETIME NOT NULL,
                        logout_at     DATETIME NULL,
                        login_status  ENUM('on_time','late') NOT NULL DEFAULT 'on_time',
                        logout_status ENUM('on_time','early','open') NOT NULL DEFAULT 'open',
                        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_att_tenant_date (tenant_id, session_date),
                        KEY idx_att_user (user_id, login_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $log[] = 'Created staff_attendance';
            } catch (Throwable $e) {
                $log[] = 'staff_attendance: ' . $e->getMessage();
            }
        }

        if (!SchemaHelper::tableExists($db, 'staff_notifications')) {
            try {
                $db->exec(
                    "CREATE TABLE staff_notifications (
                        id                 INT AUTO_INCREMENT PRIMARY KEY,
                        tenant_id          INT NOT NULL,
                        created_by_user_id INT NOT NULL,
                        template_type      ENUM('late','memo','notice') NOT NULL DEFAULT 'memo',
                        title              VARCHAR(160) NOT NULL,
                        body               TEXT NOT NULL,
                        created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_sn_tenant (tenant_id, created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $log[] = 'Created staff_notifications';
            } catch (Throwable $e) {
                $log[] = 'staff_notifications: ' . $e->getMessage();
            }
        }

        if (!SchemaHelper::tableExists($db, 'staff_notification_reads')) {
            try {
                $db->exec(
                    "CREATE TABLE staff_notification_reads (
                        id              INT AUTO_INCREMENT PRIMARY KEY,
                        notification_id INT NOT NULL,
                        user_id         INT NOT NULL,
                        read_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_sn_read (notification_id, user_id),
                        KEY idx_snr_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $log[] = 'Created staff_notification_reads';
            } catch (Throwable $e) {
                $log[] = 'staff_notification_reads: ' . $e->getMessage();
            }
        }

        SchemaHelper::clearCache();
        return ['ok' => true, 'log' => $log];
    }
}
