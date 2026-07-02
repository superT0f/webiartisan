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

    $logCtx = sprintf(
        "[MAILER] to=%s from=%s subject=%s headers=%s extra=%s",
        $to,
        $encodedFrom,
        $encodedSubject,
        str_replace("\r\n", ' | ', implode("\r\n", $headers)),
        $extraParams
    );
    error_log($logCtx);

    $result = @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers), $extraParams);

    $lastError = error_get_last();
    error_log(sprintf(
        "[MAILER] result=%s error=%s",
        $result ? 'OK' : 'FAIL',
        $lastError ? ($lastError['message'] ?? 'unknown') : 'none'
    ));

    return $result;
}
