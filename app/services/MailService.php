<?php
/**
 * UniDorm – MailService
 * Gửi email qua PHPMailer (Gmail SMTP + App Password)
 *
 * Cấu hình đọc từ file .env (根目录)
 * Cài PHPMailer: composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Autoload Composer nếu chưa load
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
  $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
  }
}

function loadEnv(string $key, string $default = ''): string {
  static $env = null;
  if ($env === null) {
    $env = [];
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (file_exists($envFile)) {
      foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
          [$k, $v] = explode('=', $line, 2);
          $env[trim($k)] = trim($v);
        }
      }
    }
  }
  return $env[$key] ?? $default;
}

class MailService
{
  public string $lastError = '';

  private string $senderEmail;
  private string $senderName;
  private string $appPassword;
  private string $smtpHost;
  private int    $smtpPort;

  public function __construct()
  {
    $this->senderEmail = loadEnv('MAIL_SENDER_EMAIL', '52300235@student.tdtu.edu.vn');
    $this->senderName  = loadEnv('MAIL_SENDER_NAME', 'UniDorm – Ký túc xá TDTU');
    $this->appPassword = loadEnv('MAIL_APP_PASSWORD', '');
    $this->smtpHost    = loadEnv('MAIL_SMTP_HOST', 'smtp.gmail.com');
    $this->smtpPort    = (int) loadEnv('MAIL_SMTP_PORT', '587');
  }

  // Phân loại email templates
  public function sendActivation(string $toEmail, string $toName, string $activationUrl): bool
  {
    $subject = '[UniDorm] Kích hoạt tài khoản của bạn';
    $body = $this->templateActivation($toName, $activationUrl);
    return $this->send($toEmail, $toName, $subject, $body);
  }

  public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): bool
  {
    $subject = '[UniDorm] Đặt lại mật khẩu';
    $body = $this->templateReset($toName, $resetUrl);
    return $this->send($toEmail, $toName, $subject, $body);
  }

  public function sendNotification(string $toEmail, string $toName, string $title, string $message): bool
  {
    $subject = "[UniDorm] $title";
    $body = $this->templateNotif($toName, $title, $message);
    return $this->send($toEmail, $toName, $subject, $body);
  }

  // ─── Core send ───────────────────────────────────────────────────
  public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
  {
    // Fallback: nếu PHPMailer chưa cài, dùng mail()
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
      $this->lastError = 'PHPMailer class not found – using PHP mail() fallback';
      $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
        . "From: {$this->senderName} <{$this->senderEmail}>\r\n";
      return @mail($toEmail, $subject, $htmlBody, $headers);
    }

    $mail = new PHPMailer(true);
    try {
      // Server settings
      $mail->isSMTP();
      $mail->Host = $this->smtpHost;
      $mail->SMTPAuth = true;
      $mail->Username = $this->senderEmail;
      $mail->Password = str_replace(' ', '', $this->appPassword);
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = $this->smtpPort;
      $mail->CharSet = 'UTF-8';

      // Recipients
      $mail->setFrom($this->senderEmail, $this->senderName);
      $mail->addAddress($toEmail, $toName);
      $mail->addReplyTo($this->senderEmail, 'Ký túc xá TDTU – No Reply');

      // Content
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $htmlBody;
      $mail->AltBody = strip_tags($htmlBody);

      $mail->send();
      return true;
    } catch (Exception $e) {
      $this->lastError = $mail->ErrorInfo . ' | ' . $e->getMessage();
      error_log("MailService Error [{$toEmail}]: " . $this->lastError);
      return false;
    }
  }

  // ─── Email Templates ──────────────────────────────────────────────
  private function baseTemplate(string $title, string $body): string
  {
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px;">
  {$body}
  <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
  <p>Chúc bạn có một ngày tốt lành!</p>
  <p>Trân trọng,<br><strong>Trần Hữu Nhân</strong></p>
</body>
</html>
HTML;
  }

  private function templateActivation(string $name, string $url): string
  {
    $body = <<<HTML
<p>Xin chào <strong>{$name}</strong>,</p>
<p>Tài khoản sinh viên tại hệ thống UniDorm của bạn đã được khởi tạo.</p>
<p>Vui lòng nhấn vào liên kết bên dưới để đặt mật khẩu và kích hoạt tài khoản:</p>
<p><a href="{$url}">{$url}</a></p>
<p><strong>Lưu ý:</strong> Liên kết này có hiệu lực trong vòng 24 giờ. Nếu bạn không yêu cầu điều này, vui lòng bỏ qua email.</p>
HTML;
    return $this->baseTemplate('Kích hoạt tài khoản UniDorm', $body);
  }

  private function templateReset(string $name, string $url): string
  {
    $body = <<<HTML
<p>Xin chào <strong>{$name}</strong>,</p>
<p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản UniDorm của bạn.</p>
<p>Vui lòng nhấn vào liên kết bên dưới để tiến hành thay đổi mật khẩu:</p>
<p><a href="{$url}">{$url}</a></p>
<p><strong>Lưu ý:</strong> Liên kết có hiệu lực trong vòng 1 giờ. Nếu bạn không thực hiện yêu cầu này, vui lòng bảo mật tài khoản.</p>
HTML;
    return $this->baseTemplate('Đặt lại mật khẩu UniDorm', $body);
  }

  private function templateNotif(string $name, string $title, string $message): string
  {
    $body = <<<HTML
<p>Xin chào <strong>{$name}</strong>,</p>
<p>Bạn có thông báo mới với tiêu đề: <strong>{$title}</strong></p>
<p>Nội dung thông báo:</p>
<p style="background: #fdfdfd; border: 1px solid #eee; padding: 10px;">{$message}</p>
<p><strong>Lưu ý:</strong> Đây là email thông báo tự động, nếu có vấn đề cần giải quyết vui lòng Reply lại email này.</p>
HTML;
    return $this->baseTemplate($title, $body);
  }
}
