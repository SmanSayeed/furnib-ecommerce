<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional order confirmation, queued so a slow SMTP host never blocks
 * checkout.
 */
class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Furnib order '.$this->order->order_no);
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');

        return new Content(view: 'mail.orders.confirmation', with: [
            'order' => $this->order,
        ]);
    }
}
