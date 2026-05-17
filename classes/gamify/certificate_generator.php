<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_leducon\gamify;

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate Generator — creates milestone PDF certificates via TCPDF.
 *
 * @package   local_leducon
 * @copyright 2024 Liberty Education Resources
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_generator {

    /**
     * Generate and stream a milestone certificate PDF for a user.
     * Uses Moodle's bundled TCPDF library.
     */
    public static function generate(int $userid, string $milestone): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/pdflib.php');

        $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname', MUST_EXIST);
        $site = get_site();

        $pdf = new \pdf();
        $pdf->SetCreator('Moodle / local_leducon');
        $pdf->SetAuthor(format_string($site->fullname));
        $pdf->SetTitle(get_string('cert_title', 'local_leducon'));
        $pdf->SetSubject(get_string('cert_title', 'local_leducon'));
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage('L', 'A4');   // Landscape A4.

        // Background colour.
        $pdf->SetFillColor(124, 58, 237);  // --ld-accent
        $pdf->Rect(0, 0, 297, 210, 'F');

        // White inner card.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect(15, 15, 267, 180, 5, '1111', 'F');

        // Title.
        $pdf->SetTextColor(124, 58, 237);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetY(35);
        $pdf->Cell(0, 20, get_string('cert_title', 'local_leducon'), 0, 1, 'C');

        // Recipient.
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('helvetica', '', 16);
        $pdf->Cell(0, 10, get_string('cert_awarded_to', 'local_leducon'), 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 14, fullname($user), 0, 1, 'C');

        // Milestone text.
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 10, get_string('cert_for', 'local_leducon', strip_tags($milestone)), 0, 1, 'C');

        // Date.
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->SetY(155);
        $pdf->Cell(0, 10, userdate(time(), get_string('strftimedate', 'langconfig')), 0, 1, 'C');

        // Site name footer.
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(124, 58, 237);
        $pdf->SetY(165);
        $pdf->Cell(0, 10, format_string($site->fullname), 0, 1, 'C');

        $filename = clean_filename('certificate_' . $milestone . '_' . fullname($user) . '.pdf');
        $pdf->Output($filename, 'D');
        exit;
    }
}
