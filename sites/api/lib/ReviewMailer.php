<?php
/**
 * Review Mailer - Envoi d'emails de demande d'avis avec tracking
 */

class ReviewMailer {
    
    private $apiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $config = getAppConfig();
        $this->apiKey = $config['sendgrid_api_key'] ?? null;
        $this->fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';
        $this->fromName = $config['from_name'] ?? 'WebIArtisan';
    }
    
    /**
     * Envoyer une demande d'avis
     */
    public function sendReviewRequest($toEmail, $subject, $data) {
        if (!$this->apiKey) {
            // Fallback to PHP mail if SendGrid not configured
            return $this->sendWithPHPMail($toEmail, $subject, $this->buildReviewEmailHTML($data), $this->buildReviewEmailText($data));
        }
        
        try {
            $html = $this->buildReviewEmailHTML($data);
            $text = $this->buildReviewEmailText($data);
            
            return $this->sendWithSendGrid($toEmail, $subject, $html, $text);
        } catch (Exception $e) {
            error_log("ReviewMailer error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Construire le HTML de l'email de demande d'avis
     */
    private function buildReviewEmailHTML($data) {
        $clientName = htmlspecialchars($data['client_name'] ?? 'Client');
        $artisanName = htmlspecialchars($data['artisan_name'] ?? 'Votre artisan');
        $customMessage = htmlspecialchars($data['custom_message'] ?? '');
        $trackingPixelUrl = htmlspecialchars($data['tracking_pixel_url'] ?? '');
        $formUrl = htmlspecialchars($data['form_url'] ?? '');
        $reviewUrl = htmlspecialchars($data['review_url'] ?? '');
        $questions = $data['questions'] ?? [];
        $isFollowup = $data['is_followup'] ?? false;
        $followupNumber = $data['followup_number'] ?? 1;
        
        $title = $isFollowup ? "Relance : Votre avis compte !" : "Votre avis compte !";
        $intro = $isFollowup 
            ? "Nous n'avons pas encore eu votre retour sur notre prestation. Votre avis est très important pour nous !" 
            : "Merci pour votre confiance ! Votre expérience nous aide à nous améliorer.";
        
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$title</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00efb7, #004e92); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; }
        .btn { display: inline-block; padding: 15px 30px; background: #00efb7; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 10px 0; transition: all 0.3s; }
        .btn:hover { background: #00d4a6; transform: translateY(-2px); }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
        .questions { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #00efb7; }
        .question { margin: 15px 0; }
        .rating { color: #fbbf24; font-size: 20px; }
        .footer { text-align: center; color: #64748b; font-size: 14px; margin-top: 30px; }
        .followup-badge { background: #f59e0b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>⭐ $title</h1>";
        
        if ($isFollowup) {
            $html .= "<div class='followup-badge'>Relance $followupNumber</div>";
        }
        
        $html .= "</div>
    
    <div class='content'>
        <h2>Bonjour $clientName,</h2>
        
        <p>$intro</p>";
        
        if ($customMessage) {
            $html .= "<div style='background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <strong>Message personnel :</strong><br>
                $customMessage
            </div>";
        }
        
        $html .= "<p>En moins de 2 minutes, partagez votre expérience :</p>";
        
        if (!empty($questions)) {
            $html .= "<div class='questions'>
                <h3>📝 Questions que vous pourrez répondre :</h3>";
            
            foreach ($questions as $q) {
                $questionText = htmlspecialchars($q['question'] ?? '');
                $isRequired = $q['required'] ?? false;
                $requiredMark = $isRequired ? ' *' : '';
                
                $html .= "<div class='question'>
                    <strong>$questionText$requiredMark</strong>";
                
                if ($q['type'] === 'rating') {
                    $html .= "<br><span class='rating'>⭐⭐⭐⭐⭐</span>";
                } else {
                    $html .= "<br><em>(Réponse textuelle)</em>";
                }
                
                $html .= "</div>";
            }
            
            $html .= "</div>";
        }
        
        $html .= "<div style='text-align: center; margin: 30px 0;'>";
        
        if ($formUrl) {
            $html .= "<a href='$formUrl' class='btn' style='margin-right: 10px;'>📝 Remplir l'avis</a>";
        }
        
        if ($reviewUrl) {
            $html .= "<a href='$reviewUrl' class='btn btn-secondary'>⭐ Aller sur Google</a>";
        }
        
        $html .= "</div>
        
        <p><small>* Les questions avec un astérisque sont obligatoires</small></p>
        
        <div class='footer'>
            <p>Cordialement,<br>L'équipe de $artisanName</p>
            <p><small>Si vous ne souhaitez plus recevoir ces emails, merci de nous le signaler.</small></p>
        </div>
    </div>";
        
        if ($trackingPixelUrl) {
            $html .= "<img src='$trackingPixelUrl' width='1' height='1' style='display:none;' alt=''>";
        }
        
        $html .= "</body>
</html>";
        
        return $html;
    }
    
    /**
     * Construire le texte brut de l'email
     */
    private function buildReviewEmailText($data) {
        $clientName = $data['client_name'] ?? 'Client';
        $artisanName = $data['artisan_name'] ?? 'Votre artisan';
        $customMessage = $data['custom_message'] ?? '';
        $formUrl = $data['form_url'] ?? '';
        $reviewUrl = $data['review_url'] ?? '';
        $questions = $data['questions'] ?? [];
        $isFollowup = $data['is_followup'] ?? false;
        
        $title = $isFollowup ? "Relance : Votre avis compte !" : "Votre avis compte !";
        $intro = $isFollowup 
            ? "Nous n'avons pas encore eu votre retour sur notre prestation. Votre avis est très important pour nous !" 
            : "Merci pour votre confiance ! Votre expérience nous aide à nous améliorer.";
        
        $text = "$title\n\n";
        $text .= "Bonjour $clientName,\n\n";
        $text .= "$intro\n\n";
        
        if ($customMessage) {
            $text .= "Message personnel :\n$customMessage\n\n";
        }
        
        $text .= "En moins de 2 minutes, partagez votre expérience :\n\n";
        
        if (!empty($questions)) {
            $text .= "Questions que vous pourrez répondre :\n";
            foreach ($questions as $q) {
                $questionText = $q['question'] ?? '';
                $isRequired = $q['required'] ?? false;
                $requiredMark = $isRequired ? ' *' : '';
                $text .= "- $questionText$requiredMark\n";
            }
            $text .= "\n";
        }
        
        if ($formUrl) {
            $text .= "📝 Remplir l'avis : $formUrl\n";
        }
        
        if ($reviewUrl) {
            $text .= "⭐ Aller sur Google : $reviewUrl\n";
        }
        
        $text .= "\nLes questions avec un astérisque sont obligatoires\n\n";
        $text .= "Cordialement,\nL'équipe de $artisanName\n\n";
        $text .= "Si vous ne souhaitez plus recevoir ces emails, merci de nous le signaler.";
        
        return $text;
    }
    
    /**
     * Envoyer avec SendGrid
     */
    private function sendWithSendGrid($to, $subject, $html, $text) {
        $url = 'https://api.sendgrid.com/v3/mail/send';
        
        $data = [
            'personalizations' => [[
                'to' => [['email' => $to]],
                'subject' => $subject
            ]],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'content' => [
                ['type' => 'text/html', 'value' => $html],
                ['type' => 'text/plain', 'value' => $text]
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 202;
    }
    
    /**
     * Fallback avec PHP mail
     */
    private function sendWithPHPMail($to, $subject, $html, $text) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion(),
        ];
        
        $encodedSubject = mb_encode_mimeheader($subject, "UTF-8");
        return mail($to, $encodedSubject, $html, implode("\r\n", $headers), "-f" . $this->fromEmail);
    }
}
