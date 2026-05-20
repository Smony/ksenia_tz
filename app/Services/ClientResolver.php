<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Client;

class ClientResolver
{
    public function resolveForCall(Call $call): ?Client
    {
        $normalized = $this->normalizePhone($call->phone);

        return Client::query()
            ->where('phone', $normalized)
            ->first();
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
