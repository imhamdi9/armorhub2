<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use Illuminate\Support\Facades\DB;
use App\Models\PartnerQuote;

class QuoteService
{
    public function accept(PartnerQuote $partnerQuote)
    {
      return DB::transaction(function () use ($partnerQuote) {

          $order = $partnerQuote->order;
          $order->partnerQuotes()
              ->where('id', '!=', $partnerQuote->id)
              ->update([
                 'status' => QuoteStatus::Rejected,
               ]);

           $partnerQuote->update([
               'status' => QuoteStatus::Accepted,
            ]);

            $total = $order->product_amount
                + $partnerQuote->price
                + $order->commission_amount;

            $order->update([
                'service_amount' => $partnerQuote->price,
                'total_amount' => $total,
                'status' => OrderStatus::PartnerSelected,
            ]);

            return $order->fresh();
        });
    }  
}