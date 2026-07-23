<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use Illuminate\Support\Facades\DB;
use App\Models\InstallationQuote;

class QuoteService
{
    public function accept(InstallationQuote $installationQuote)
    {
      return DB::transaction(function () use ($installationQuote) {

          $order = $installationQuote->order;
          $order->installationQuotes()
              ->where('id', '!=', $installationQuote->id)
              ->update([
                 'status' => QuoteStatus::Rejected,
               ]);

           $installationQuote->update([
               'status' => QuoteStatus::Accepted,
            ]);

            $total = $order->film_amount
                + $installationQuote->price
                + $order->commission_amount;

            $order->update([
                'installation_amount' => $installationQuote->price,
                'total_amount' => $total,
                'status' => OrderStatus::PartnerSelected,
            ]);

            return $order->fresh();
        });
    }  
}