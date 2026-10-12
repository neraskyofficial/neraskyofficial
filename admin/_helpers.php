<?php
declare(strict_types=1);

/*
| Shared helpers for the isolated admin app (port 8898).
|
| bootstrap.php deliberately does NOT include the marketplace's auth.php
| (separate sessions = separate security), so the render helpers that live
| there are re-provided here in admin flavour:
|   - no guest-privacy branch: moderators always see full member details
|   - avatar photo lookup resolves the PERSON's id (user_id / seller_id /
|     buyer_id preferred over the row's own id) so the memoised
|     avatar_file_for() can never attach the wrong user's photo
*/

if (!function_exists('admin_counts')) {
    /** Pending-review counters for the dashboard cards and tab labels. */
    function admin_counts(): array
    {
        return [
            'users'       => (int) row("SELECT COUNT(*) c FROM users WHERE account_status = 'pending'")['c'],
            'consents'    => (int) row("SELECT COUNT(*) c FROM users WHERE is_minor = 1 AND consent_status = 'submitted'")['c'],
            'services'    => (int) row("SELECT COUNT(*) c FROM services WHERE status = 'pending'")['c'],
            'payments'    => (int) row(
                "SELECT
                    (SELECT COUNT(*) FROM payments WHERE status = 'pending')
                    + (SELECT COUNT(*) FROM paymongo_checkouts WHERE status = 'pending') c"
            )['c'],
            'cash_ins'    => (int) row("SELECT COUNT(*) c FROM cash_in_requests WHERE status = 'pending'")['c'],
            'withdrawals' => (int) row("SELECT COUNT(*) c FROM withdrawals WHERE status = 'pending'")['c'],
            'applications' => (int) row("SELECT COUNT(*) c FROM admin_applications WHERE status = 'pending'")['c'],
        ];
    }
}

if (!function_exists('admin_subnav')) {
    /** Horizontal section nav shared by every admin page. */
    function admin_subnav(string $active): string
    {
        $items = [
            'index'       => ['Overview', 'index.php'],
            'users'       => ['Accounts', 'users.php'],
            'services'    => ['Services', 'services.php'],
            'payments'    => ['Payments', 'payments.php'],
            'withdrawals' => ['Payouts', 'withdrawals.php'],
            'orders'      => ['Orders', 'orders.php'],
            'staff'       => ['Staff', 'staff.php'],
            'tasks'       => ['Tasks', 'tasks.php'],
            'reports'     => ['Reports', 'reports.php'],
            'audit'       => ['Audit log', 'audit.php'],
            'support'     => ['Support', 'support.php'],
        ];
        $out = '<nav class="admin-subnav">';
        $counts = admin_counts();
        $admin = admin_user();
        foreach ($items as $key => [$label, $href]) {
            if ($admin && !admin_can($admin, $key === 'index' ? 'overview' : $key)) {
                continue;
            }
            if ($key === 'payments' && $counts['payments'] > 0) {
                $label .= ' (' . $counts['payments'] . ')';
            } elseif ($key === 'withdrawals' && $counts['withdrawals'] > 0) {
                $label .= ' (' . $counts['withdrawals'] . ')';
            } elseif ($key === 'staff' && $counts['applications'] > 0) {
                $label .= ' (' . $counts['applications'] . ')';
            }
            $cls = $key === $active ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
            $out .= '<a class="' . $cls . '" href="' . e(base_url($href)) . '">' . e($label) . '</a>';
        }
        return $out . '</nav>';
    }
}

if (!function_exists('avatar_of')) {
    /**
     * Frame-aware avatar for a member/row array.
     * Accepts full user rows, or joined rows (services, withdrawals, orders)
     * — the photo lookup resolves the member's id, never the row's own id.
     */
    function avatar_of(array $u, int $size = 40): string
    {
        $uid   = (int) ($u['user_id'] ?? $u['seller_id'] ?? $u['buyer_id'] ?? $u['id'] ?? 0);
        $file  = $u['avatar_file'] ?? avatar_file_for($uid);
        $inner = avatar_html((string) ($u['full_name'] ?? ''), $size, $file);
        $frame = (string) ($u['profile_frame'] ?? 'none');
        if ($frame === 'none' || $frame === '') {
            return $inner;
        }

        $hex = profile_accent_hex((string) ($u['profile_accent'] ?? 'indigo'));
        return '<span class="avwrap avf-' . e($frame) . '" style="--avc:' . $hex . ';--avs:' . $size . 'px">' . $inner . '</span>';
    }

    if (!function_exists('admin_presence_badge')) {
        function admin_presence_badge(array $user): string
        {
            $labels = ['online' => 'Online', 'away' => 'Away', 'dnd' => 'Do not disturb', 'offline' => 'Offline'];
            $status = (string) ($user['profile_status'] ?? 'offline');
            if (!isset($labels[$status])) {
                $status = 'offline';
            }
            return '<span class="admin-presence admin-presence-' . e($status) . '"><i></i>' . e($labels[$status]) . '</span>';
        }
    }
}
