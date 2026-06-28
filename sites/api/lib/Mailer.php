<?php
/**
 * Mailer - Envoi d'emails HTML via PHP mail()
 *
 * Fallback simple et robuste pour les hébergements mutualisés (Gandi, OVH, etc.)
 * où SendGrid n'est pas configuré.
 */

function send_html_email(
    string $to,
    string $subject,
    string $htmlBody,
    ?string $fromEmail = null,
    ?string $fromName = null,
    ?string $replyTo = null
): bool {
    $config    = getAppConfig();
    $fromEmail = $fromEmail ?: ($config['mail_from'] ?? 'noreply@webiartisan.prigent.tech');
    $fromName  = $fromName ?: ($config['from_name'] ?? 'WebIArtisan');

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $encodedFrom    = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n") . ' <' . $fromEmail . '>';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $encodedFrom,
        'X-Mailer: PHP/' . phpversion(),
    ];

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $extraParams = '-f' . $fromEmail;

    return mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers), $extraParams);
}
