<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Platform admin يرى كل الشركات
        if ($user->hasRole('platform-admin')) {
            return;
        }

        // باقي المستخدمين يرون شركتهم فقط
        if ($user->company_id) {
            $builder->where(
               $model->getTable().'.company_id',
               $user->company_id
            );
        }
    }
}