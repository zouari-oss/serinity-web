<?php

declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Twig\Environment;

final readonly class MailerService
{
    public function __construct(
        private Environment $twig,
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $encryption,
        private string $fromEmail,
        private string $fromName,
    ) {
    }

    public function sendTemplateHtmlEmail(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $plainText = null,
    ): void {
        $html = $this->twig->render($template, $context);
        $text = $plainText ?? trim(strip_tags($html));

        $mailer = $this->buildMailer();
        $mailer->setFrom($this->fromEmail, $this->fromName);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = $text;

        try {
            $mailer->send();
        } catch (PHPMailerException $exception) {
            throw new \RuntimeException(sprintf('Failed to send email to "%s".', $to), previous: $exception);
        }
    }

    private function buildMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $this->host;
        $mailer->Port = $this->port;
        $mailer->SMTPAuth = $this->username !== '' && $this->password !== '';
        $mailer->Username = $this->username;
        $mailer->Password = $this->password;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->isHTML(true);

        $encryption = strtolower(trim($this->encryption));
        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        return $mailer;
    }
}
