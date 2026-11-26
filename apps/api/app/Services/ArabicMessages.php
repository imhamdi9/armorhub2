<?php

namespace App\Services;

/**
 * The built-in Arabic wording of the order notifications, for people whose language is Arabic. The English wording (and anything an administrator
 * published) is written where the event is announced; this is its Arabic counterpart, with the same {placeholders}.
 */
class ArabicMessages
{
    private const TEXT = [
        'quote_requested' => ['طلب عرض سعر جديد', 'عميل يطلب عرض سعر لـ {name} (الطلب رقم {order_id}).'],
        'quote_received' => ['وصلك عرض سعر', 'قدّم {partner} عرضاً بسعر {price} لـ {name}.'],
        'quote_accepted' => ['تم قبول عرضك', 'قبل العميل عرضك لـ {name} (الطلب رقم {order_id}). الدفع هو الخطوة التالية.'],
        'job_ready' => ['عمل جديد مدفوع', 'الطلب رقم {order_id} ({name}) مدفوع. يرجى تحديد موعد له.'],
        'order_to_ship' => ['طلب مدفوع جديد للشحن', 'الطلب رقم {order_id} ({name}) مدفوع. يرجى شحنه.'],
        'payment_received' => ['تم استلام الدفع', 'استلمنا دفعتك لـ {name}.'],
        'scheduled' => ['تم تحديد الموعد', 'تم تحديد موعد {name}.'],
        'started' => ['بدأ العمل', 'بدأ العمل في {name}.'],
        'completed' => ['اكتمل العمل', 'اكتمل {name}. راجع تفاصيل الضمان.'],
        'shipped' => ['تم شحن طلبك', '{name} في الطريق إليك {tracking}'],
        'shipment_out_for_delivery' => ['خرج للتسليم', '{name} خرج للتسليم اليوم. أعطِ المندوب هذا الرمز عند الاستلام: {code}.'],
        'shipment_delivered' => ['تم التسليم', 'تم تسليم {name}.'],
        'shipment_failed' => ['مشكلة في التسليم', 'تعذّر على شركة الشحن تسليم {name}. اختر يوماً جديداً للتسليم من صفحة طلبك.'],
        'delivery_rescheduled' => ['موعد تسليم جديد', 'طلب العميل تسليم {name} يوم {date}.'],
        'return_approved' => ['تمت الموافقة على الإرجاع', 'وافقنا على إرجاع {name}. أعطِ الرمز {code} لمن يستلم الطرد منك.'],
        'return_collected' => ['تم استلام المرتجع', 'استلمنا {name} المرتجع وسنفحصه قريباً.'],
        'return_failed' => ['لم يجتز المرتجع الفحص', 'لم يجتز {name} فحص الإرجاع: {reason}'],
        'cancelled' => ['أُلغي الطلب', 'أُلغي الطلب رقم {order_id} ({name}).'],
    ];

    /** @param array<string, scalar|null> $vars @return array{0: string, 1: string}|null */
    public static function render(string $kind, array $vars): ?array
    {
        if (! isset(self::TEXT[$kind])) {
            return null;
        }
        [$title, $body] = self::TEXT[$kind];
        // The tracking number appears only when there is one: "(رقم التتبع X)." instead of a dangling placeholder.
        $vars['tracking'] = ($vars['tracking'] ?? '') !== '' ? "(رقم التتبع {$vars['tracking']})." : '.';
        $fill = fn (string $t) => trim(preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $t));

        return [$fill($title), $fill($body)];
    }
}
