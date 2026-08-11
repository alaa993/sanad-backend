<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrgPeriodicReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $orgName,
        public array $summary,
        public string $periodLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sanad — تقرير دوري للمنظمة: ' . $this->orgName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.org-periodic-report',
        );
    }
}
