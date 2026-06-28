<?php
/**
 * Simple PDF Generator for WebIArtisan
 * Native PHP PDF generation without external dependencies
 */

class SimplePDF {
    private $page;
    private $data;
    private $y;
    private $pageHeight = 297; // A4 height in mm
    private $pageWidth = 210;  // A4 width in mm
    private $margin = 20;
    private $fontSize = 12;
    private $lineHeight = 6;

    public function __construct() {
        $this->page = 1;
        $this->y = $this->pageHeight - $this->margin;
        $this->data = '';
    }

    public function createDevisPDF(array $devis, array $client, array $brand): string {
        $this->data = $this->getPDFHeader();
        $this->addDevisHeader($devis, $brand);
        $this->addClientInfo($client);
        $this->addDevisLignes($devis['lignes'] ?? []);
        $this->addDevisFooter($devis, $brand);
        $this->data .= $this->getPDFEOF();
        return $this->data;
    }

    public function createFacturePDF(array $facture, array $client, array $brand): string {
        $this->data = $this->getPDFHeader();
        $this->addFactureHeader($facture, $brand);
        $this->addClientInfo($client);
        $this->addFactureLignes($facture['lignes'] ?? []);
        $this->addFactureFooter($facture, $brand);
        $this->data .= $this->getPDFEOF();
        return $this->data;
    }

    private function getPDFHeader(): string {
        return "%PDF-1.4\n" .
               "1 0 obj\n" .
               "<<\n" .
               "/Type /Catalog\n" .
               "/Pages 2 0 R\n" .
               ">>\n" .
               "endobj\n" .
               "2 0 obj\n" .
               "<<\n" .
               "/Type /Pages\n" .
               "/Kids [3 0 R]\n" .
               "/Count 1\n" .
               ">>\n" .
               "endobj\n" .
               "3 0 obj\n" .
               "<<\n" .
               "/Type /Page\n" .
               "/Parent 2 0 R\n" .
               "/MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}]\n" .
               "/Contents 4 0 R\n" .
               "/Resources <<\n" .
               "/Font <<\n" .
               "/F1 5 0 R\n" .
               ">>\n" .
               ">>\n" .
               ">>\n" .
               "endobj\n" .
               "4 0 obj\n" .
               "<<\n" .
               "/Length " . strlen($this->getPageContent()) . "\n" .
               ">>\n" .
               "stream\n" .
               $this->getPageContent() .
               "endstream\n" .
               "endobj\n" .
               "5 0 obj\n" .
               "<<\n" .
               "/Type /Font\n" .
               "/Subtype /Type1\n" .
               "/BaseFont /Helvetica\n" .
               ">>\n" .
               "endobj\n";
    }

    private function getPageContent(): string {
        $content = "BT\n/F1 {$this->fontSize} Tf\n";
        
        // This will be filled by the specific methods
        return $content . "ET\n";
    }

    private function addText($x, $y, $text, $size = 12) {
        $this->data .= str_replace("ET\n", "ET\n" . 
            sprintf("%.2f %.2f Td\n", $x, $y) . 
            "/F1 {$size} Tf\n" . 
            "(" . $this->escapeText($text) . ") Tj\n", 
            $this->data);
    }

    private function escapeText($text) {
        return str_replace(['(', ')', '\\', "\n", "\r"], ['\\(', '\\)', '\\\\', '\\n', '\\r'], $text);
    }

    private function addDevisHeader($devis, $brand) {
        $this->addText($this->margin, $this->y, $brand['company_name'] ?? 'Entreprise', 16);
        $this->y -= 10;
        $this->addText($this->margin, $this->y, $brand['address'] ?? 'Adresse', 10);
        $this->y -= 8;
        $this->addText($this->margin, $this->y, $brand['contact']['phone'] ?? 'Téléphone', 10);
        $this->y -= 8;
        $this->addText($this->margin, $this->y, $brand['contact']['email'] ?? 'Email', 10);
        
        // Right side - Devis info
        $this->y = $this->pageHeight - $this->margin;
        $this->addText($this->pageWidth - 100, $this->y, "DEVIS", 20);
        $this->y -= 15;
        $this->addText($this->pageWidth - 100, $this->y, "N°: " . ($devis['numero'] ?? ''), 12);
        $this->y -= 8;
        $this->addText($this->pageWidth - 100, $this->y, "Date: " . date('d/m/Y', strtotime($devis['created_at'] ?? 'now')), 10);
        $this->y -= 20;
    }

