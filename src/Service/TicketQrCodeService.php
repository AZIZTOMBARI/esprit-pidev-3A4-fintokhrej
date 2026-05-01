<?php

namespace App\Service;

use App\Entity\Ticket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

class TicketQrCodeService
{
    public function generateSvg(Ticket $ticket): string
    {
        $result = (new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
                SvgWriter::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT => false,
            ],
            validateResult: false,
            size: 220,
            margin: 8,
        ))->build(data: $ticket->getQrCodePayload());

        return $result->getString();
    }

    /**
     * @param iterable<Ticket> $tickets
     * @return array<int, string>
     */
    public function generateMap(iterable $tickets): array
    {
        $qrCodes = [];

        foreach ($tickets as $ticket) {
            if (!$ticket instanceof Ticket || $ticket->getId() === null) {
                continue;
            }

            $qrCodes[$ticket->getId()] = $this->generateSvg($ticket);
        }

        return $qrCodes;
    }
}
