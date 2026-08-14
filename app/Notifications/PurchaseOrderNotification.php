<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PurchaseOrder;

class PurchaseOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $po;
    public $title;
    public $message;
    public $actionText;
    public $actionUrl;
    public $notes;

    /**
     * Create a new notification instance.
     */
    public function __construct(PurchaseOrder $po, string $title, string $message, ?string $actionText = null, ?string $actionUrl = null, ?string $notes = null)
    {
        $this->po = $po;
        $this->title = $title;
        $this->message = $message;
        $this->actionText = $actionText;
        $this->actionUrl = $actionUrl;
        $this->notes = $notes;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (!isset($notifiable->enable_email_notification) || $notifiable->enable_email_notification) {
            $channels[] = 'mail';
        }

        if (!isset($notifiable->enable_system_notification) || $notifiable->enable_system_notification) {
            $channels[] = 'database';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $poUrl = $this->actionUrl ?? (config('app.url') . "/purchase-orders/" . $this->po->id);

        return (new MailMessage)
            ->subject($this->title)
            ->view('mails.purchase-order', [
                'title' => $this->title,
                'messageText' => $this->message,
                'po' => $this->po,
                'actionText' => $this->actionText ?? 'View Purchase Order',
                'actionUrl' => $poUrl,
                'notes' => $this->notes,
                'bargains' => $this->po->bargains()->with('user')->latest()->get()
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->po->id,
            'po_number' => $this->po->po_number,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->po->status,
            'payment_status' => $this->po->payment_status,
        ];
    }
}
