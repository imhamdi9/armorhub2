<?php

namespace App\Services\Pdf;

use App\Models\Order;

/** The tax invoice for a paid order, as a PDF the customer can keep or hand to an accountant. Latin text only (see MiniPdf); the on-screen receipt covers Arabic. */
class InvoicePdf
{
    public static function number(Order $order): string
    {
        return sprintf('INV-%d-%06d', $order->created_at?->year ?? now()->year, $order->id);
    }

    public function render(Order $order): string
    {
        $order->loadMissing('product:id,name,name_ar,sku,seller_id');
        $pdf = new MiniPdf();
        $money = fn ($v) => number_format((float) $v, 2).' '.$order->currency;
        $name = MiniPdf::ascii((string) $order->product?->name) ?: 'Product '.($order->product?->sku ?: '#'.$order->product_id);
        $seller = $order->product?->seller_id ? \App\Models\Company::withoutGlobalScopes()->whereKey($order->product->seller_id)->value('name') : null;
        $customer = $order->customer?->name ?? $order->guest_name;
        $address = (array) $order->makeVisible('shipping_address')->shipping_address;

        $pdf->text(50, 70, 'ArmorHub', 24, true);
        $pdf->text(545, 62, 'TAX INVOICE', 14, true, true);
        $pdf->text(545, 80, self::number($order), 10, false, true);
        $pdf->line(50, 100, 545, 100, 1.2);

        $pdf->text(50, 125, 'Date', 9);
        $pdf->text(50, 139, ($order->created_at ?? now())->format('d M Y'), 11, true);
        $pdf->text(200, 125, 'Order', 9);
        $pdf->text(200, 139, '#'.$order->id, 11, true);
        $pdf->text(300, 125, 'Sold by', 9);
        $pdf->text(300, 139, $seller ? (MiniPdf::ascii($seller) ?: 'Seller') : 'ArmorHub', 11, true);

        $pdf->text(50, 175, 'Billed to', 9);
        $pdf->text(50, 189, MiniPdf::ascii((string) $customer) ?: 'Customer', 11, true);
        $lines = array_filter([$address['line1'] ?? null, $address['city'] ?? null, $address['country'] ?? null]);
        foreach (array_values($lines) as $i => $l) {
            $pdf->text(50, 203 + $i * 13, MiniPdf::ascii((string) $l), 10);
        }

        $y = 270;
        $pdf->line(50, $y - 14, 545, $y - 14);
        $pdf->text(50, $y, 'Item', 10, true);
        $pdf->text(380, $y, 'Qty', 10, true, true);
        $pdf->text(545, $y, 'Amount', 10, true, true);
        $pdf->line(50, $y + 8, 545, $y + 8);
        $y += 28;
        $pdf->text(50, $y, mb_strimwidth($name, 0, 60, '...'), 11);
        $pdf->text(380, $y, (string) ($order->quantity ?? 1), 11, false, true);
        $pdf->text(545, $y, $money($order->product_amount ?? $order->total_amount), 11, false, true);

        $y += 40;
        $rows = [['Items', $order->total_amount]];
        if ((float) $order->shipping_amount > 0) {
            $rows[] = ['Delivery', $order->shipping_amount];
        }
        if ((float) $order->discount_amount > 0) {
            $rows[] = ['Discount', -1 * (float) $order->discount_amount];
        }
        if ((float) $order->service_fee_amount > 0) {
            $rows[] = ['Platform fee ('.rtrim(rtrim(number_format((float) $order->service_fee_rate, 2), '0'), '.').'%)', $order->service_fee_amount];
        }
        foreach ($rows as [$label, $value]) {
            $pdf->text(380, $y, $label, 10);
            $pdf->text(545, $y, $money($value), 10, false, true);
            $y += 16;
        }
        if ((float) $order->tax_rate > 0) {
            $pdf->text(380, $y, 'Includes VAT ('.rtrim(rtrim(number_format((float) $order->tax_rate, 2), '0'), '.').'%)', 9);
            $pdf->text(545, $y, $money($order->tax_amount), 9, false, true);
            $y += 16;
        }
        $pdf->line(380, $y - 6, 545, $y - 6);
        $y += 10;
        $pdf->text(380, $y, 'Total paid', 12, true);
        $pdf->text(545, $y, $money($order->payable_amount), 12, true, true);

        $pdf->text(50, 780, 'Issued by ArmorHub on behalf of the seller. Keep this invoice for your records and for any warranty claim.', 8);

        return $pdf->output();
    }
}
