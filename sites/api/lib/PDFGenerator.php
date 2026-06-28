<?php
/**
 * PDF Generator using HTML to PDF conversion
 * Simple and reliable approach for invoices/quotes
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFGenerator {
    
    public static function generateDevisPDF(array $devis, array $client, array $brand, string $plan = 'free'): string {
        $html = self::getDevisHTML($devis, $client, $brand, $plan);
        return self::convertHTMLToPDF($html, 'devis-' . $devis['numero']);
    }
    
    public static function generateFacturePDF(array $facture, array $client, array $brand, string $plan = 'free'): string {
        $html = self::getFactureHTML($facture, $client, $brand, $plan);
        return self::convertHTMLToPDF($html, 'facture-' . $facture['numero']);
    }
    
    private static function getDevisHTML($devis, $client, $brand, string $plan = 'free'): string {
        $totalHT = $devis['total_ht'] ?? 0;
        $tvaRate = $devis['tva_rate'] ?? 20;
        $tva = $totalHT * ($tvaRate / 100);
        $totalTTC = $totalHT + $tva;
        
        // Build full address from structured fields or fallback to old format
        $addressStreet = $brand['contact']['address_street'] ?? $brand['address'] ?? '';
        $addressZip = $brand['contact']['address_zip'] ?? '';
        $addressCity = $brand['contact']['address_city'] ?? '';
        $addressCountry = $brand['contact']['address_country'] ?? 'France';
        
        $fullAddress = $addressStreet;
        if ($addressZip || $addressCity) {
            $fullAddress .= ($fullAddress ? ', ' : '') . trim($addressZip . ' ' . $addressCity);
        }
        if ($addressCountry && $addressCountry !== 'France') {
            $fullAddress .= ($fullAddress ? ', ' : '') . $addressCountry;
        }
        
        // Build legal info section
        $legalInfo = '';
        $legal = $brand['legal'] ?? [];
        if (!empty($legal['siret'])) {
            $legalInfo .= '<p>SIRET : ' . htmlspecialchars($legal['siret']) . '</p>';
        }
        if (!empty($legal['vat_number'])) {
            $legalInfo .= '<p>TVA : ' . htmlspecialchars($legal['vat_number']) . '</p>';
        }
        if (!empty($legal['rcs'])) {
            $legalInfo .= '<p>' . htmlspecialchars($legal['rcs']) . '</p>';
        }
        if (!empty($legal['rm'])) {
            $legalInfo .= '<p>' . htmlspecialchars($legal['rm']) . '</p>';
        }
        if (!empty($legal['legal_form'])) {
            $legalInfo .= '<p>Forme juridique : ' . htmlspecialchars($legal['legal_form']) . '</p>';
        }
        
        $lignesHTML = '';
        foreach ($devis['lignes'] ?? [] as $ligne) {
            $ligneTotal = ($ligne['quantite'] ?? 1) * ($ligne['prix_unitaire'] ?? 0);
            $lignesHTML .= '
            <tr>
                <td>' . htmlspecialchars($ligne['description'] ?? '') . '</td>
                <td align="center">' . ($ligne['quantite'] ?? 1) . '</td>
                <td align="right">' . number_format($ligne['prix_unitaire'] ?? 0, 2, ',', ' ') . ' €</td>
                <td align="right">' . number_format($ligneTotal, 2, ',', ' ') . ' €</td>
            </tr>';
        }
        
        $logoHtml = '';
        if (!empty($brand['logo_image_url'])) {
            $logoHtml = '<img src="' . htmlspecialchars($brand['logo_image_url']) . '" style="max-height: 80px; margin-bottom: 10px;">';
        } elseif (!empty($brand['logo_image_path'])) {
            // Local path (assuming it's in the uploads folder)
            $path = __DIR__ . '/../' . $brand['logo_image_path'];
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logoHtml = '<img src="data:image/' . $type . ';base64,' . base64_encode($data) . '" style="max-height: 80px; margin-bottom: 10px;">';
            }
        } elseif (!empty($brand['logo_svg'])) {
            if (strpos($brand['logo_svg'], '<svg') !== false) {
                $logoHtml = '<div style="max-height: 80px; width: auto; margin-bottom: 10px;">' . $brand['logo_svg'] . '</div>';
            } else {
                $logoHtml = '<img src="' . $brand['logo_svg'] . '" style="max-height: 80px; margin-bottom: 10px;">';
            }
        }
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Devis ' . htmlspecialchars($devis['numero'] ?? '') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { display: block; border-bottom: 2px solid ' . ($brand['colors']['primary'] ?? '#00efb7') . '; padding-bottom: 20px; margin-bottom: 30px; }
        .company { float: left; width: 60%; }
        .company h1 { color: ' . ($brand['colors']['primary'] ?? '#00efb7') . '; margin: 0; }
        .company p { margin: 5px 0; color: #666; font-size: 11px; }
        .company .legal-info { margin-top: 10px; font-size: 9px; color: #888; }
        .company .legal-info p { margin: 2px 0; }
        .document-info { float: right; width: 35%; text-align: right; }
        .document-info h2 { color: #333; margin: 0; font-size: 24px; }
        .document-info p { margin: 5px 0; }
        .client-info { clear: both; background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .client-info h3 { margin: 0 0 10px 0; color: #333; }
        .client-info p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        .totals { margin-top: 20px; text-align: right; }
        .totals p { margin: 5px 0; }
        .total-row { font-weight: bold; font-size: 16px; color: ' . ($brand['colors']['primary'] ?? '#00efb7') . '; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
        .footer p { margin: 5px 0; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="header clearfix">
        <div class="company">
            ' . $logoHtml . '
            <h1>' . htmlspecialchars($brand['company_name'] ?? 'Entreprise') . '</h1>
            <p>' . htmlspecialchars($fullAddress) . '</p>
            <p>' . htmlspecialchars($brand['contact']['phone'] ?? '') . '</p>
            <p>' . htmlspecialchars($brand['contact']['email'] ?? '') . '</p>
            ' . ($legalInfo ? '<div class="legal-info">' . $legalInfo . '</div>' : '') . '
        </div>
        <div class="document-info">
            <h2>DEVIS</h2>
            <p><strong>N° :</strong> ' . htmlspecialchars($devis['numero'] ?? '') . '</p>
            <p><strong>Date :</strong> ' . date('d/m/Y', strtotime($devis['created_at'] ?? 'now')) . '</p>
            <p><strong>Statut :</strong> ' . htmlspecialchars($devis['status'] ?? 'draft') . '</p>
        </div>
    </div>
    
    <div class="client-info">
        <h3>CLIENT</h3>
        <p><strong>' . htmlspecialchars(($client['societe'] ?? '') . ' ' . ($client['nom'] ?? '')) . '</strong></p>
        <p>' . htmlspecialchars($client['adresse'] ?? '') . '</p>
        <p>' . htmlspecialchars($client['email'] ?? '') . '</p>
        <p>' . htmlspecialchars($client['telephone'] ?? '') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 80px; text-align: center;">Quantité</th>
                <th style="width: 100px; text-align: right;">Prix Unitaire</th>
                <th style="width: 100px; text-align: right;">Total HT</th>
            </tr>
        </thead>
        <tbody>
            ' . $lignesHTML . '
        </tbody>
    </table>
    
    <div class="totals">
        <p><strong>Total HT :</strong> ' . number_format($totalHT, 2, ',', ' ') . ' €</p>
        <p><strong>TVA (' . $tvaRate . '%) :</strong> ' . number_format($tva, 2, ',', ' ') . ' €</p>
        <p class="total-row"><strong>Total TTC :</strong> ' . number_format($totalTTC, 2, ',', ' ') . ' €</p>
    </div>
    
    <div class="footer">
        <p><strong>Conditions de ce devis :</strong></p>
        <p>- Validité : 1 mois à compter de la date d\'émission</p>
        <p>- Modalités de paiement : 30% d\'acompte, solde à la livraison</p>
        <p>- TVA : ' . $tvaRate . '%</p>
        <p style="margin-top: 15px; text-align: center;">' . self::getFooterBranding($plan) . '</p>
    </div>
</body>
</html>';
    }
    
    private static function getFactureHTML($facture, $client, $brand, string $plan = 'free'): string {
        $totalHT = $facture['total_ht'] ?? 0;
        $tvaRate = $facture['tva_rate'] ?? 20;
        $tva = $totalHT * ($tvaRate / 100);
        $totalTTC = $totalHT + $tva;
        
        // Build full address from structured fields or fallback to old format
        $addressStreet = $brand['contact']['address_street'] ?? $brand['address'] ?? '';
        $addressZip = $brand['contact']['address_zip'] ?? '';
        $addressCity = $brand['contact']['address_city'] ?? '';
        $addressCountry = $brand['contact']['address_country'] ?? 'France';
        
        $fullAddress = $addressStreet;
        if ($addressZip || $addressCity) {
            $fullAddress .= ($fullAddress ? ', ' : '') . trim($addressZip . ' ' . $addressCity);
        }
        if ($addressCountry && $addressCountry !== 'France') {
            $fullAddress .= ($fullAddress ? ', ' : '') . $addressCountry;
        }
        
        // Build legal info section
        $legalInfo = '';
        $legal = $brand['legal'] ?? [];
        if (!empty($legal['siret'])) {
            $legalInfo .= '<p>SIRET : ' . htmlspecialchars($legal['siret']) . '</p>';
        }
        if (!empty($legal['vat_number'])) {
            $legalInfo .= '<p>TVA : ' . htmlspecialchars($legal['vat_number']) . '</p>';
        }
        if (!empty($legal['rcs'])) {
            $legalInfo .= '<p>' . htmlspecialchars($legal['rcs']) . '</p>';
        }
        if (!empty($legal['rm'])) {
            $legalInfo .= '<p>' . htmlspecialchars($legal['rm']) . '</p>';
        }
        if (!empty($legal['legal_form'])) {
            $legalInfo .= '<p>Forme juridique : ' . htmlspecialchars($legal['legal_form']) . '</p>';
        }
        
        $lignesHTML = '';
        foreach ($facture['lignes'] ?? [] as $ligne) {
            $ligneTotal = ($ligne['quantite'] ?? 1) * ($ligne['prix_unitaire'] ?? 0);
            $lignesHTML .= '
            <tr>
                <td>' . htmlspecialchars($ligne['description'] ?? '') . '</td>
                <td align="center">' . ($ligne['quantite'] ?? 1) . '</td>
                <td align="right">' . number_format($ligne['prix_unitaire'] ?? 0, 2, ',', ' ') . ' €</td>
                <td align="right">' . number_format($ligneTotal, 2, ',', ' ') . ' €</td>
            </tr>';
        }
        
        $logoHtml = '';
        if (!empty($brand['logo_image_url'])) {
            $logoHtml = '<img src="' . htmlspecialchars($brand['logo_image_url']) . '" style="max-height: 80px; margin-bottom: 10px;">';
        } elseif (!empty($brand['logo_image_path'])) {
            $path = __DIR__ . '/../' . $brand['logo_image_path'];
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logoHtml = '<img src="data:image/' . $type . ';base64,' . base64_encode($data) . '" style="max-height: 80px; margin-bottom: 10px;">';
            }
        } elseif (!empty($brand['logo_svg'])) {
            if (strpos($brand['logo_svg'], '<svg') !== false) {
                $logoHtml = '<div style="max-height: 80px; width: auto; margin-bottom: 10px;">' . $brand['logo_svg'] . '</div>';
            } else {
                $logoHtml = '<img src="' . $brand['logo_svg'] . '" style="max-height: 80px; margin-bottom: 10px;">';
            }
        }

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture ' . htmlspecialchars($facture['numero'] ?? '') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { display: block; border-bottom: 2px solid ' . ($brand['colors']['primary'] ?? '#00efb7') . '; padding-bottom: 20px; margin-bottom: 30px; }
        .company { float: left; width: 60%; }
        .company h1 { color: ' . ($brand['colors']['primary'] ?? '#00efb7') . '; margin: 0; }
        .company p { margin: 5px 0; color: #666; font-size: 11px; }
        .company .legal-info { margin-top: 10px; font-size: 9px; color: #888; }
        .company .legal-info p { margin: 2px 0; }
        .document-info { float: right; width: 35%; text-align: right; }
        .document-info h2 { color: #333; margin: 0; font-size: 24px; }
        .document-info p { margin: 5px 0; }
        .client-info { clear: both; background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .client-info h3 { margin: 0 0 10px 0; color: #333; }
        .client-info p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; }
        th { background: #f8f9fa; font-weight: bold; }
        .totals { margin-top: 20px; text-align: right; }
        .totals p { margin: 5px 0; }
        .total-row { font-weight: bold; font-size: 16px; color: ' . ($brand['colors']['primary'] ?? '#00efb7') . '; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #666; }
        .footer p { margin: 5px 0; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="header clearfix">
        <div class="company">
            ' . $logoHtml . '
            <h1>' . htmlspecialchars($brand['company_name'] ?? 'Entreprise') . '</h1>
            <p>' . htmlspecialchars($fullAddress) . '</p>
            <p>' . htmlspecialchars($brand['contact']['phone'] ?? '') . '</p>
            <p>' . htmlspecialchars($brand['contact']['email'] ?? '') . '</p>
            ' . ($legalInfo ? '<div class="legal-info">' . $legalInfo . '</div>' : '') . '
        </div>
        <div class="document-info">
            <h2>FACTURE</h2>
            <p><strong>N° :</strong> ' . htmlspecialchars($facture['numero'] ?? '') . '</p>
            <p><strong>Date :</strong> ' . date('d/m/Y', strtotime($facture['created_at'] ?? 'now')) . '</p>
            <p><strong>Statut :</strong> ' . htmlspecialchars($facture['status'] ?? 'draft') . '</p>
        </div>
    </div>
    
    <div class="client-info">
        <h3>CLIENT</h3>
        <p><strong>' . htmlspecialchars(($client['societe'] ?? '') . ' ' . ($client['nom'] ?? '')) . '</strong></p>
        <p>' . htmlspecialchars($client['adresse'] ?? '') . '</p>
        <p>' . htmlspecialchars($client['email'] ?? '') . '</p>
        <p>' . htmlspecialchars($client['telephone'] ?? '') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 80px; text-align: center;">Quantité</th>
                <th style="width: 100px; text-align: right;">Prix Unitaire</th>
                <th style="width: 100px; text-align: right;">Total HT</th>
            </tr>
        </thead>
        <tbody>
            ' . $lignesHTML . '
        </tbody>
    </table>
    
    <div class="totals">
        <p><strong>Total HT :</strong> ' . number_format($totalHT, 2, ',', ' ') . ' €</p>
        <p><strong>TVA (' . $tvaRate . '%) :</strong> ' . number_format($tva, 2, ',', ' ') . ' €</p>
        <p class="total-row"><strong>Total TTC :</strong> ' . number_format($totalTTC, 2, ',', ' ') . ' €</p>
    </div>
    
    <div class="footer">
        <p><strong>Modalités de paiement :</strong></p>
        <p>- Paiement à 30 jours date de facture</p>
        <p>- TVA : ' . $tvaRate . '%</p>
        <p>- En cas de retard, pénalité de 3 fois le taux légal</p>
        <p style="margin-top: 15px; text-align: center;">' . self::getFooterBranding($plan) . '</p>
    </div>
</body>
</html>';
    }
    
    private static function getFooterBranding(string $plan): string {
        $date = date('d/m/Y H:i');
        if ($plan === 'free') {
            return "Propulsé par <strong>WebIArtisan</strong> — webiartisan.prigent.tech — $date";
        }
        return "Document généré le $date";
    }

    private static function convertHTMLToPDF($html, $filename): string {
        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
}
