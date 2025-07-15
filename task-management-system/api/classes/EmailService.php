<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php'; // Make sure PHPMailer is installed via Composer
require_once __DIR__ . '/../config/Config.php';

class EmailService {
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    private function getEmailTemplate($title, $content, $actionText = null, $actionUrl = null, $type = 'task') {
        $brandColor = '#5a3c11';
        $accentColor = '#516d45';
        $backgroundColor = '#f8f9fa';
        $textColor = '#2d1b0a';
        $lightTextColor = '#6a543c';
        
        $actionButton = '';
        if ($actionText && $actionUrl) {
            $actionButton = '
            <tr>
                <td style="padding: 24px 0;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center">
                                <a href="' . $actionUrl . '" style="
                                    display: inline-block;
                                    background: linear-gradient(135deg, ' . $brandColor . ' 0%, #3d2410 100%);
                                    color: #ffffff;
                                    text-decoration: none;
                                    padding: 16px 32px;
                                    border-radius: 8px;
                                    font-weight: 600;
                                    font-size: 16px;
                                    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;
                                    box-shadow: 0 4px 12px rgba(90, 60, 17, 0.3);
                                    transition: all 0.3s ease;
                                ">' . $actionText . '</a>
                            </td>
                        </tr>
                    </table>
                </td>';
        }
        
        // Remove icon and just use text header
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $title . '</title>
            <style>
                @media only screen and (max-width: 600px) {
                    .email-container {
                        width: 100% !important;
                        margin: 0 !important;
                        padding: 16px !important;
                    }
                    .email-content {
                        padding: 24px 16px !important;
                    }
                    .header-icon {
                        font-size: 48px !important;
                    }
                    .email-title {
                        font-size: 24px !important;
                        line-height: 1.3 !important;
                    }
                    .task-details {
                        padding: 16px !important;
                    }
                    .task-detail-row {
                        flex-direction: column !important;
                        align-items: flex-start !important;
                        gap: 8px !important;
                    }
                    .task-label {
                        font-size: 14px !important;
                    }
                    .task-value {
                        font-size: 16px !important;
                    }
                }
            </style>
        </head>
        <body style="
            margin: 0;
            padding: 0;
            background-color: ' . $backgroundColor . ';
            font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;
            line-height: 1.6;
            color: ' . $textColor . ';
        ">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: ' . $backgroundColor . ';">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table class="email-container" width="600" cellpadding="0" cellspacing="0" style="
                            background: #ffffff;
                            border-radius: 16px;
                            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                            overflow: hidden;
                            max-width: 600px;
                            margin: 0 auto;">
                            <!-- Header -->
                            <tr>
                                <td class="email-content" style="
                                    background: linear-gradient(135deg, ' . $brandColor . ' 0%, #3d2410 100%);
                                    padding: 40px 32px;
                                    text-align: center;
                                ">
                                    <h1 class="email-title" style="
                                        color: #ffffff;
                                        font-size: 28px;
                                        font-weight: 700;
                                        margin: 0;
                                        letter-spacing: -0.025em;
                                    ">Task Management System</h1>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td class="email-content" style="padding: 40px 32px;">
                                    ' . $content . '
                                </td>
                            </tr>
                            <!-- Action Button -->
                            ' . $actionButton . '
                            <!-- Footer -->
                            <tr>
                                <td style="
                                    background-color: #f8f9fa;
                                    padding: 32px;
                                    text-align: center;
                                    border-top: 1px solid #e9ecef;
                                ">
                                    <p style="
                                        color: ' . $lightTextColor . ';
                                        font-size: 14px;
                                        margin: 0 0 16px 0;
                                        font-weight: 500;
                                    ">Task Management System</p>
                                    <p style="
                                        color: ' . $lightTextColor . ';
                                        font-size: 12px;
                                        margin: 0;
                                        opacity: 0.8;
                                    ">This email was sent automatically. Please do not reply to this email.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    private function getTaskDetailsHTML($task) {
        $brandColor = '#5a3c11';
        $accentColor = '#516d45';
        
        return '
        <div class="task-details" style="
            background: #f8f9fa;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            border-left: 4px solid ' . $brandColor . ';
        ">
            <div class="task-detail-row" style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #e9ecef;
            ">
                <span class="task-label" style="
                    color: ' . $brandColor . ';
                    font-weight: 600;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                ">Task Title</span>
                <span class="task-value" style="
                    color: #2d1b0a;
                    font-weight: 600;
                    font-size: 16px;
                ">' . htmlspecialchars($task['title']) . '</span>
            </div>
            
            <div class="task-detail-row" style="
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #e9ecef;
            ">
                <span class="task-label" style="
                    color: ' . $brandColor . ';
                    font-weight: 600;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                ">Description</span>
                <span class="task-value" style="
                    color: #2d1b0a;
                    font-weight: 400;
                    font-size: 16px;
                    text-align: right;
                    max-width: 60%;
                ">' . htmlspecialchars($task['description'] ?: 'No description provided') . '</span>
            </div>
            
            <div class="task-detail-row" style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #e9ecef;
            ">
                <span class="task-label" style="
                    color: ' . $brandColor . ';
                    font-weight: 600;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                ">Deadline</span>
                <span class="task-value" style="
                    color: #2d1b0a;
                    font-weight: 600;
                    font-size: 16px;
                ">' . htmlspecialchars($task['deadline'] ?: 'No deadline set') . '</span>
            </div>
            
            <div class="task-detail-row" style="
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <span class="task-label" style="
                    color: ' . $brandColor . ';
                    font-weight: 600;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                ">Assigned By</span>
                <span class="task-value" style="
                    color: #2d1b0a;
                    font-weight: 600;
                    font-size: 16px;
                ">' . htmlspecialchars($task['assigned_by_name']) . '</span>
            </div>
        </div>';
    }

    public function sendTaskAssignmentEmail($email, $task) {
        $mail = new PHPMailer(true);
        try {
            // Get email configuration from environment
            $emailConfig = $this->config->getEmailConfig();
            $appConfig = $this->config->getAppConfig();
            
            // SMTP settings
            $mail->isSMTP();
            $mail->Host       = $emailConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $emailConfig['username'];
            $mail->Password   = $emailConfig['password'];
            $mail->SMTPSecure = $emailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $emailConfig['port'];

            // Recipients
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "New Task Assigned: " . $task['title'];
            
            $content = '
            <p style="
                color: #2d1b0a;
                font-size: 18px;
                font-weight: 500;
                margin: 0 0 24px 0;
                line-height: 1.6;
            ">Hello! You have been assigned a new task. Please review the details below and take action as needed.</p>
            
            ' . $this->getTaskDetailsHTML($task) . '
            
            <p style="
                color: #6a543c;
                font-size: 16px;
                margin: 24px 0 0 0;
                font-weight: 500;
            ">Please log in to your dashboard to view and manage this task.</p>';
            
            $mail->Body = $this->getEmailTemplate(
                'New Task Assigned',
                $content,
                'View Task',
                $appConfig['url'], // Use configured app URL
                'task'
            );

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendTaskStatusUpdateEmail($email, $task) {
        $mail = new PHPMailer(true);
        try {
            // Get email configuration from environment
            $emailConfig = $this->config->getEmailConfig();
            $appConfig = $this->config->getAppConfig();
            
            $mail->isSMTP();
            $mail->Host       = $emailConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $emailConfig['username'];
            $mail->Password   = $emailConfig['password'];
            $mail->SMTPSecure = $emailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $emailConfig['port'];

            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "✅ Task Status Updated: " . $task['title'];
            
            $statusColor = '#516d45';
            if ($task['status'] === 'Pending') $statusColor = '#fdcb6e';
            if ($task['status'] === 'In Progress') $statusColor = '#74b9ff';
            
            $content = '
            <p style="
                color: #2d1b0a;
                font-size: 18px;
                font-weight: 500;
                margin: 0 0 24px 0;
                line-height: 1.6;
            ">A task status has been updated. Here are the current details:</p>
            
            ' . $this->getTaskDetailsHTML($task) . '
            
            <div style="
                background: ' . $statusColor . ';
                color: #ffffff;
                padding: 12px 20px;
                border-radius: 8px;
                text-align: center;
                margin: 24px 0;
            ">
                <strong>Current Status: ' . htmlspecialchars($task['status']) . '</strong>
            </div>
            
            <p style="
                color: #6a543c;
                font-size: 16px;
                margin: 24px 0 0 0;
                font-weight: 500;
            ">Updated by: ' . htmlspecialchars($task['assigned_to_name']) . '</p>';
            
            $mail->Body = $this->getEmailTemplate(
                'Task Status Updated',
                $content,
                'View Task',
                $appConfig['url'], // Use configured app URL
                'status'
            );

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendCustomEmail($email, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // Get email configuration from environment
            $emailConfig = $this->config->getEmailConfig();
            $appConfig = $this->config->getAppConfig();
            
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = 'error_log';
            $mail->isSMTP();
            $mail->Host       = $emailConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $emailConfig['username'];
            $mail->Password   = $emailConfig['password'];
            $mail->SMTPSecure = $emailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $emailConfig['port'];

            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->getEmailTemplate(
                'System Notification',
                $body,
                null,
                null,
                'notification'
            );

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function sendDeadlineReminderEmail($email, $task) {
        $mail = new PHPMailer(true);
        try {
            // Get email configuration from environment
            $emailConfig = $this->config->getEmailConfig();
            $appConfig = $this->config->getAppConfig();
            
            // SMTP settings
            $mail->isSMTP();
            $mail->Host       = $emailConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $emailConfig['username'];
            $mail->Password   = $emailConfig['password'];
            $mail->SMTPSecure = $emailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $emailConfig['port'];

            // Recipients
            $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "⏰ Deadline Reminder: " . $task['title'];
            
            $content = '
            <div style="
                background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
                border: 2px solid #fdcb6e;
                border-radius: 12px;
                padding: 20px;
                margin: 24px 0;
                text-align: center;
            ">
                <h3 style="
                    color: #d63031;
                    margin: 0 0 12px 0;
                    font-size: 20px;
                    font-weight: 700;
                ">⚠️ Deadline Approaching</h3>
                <p style="
                    color: #2d1b0a;
                    font-size: 16px;
                    font-weight: 600;
                    margin: 0;
                ">Your task deadline is within 24 hours!</p>
            </div>
            
            <p style="
                color: #2d1b0a;
                font-size: 18px;
                font-weight: 500;
                margin: 0 0 24px 0;
                line-height: 1.6;
            ">This is a friendly reminder that your task deadline is approaching. Please review the details below and take action if needed.</p>
            
            ' . $this->getTaskDetailsHTML($task) . '
            
            <p style="
                color: #d63031;
                font-size: 16px;
                font-weight: 600;
                margin: 24px 0 0 0;
                text-align: center;
            ">⏰ Deadline: ' . htmlspecialchars($task['deadline']) . '</p>';
            
            $mail->Body = $this->getEmailTemplate(
                'Deadline Reminder',
                $content,
                'View Task',
                $appConfig['url'], // Use configured app URL
                'reminder'
            );

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}