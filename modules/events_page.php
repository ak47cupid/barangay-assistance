<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../sms/sms_gateway.php';
function render_events_page(array $roles): void
{
    $user = require_role($roles);
    $canPublish = $user['role_name'] !== 'Administrator';
    $page = str_contains($_SERVER['REQUEST_URI'], '/staff/') ? '/staff/events.php' : '/admin/events.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!$canPublish) {
            flash('danger', 'Barangay staff add events.');
            redirect($page);
        }
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate = trim($_POST['event_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($title === '' || $description === '' || $eventDate === '') {
            flash('danger', 'Enter an event title, description, and date.');
            redirect($page);
        }
        $stmt = db()->prepare('INSERT INTO events (title, description, event_date, venue, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $eventDate, $location, $user['user_id']]);
        $eventId = (int)db()->lastInsertId();
        if (isset($_POST['send_sms'])) {
            $targets = db()->query("SELECT u.user_id, u.phone_number FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.status='Active' AND r.role_name='Senior Citizen'")->fetchAll();
            $message = 'New event: ' . $title . ' on ' . date('M j, Y g:i A', strtotime($eventDate));
            if ($location !== '') $message .= ' at ' . $location;
            foreach ($targets as $target) {
                send_sms((int)$target['user_id'], $target['phone_number'], $message);
            }
        }
        audit_log((int)$user['user_id'], 'Created event', 'events', $eventId);
        flash('success', 'Event saved.');
        redirect($page);
    }
    $rows = db()->query('SELECT e.*, u.full_name FROM events e JOIN users u ON u.user_id = e.created_by ORDER BY e.event_date DESC')->fetchAll();
    require_once __DIR__ . '/../includes/header.php'; require_once __DIR__ . '/../includes/sidebar.php';
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="page-title mb-0">Events</h1>
        <?php if ($canPublish): ?><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#event-modal">New event</button><?php endif; ?>
    </div>
    <?php if (!$canPublish): ?><p class="text-muted">Barangay staff add events. This page shows every event.</p><?php endif; ?>
    <?php if ($canPublish): ?>
    <div class="modal fade" id="event-modal" tabindex="-1" aria-labelledby="event-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5" id="event-modal-title">New event</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body row g-3">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="col-12"><label class="form-label" for="event-title">Title</label><input class="form-control" id="event-title" name="title" required></div>
                    <div class="col-12"><label class="form-label" for="event-description">Details</label><textarea class="form-control" id="event-description" name="description" rows="5" required></textarea></div>
                    <div class="col-md-6"><label class="form-label" for="event-location">Location</label><input class="form-control" id="event-location" name="location"></div>
                    <div class="col-md-6"><label class="form-label" for="event-date">Date and time</label><input class="form-control" id="event-date" type="datetime-local" name="event_date" required></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="send_sms" id="event-send-sms"><label class="form-check-label" for="event-send-sms">Send SMS to active senior citizens</label></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Post</button></div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <div class="panel table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Event</th><th>Date</th><th>Location</th><th>Created By</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['title']) ?></strong><div><?= e($r['description']) ?></div></td>
                    <td><?= e($r['event_date']) ?></td>
                    <td><?= e($r['venue']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
}
