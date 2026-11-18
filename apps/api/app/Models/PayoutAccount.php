<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Where one seller's earnings are sent. The number is stored encrypted and shown masked to everyone but the people who pay. */
class PayoutAccount extends Model
{
    protected $guarded = [];

    protected $casts = ['account_number' => 'encrypted', 'wallet_phone' => 'encrypted', 'verified_at' => 'datetime'];

    protected $hidden = ['account_number', 'wallet_phone'];

    public function company()
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    /** The number with all but its last four characters hidden. */
    public function masked(): ?string
    {
        $n = (string) ($this->method === 'wallet' ? $this->wallet_phone : $this->account_number);

        return $n === '' ? null : str_repeat('•', max(0, mb_strlen($n) - 4)).mb_substr($n, -4);
    }

    /** @return array<string, mixed> what the seller sees */
    public function forSeller(): array
    {
        return ['method' => $this->method, 'holder' => $this->holder, 'bank_name' => $this->bank_name, 'number' => $this->masked(), 'status' => $this->status, 'note' => $this->note];
    }

    /** @return array<string, mixed> what the person who pays needs, in full */
    public function forPayer(): array
    {
        return ['method' => $this->method, 'holder' => $this->holder, 'bank_name' => $this->bank_name, 'number' => $this->method === 'wallet' ? $this->wallet_phone : $this->account_number, 'status' => $this->status];
    }
}
