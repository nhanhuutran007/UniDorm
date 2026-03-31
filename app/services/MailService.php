<?php
/**
 * UniDorm – MailService
 * Gửi email qua PHPMailer (Gmail SMTP + App Password)
 *
 * SENDER: unidorm.tdtu@gmail.com
 * APP PASSWORD: ufrv rrnv qqua wepv  (từ README – không có dấu cách)
 *
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

class MailService
{

  private string $senderEmail = '52300235@student.tdtu.edu.vn';
  private string $senderName = 'Ký túc xá TDTU';
  private string $appPassword = 'ufrvrrn vqquawepv';  // Thay khoảng trắng sau khi cài

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
      $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
        . "From: {$this->senderName} <{$this->senderEmail}>\r\n";
      return @mail($toEmail, $subject, $htmlBody, $headers);
    }

    $mail = new PHPMailer(true);
    try {
      // Server settings
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = $this->senderEmail;
      $mail->Password = str_replace(' ', '', $this->appPassword);  // Xóa khoảng trắng
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;
      $mail->CharSet = 'UTF-8';

      // Recipients
      $mail->setFrom($this->senderEmail, $this->senderName);
      $mail->addAddress($toEmail, $toName);
      $mail->addReplyTo('52300235@student.tdtu.edu.vn', 'Ký túc xá TDTU – No Reply');

      // Content
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $htmlBody;
      $mail->AltBody = strip_tags($htmlBody);

      $mail->send();
      return true;
    } catch (Exception $e) {
      error_log("MailService Error [{$toEmail}]: " . $mail->ErrorInfo);
      return false;
    }
  }

  // ─── Email Templates ──────────────────────────────────────────────
  private function baseTemplate(string $title, string $body): string
  {
    return <<<HTML
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px;">
<table width="100%" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <tr><td style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:32px 40px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:22px;font-weight:700;">🏠 UniDorm</h1>
    <p style="color:#bfdbfe;margin:6px 0 0;font-size:13px;">Hệ thống Ký túc xá – Đại học Tôn Đức Thắng</p>
  </td></tr>
  <tr><td style="padding:36px 40px;">{$body}</td></tr>
  <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;border-top:1px solid #e5e7eb;">
    <p style="color:#9ca3af;font-size:11px;margin:0;">© 2026 UniDorm – TDTU &nbsp;|&nbsp; Đừng reply email này</p>
  </td></tr>
</table></td></tr></table>
</body></html>
HTML;
  }

  private function templateActivation(string $name, string $url): string
  {
    $body = <<<HTML
<p style="color:#374151;font-size:15px;">Xin chào <strong>{$name}</strong>,</p>
<p style="color:#6b7280;font-size:14px;line-height:1.6;">Tài khoản sinh viên tại UniDorm đã được tạo.
Hãy nhấn nút bên dưới để đặt mật khẩu và kích hoạt tài khoản của bạn.</p>
<div style="text-align:center;margin:32px 0;">
  <a href="{$url}" style="background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block;">
    🔑 Đặt mật khẩu ngay
  </a>
</div>
<p style="color:#9ca3af;font-size:12px;">Link có hiệu lực trong <strong>24 giờ</strong>. Nếu bạn không yêu cầu điều này, hãy bỏ qua email này.</p>
HTML;
    return $this->baseTemplate('Kích hoạt tài khoản UniDorm', $body);
  }

  private function templateReset(string $name, string $url): string
  {
    $body = <<<HTML
<p style="color:#374151;font-size:15px;">Xin chào <strong>{$name}</strong>,</p>
<p style="color:#6b7280;font-size:14px;line-height:1.6;">Bạn (hoặc quản trị viên) đã yêu cầu đặt lại mật khẩu cho tài khoản UniDorm.</p>
<div style="text-align:center;margin:32px 0;">
  <a href="{$url}" style="background:#dc2626;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block;">
    🔐 Đặt lại mật khẩu
  </a>
</div>
<p style="color:#9ca3af;font-size:12px;">Link có hiệu lực trong <strong>1 giờ</strong>. Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
HTML;
    return $this->baseTemplate('Đặt lại mật khẩu UniDorm', $body);
  }

  private function templateNotif(string $name, string $title, string $message): string
  {
    $body = <<<HTML
<p style="color:#374151;font-size:15px;">Xin chào <strong>{$name}</strong>,</p>
<div style="background:#eff6ff;border-left:4px solid #2563eb;border-radius:0 8px 8px 0;padding:16px 20px;margin:20px 0;">
  <h3 style="color:#1d4ed8;margin:0 0 8px;font-size:16px;">{$title}</h3>
  <p style="color:#374151;margin:0;font-size:14px;line-height:1.6;">{$message}</p>
</div>
<p style="color:#9ca3af;font-size:12px;">Đây là thông báo tự động từ hệ thống UniDorm.</p>
HTML;
    return $this->baseTemplate($title, $body);
  }
}
