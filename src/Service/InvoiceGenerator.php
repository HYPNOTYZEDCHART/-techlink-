<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoiceGenerator
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function generate(CustomerOrder $order): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('pdf/invoice.html.twig', [
            'order' => $order,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}