    private function addFactureHeader($facture, $brand) {
        $this->addText($this->margin, $this->y, $brand['company_name'] ?? 'Entreprise', 16);
        $this->y -= 10;
        $this->addText($this->margin, $this->y, $brand['address'] ?? 'Adresse', 10);
        $this->y -= 8;
        $this->addText($this->margin, $this->y, $brand['contact']['phone'] ?? 'Téléphone', 10);
        $this->y -= 8;
        $this->addText($this->margin, $this->y, $brand['contact']['email'] ?? 'Email', 10);
        
        // Right side - Facture info
        $this->y = $this->pageHeight - $this->margin;
        $this->addText($this->pageWidth - 100, $this->y, "FACTURE", 20);
        $this->y -= 15;
        $this->addText($this->pageWidth - 100, $this->y, "N°: " . ($facture['numero'] ?? ''), 12);
        $this->y -= 8;
        $this->addText($this->pageWidth - 100, $this->y, "Date: " . date('d/m/Y', strtotime($facture['created_at'] ?? 'now')), 10);
        $this->y -= 20;
    }

    private function addClientInfo($client) {
        $this->y -= 20;
        $this->addText($this->margin, $this->y, "CLIENT:", 12);
        $this->y -= 8;
        $this->addText($this->margin, $this->y, ($client['societe'] ?? '') . ' ' . ($client['nom'] ?? ''), 10);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, $client['adresse'] ?? '', 10);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, $client['email'] ?? '', 10);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, $client['telephone'] ?? '', 10);
        $this->y -= 20;
    }

    private function addDevisLignes($lignes) {
        $this->addText($this->margin, $this->y, "DÉTAIL DU DEVIS:", 12);
        $this->y -= 15;
        
        // Header
        $this->addText($this->margin, $this->y, "Description", 10);
        $this->addText($this->margin + 80, $this->y, "Qté", 10);
        $this->addText($this->margin + 110, $this->y, "Prix U", 10);
        $this->addText($this->margin + 150, $this->y, "Total HT", 10);
        $this->y -= 10;
        
        // Line separator
        $this->addText($this->margin, $this->y, str_repeat('-', 150), 10);
        $this->y -= 10;
        
        $total = 0;
        foreach ($lignes as $ligne) {
            $desc = wordwrap($ligne['description'] ?? '', 40, "\n");
            $descLines = explode("\n", $desc);
            
            foreach ($descLines as $i => $descLine) {
                $this->addText($this->margin, $this->y, $descLine, 9);
                if ($i === 0) {
                    $this->addText($this->margin + 80, $this->y, (string)($ligne['quantite'] ?? 1), 9);
                    $this->addText($this->margin + 110, $this->y, number_format($ligne['prix_unitaire'] ?? 0, 2, ',', ' ') . ' €', 9);
                    $ligneTotal = ($ligne['quantite'] ?? 1) * ($ligne['prix_unitaire'] ?? 0);
                    $this->addText($this->margin + 150, $this->y, number_format($ligneTotal, 2, ',', ' ') . ' €', 9);
                    $total += $ligneTotal;
                }
                $this->y -= 6;
            }
        }
        
        $this->y -= 10;
        $this->addText($this->margin, $this->y, str_repeat('-', 150), 10);
        $this->y -= 10;
        $this->addText($this->margin + 120, $this->y, "Total HT:", 10);
        $this->addText($this->margin + 150, $this->y, number_format($total, 2, ',', ' ') . ' €', 10);
        $this->y -= 8;
        
        $tva = $total * (($lignes[0]['tva_rate'] ?? 20) / 100);
        $this->addText($this->margin + 120, $this->y, "TVA (" . ($lignes[0]['tva_rate'] ?? 20) . "%):", 10);
        $this->addText($this->margin + 150, $this->y, number_format($tva, 2, ',', ' ') . ' €', 10);
        $this->y -= 8;
        
        $ttc = $total + $tva;
        $this->addText($this->margin + 120, $this->y, "Total TTC:", 12);
        $this->addText($this->margin + 150, $this->y, number_format($ttc, 2, ',', ' ') . ' €', 12);
    }

    private function addFactureLignes($lignes) {
        $this->addText($this->margin, $this->y, "DÉTAIL DE LA FACTURE:", 12);
        $this->y -= 15;
        
        // Header
        $this->addText($this->margin, $this->y, "Description", 10);
        $this->addText($this->margin + 80, $this->y, "Qté", 10);
        $this->addText($this->margin + 110, $this->y, "Prix U", 10);
        $this->addText($this->margin + 150, $this->y, "Total HT", 10);
        $this->y -= 10;
        
        // Line separator
        $this->addText($this->margin, $this->y, str_repeat('-', 150), 10);
        $this->y -= 10;
        
        $total = 0;
        foreach ($lignes as $ligne) {
            $desc = wordwrap($ligne['description'] ?? '', 40, "\n");
            $descLines = explode("\n", $desc);
            
            foreach ($descLines as $i => $descLine) {
                $this->addText($this->margin, $this->y, $descLine, 9);
                if ($i === 0) {
                    $this->addText($this->margin + 80, $this->y, (string)($ligne['quantite'] ?? 1), 9);
                    $this->addText($this->margin + 110, $this->y, number_format($ligne['prix_unitaire'] ?? 0, 2, ',', ' ') . ' €', 9);
                    $ligneTotal = ($ligne['quantite'] ?? 1) * ($ligne['prix_unitaire'] ?? 0);
                    $this->addText($this->margin + 150, $this->y, number_format($ligneTotal, 2, ',', ' ') . ' €', 9);
                    $total += $ligneTotal;
                }
                $this->y -= 6;
            }
        }
        
        $this->y -= 10;
        $this->addText($this->margin, $this->y, str_repeat('-', 150), 10);
        $this->y -= 10;
        $this->addText($this->margin + 120, $this->y, "Total HT:", 10);
        $this->addText($this->margin + 150, $this->y, number_format($total, 2, ',', ' ') . ' €', 10);
        $this->y -= 8;
        
        $tva = $total * (($lignes[0]['tva_rate'] ?? 20) / 100);
        $this->addText($this->margin + 120, $this->y, "TVA (" . ($lignes[0]['tva_rate'] ?? 20) . "%):", 10);
        $this->addText($this->margin + 150, $this->y, number_format($tva, 2, ',', ' ') . ' €', 10);
        $this->y -= 8;
        
        $ttc = $total + $tva;
        $this->addText($this->margin + 120, $this->y, "Total TTC:", 12);
        $this->addText($this->margin + 150, $this->y, number_format($ttc, 2, ',', ' ') . ' €', 12);
    }

    private function addDevisFooter($devis, $brand) {
        $this->y = $this->margin + 40;
        $this->addText($this->margin, $this->y, "Conditions de ce devis:", 10);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- Validité: 1 mois à compter de la date d'émission", 9);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- Modalités de paiement: 30% d'acompte, solde à la livraison", 9);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- TVA: " . ($devis['tva_rate'] ?? 20) . "%", 9);
    }

    private function addFactureFooter($facture, $brand) {
        $this->y = $this->margin + 40;
        $this->addText($this->margin, $this->y, "Modalités de paiement:", 10);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- Paiement à 30 jours date de facture", 9);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- TVA: " . ($facture['tva_rate'] ?? 20) . "%", 9);
        $this->y -= 6;
        $this->addText($this->margin, $this->y, "- En cas de retard, pénalité de 3 fois le taux légal", 9);
    }

    private function getPDFEOF(): string {
        $xref = "xref\n0 6\n0000000000 65535 f\n";
        
        $offsets = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0
        ];
        
        foreach ($offsets as $i => $offset) {
            $xref .= sprintf("%010d 00000 n\n", $offset);
        }
        
        return $xref .
               "trailer\n" .
               "<<\n" .
               "/Size 6\n" .
               "/Root 1 0 R\n" .
               ">>\n" .
               "startxref\n0\n" .
               "%%EOF\n";
    }
}
