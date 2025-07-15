<?php
require_once __DIR__ . '/classes/Task.php';
require_once __DIR__ . '/classes/EmailService.php';

// For CLI/cron use only
$taskObj = new Task();
$emailService = new EmailService();

$tasksDueSoon = $taskObj->getTasksDueSoon(24);

foreach ($tasksDueSoon as $task) {
    $email = $task['assigned_to_email'];
    if ($email) {
        $sent = $emailService->sendDeadlineReminderEmail($email, $task);
        if ($sent) {
            $taskObj->markReminderSent($task['id']);
            echo "Reminder sent for task ID {$task['id']} to {$email}\n";
        } else {
            echo "Failed to send reminder for task ID {$task['id']} to {$email}\n";
        }
    }
} 