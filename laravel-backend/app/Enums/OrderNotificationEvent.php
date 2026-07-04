<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Customer-facing order events that trigger a notification. Single source of
 * truth: each case knows which order status raises it, its settings key suffix,
 * and its default (BTRC-vettable) Bangla SMS template. Add an event = add a case.
 */
enum OrderNotificationEvent: string
{
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /**
     * Map a new order status onto the event it should notify about (null = no
     * customer notification for that status).
     */
    public static function fromStatus(string $status): ?self
    {
        return match ($status) {
            'confirmed' => self::Confirmed,
            'shipped' => self::Shipped,
            'delivered' => self::Delivered,
            'cancelled' => self::Cancelled,
            'returned' => self::Returned,
            default => null,
        };
    }

    /** Settings key suffix for this event's per-channel toggle. */
    public function toggleKey(): string
    {
        return 'event_'.$this->value;
    }

    /** Settings key suffix for this event's editable template. */
    public function templateKey(): string
    {
        return 'tpl_'.$this->value;
    }

    /**
     * Default Bangla (Unicode) SMS template — the owner should replace these with
     * BTRC-vetted copy in Admin → Settings. Placeholders: {name} {order_no}
     * {total} {due} {tracking}.
     */
    public function defaultSmsTemplate(): string
    {
        return match ($this) {
            self::Confirmed => 'প্রিয় {name}, আপনার অর্ডার #{order_no} নিশ্চিত হয়েছে। ধন্যবাদ - Furnib।',
            self::Shipped => 'আপনার অর্ডার #{order_no} পাঠানো হয়েছে। ট্র্যাকিং: {tracking}। - Furnib',
            self::Delivered => 'আপনার অর্ডার #{order_no} ডেলিভারি সম্পন্ন। কেনার জন্য ধন্যবাদ - Furnib।',
            self::Cancelled => 'আপনার অর্ডার #{order_no} বাতিল করা হয়েছে। প্রশ্ন থাকলে কল করুন - Furnib।',
            self::Returned => 'আপনার অর্ডার #{order_no} ফেরত প্রক্রিয়াধীন। - Furnib',
        };
    }
}
