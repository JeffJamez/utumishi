<?php
define('UTUMISHI_WEB_APP', true);

session_start();

require_once __DIR__ . '/../../includes/config/constants.php';
require_once __DIR__ . '/../../includes/core/db.php';
require_once __DIR__ . '/../../includes/core/auth.php';
require_once __DIR__ . '/../../includes/classes/User.php';

requireRole(ROLE_OCS);

$currentUser = getCurrentUser();
$stationId = $currentUser['station_id'];

// Handle form submissions
if ($_POST) {
    try {
        $db = getDB();
        
        if ($_POST['action'] === 'create_event') {
            $eventData = [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'station_id' => $stationId,
                'date_time' => $_POST['date'] . ' ' . $_POST['time'],
                'location' => $_POST['location'],
                'officer_ids' => isset($_POST['officer_ids']) ? implode(',', $_POST['officer_ids']) : '',
                'created_by' => $currentUser['id'],
                'status' => 'scheduled'
            ];
            
            $db->insert('events', $eventData);
            $_SESSION['success'] = 'Event created successfully';
        }
        
        if ($_POST['action'] === 'update_status') {
            $db->update('events', 
                ['status' => $_POST['new_status']], 
                'id = :event_id AND station_id = :station_id',
                ['event_id' => $_POST['event_id'], 'station_id' => $stationId]
            );
            $_SESSION['success'] = 'Event status updated';
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Operation failed: ' . $e->getMessage();
    }
}

// Initialize variables
$events = [];
$officers = [];
$upcomingEvents = [];
$pastEvents = [];
$error = '';

try {
    $db = getDB();
    
    // Get all events for this station
    $events = $db->fetchAll("
        SELECT 
            e.*,
            u.name as created_by_name,
            CASE 
                WHEN e.date_time > NOW() THEN 'upcoming'
                WHEN e.date_time <= NOW() AND e.status != 'completed' THEN 'ongoing'
                ELSE 'past'
            END as event_status
        FROM events e
        JOIN users u ON e.created_by = u.id
        WHERE e.station_id = :station_id
        ORDER BY e.date_time DESC
    ", ['station_id' => $stationId]);
    
    // Separate events by status
    foreach ($events as $event) {
        if ($event['event_status'] === 'upcoming' || $event['event_status'] === 'ongoing') {
            $upcomingEvents[] = $event;
        } else {
            $pastEvents[] = $event;
        }
    }
    
    // Get officers for assignment
    $officers = $db->fetchAll("
        SELECT o.id, u.name, o.badge_number
        FROM officers o
        JOIN users u ON o.user_id = u.id
        WHERE u.station_id = :station_id AND u.is_active = 1
        ORDER BY u.name
    ", ['station_id' => $stationId]);
    
} catch (Exception $e) {
    error_log("Events Error: " . $e->getMessage());
    $error = "Unable to load events data";
}

$pageTitle = "Community Events";
require_once __DIR__ . '/../../includes/layout/layout.php';

?>
   <main class="app-main">
        <div class="mb-4">
            <h1>Community Events</h1>
            <p class="text-muted">Plan and manage community engagement activities</p>
        </div>

        <!-- Event Statistics -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-value"><?php echo count($upcomingEvents); ?></div>
                <div class="kpi-label">Upcoming Events</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo count($pastEvents); ?></div>
                <div class="kpi-label">Past Events</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo count($officers); ?></div>
                <div class="kpi-label">Available Officers</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo count(array_filter($events, function($e) { return $e['status'] === 'completed'; })); ?></div>
                <div class="kpi-label">Completed Events</div>
            </div>
        </div>

        <!-- Create New Event -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Create New Event</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_event">
                    
                    <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label for="title" class="form-label">Event Title</label>
                            <input type="text" name="title" id="title" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="location" class="form-label">Location</label>
                            <input type="text" name="location" id="location" class="form-control" required>
                        </div>
                        
                        <div>
                            <label for="date" class="form-label">Date</label>
                            <input type="date" name="date" id="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div>
                            <label for="time" class="form-label">Time</label>
                            <input type="time" name="time" id="time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <label for="officer_ids" class="form-label">Assign Officers</label>
                        <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem;">
                            <?php foreach ($officers as $officer): ?>
                                <label class="form-check">
                                    <input type="checkbox" name="officer_ids[]" value="<?php echo $officer['id']; ?>" class="form-check-input">
                                    <?php echo htmlspecialchars($officer['name']); ?> (<?php echo htmlspecialchars($officer['badge_number']); ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Create Event</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Upcoming Events</h3>
                <span class="badge status-info"><?php echo count($upcomingEvents); ?> scheduled</span>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingEvents)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Assigned Officers</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($event['description']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($event['date_time'])); ?><br>
                                            <small><?php echo date('H:i A', strtotime($event['date_time'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td>
                                            <?php if ($event['officer_ids']): ?>
                                                <?php
                                                $assignedIds = explode(',', $event['officer_ids']);
                                                $assignedNames = [];
                                                foreach ($officers as $officer) {
                                                    if (in_array($officer['id'], $assignedIds)) {
                                                        $assignedNames[] = $officer['name'];
                                                    }
                                                }
                                                echo htmlspecialchars(implode(', ', $assignedNames));
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">No officers assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo $event['status'] === 'scheduled' ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <select name="new_status" class="form-control form-control-sm d-inline w-auto" onchange="this.form.submit()">
                                                    <option value="scheduled" <?php echo $event['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <div style="font-size: 3rem;">📅</div>
                        <h4>No Upcoming Events</h4>
                        <p class="text-muted">No events scheduled. Create one above to engage with the community.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Events -->
        <div class="card">
            <div class="card-header">
                <h3>Past Events</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($pastEvents)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pastEvents, 0, 10) as $event): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($event['description']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($event['date_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['location']); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $event['status'] === 'completed' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($pastEvents) > 10): ?>
                        <div class="text-center mt-3">
                            <small class="text-muted">Showing recent 10 of <?php echo count($pastEvents); ?> past events</small>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center p-4">
                        <p class="text-muted">No past events recorded.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </main>
    </div>
    
    <script src="<?php echo ASSETS_URL; ?>/js/validation.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 300000);
        
        // Set minimum date to today
        document.getElementById('date').min = new Date().toISOString().split('T')[0];
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const selectedDate = new Date(document.getElementById('date').value + ' ' + document.getElementById('time').value);
            const now = new Date();
            
            if (selectedDate <= now) {
                alert('Please select a future date and time for the event.');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>