<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateLanguageRequest extends ClientApiRequest
{
    public function rules(): array
    {
        $supported = implode(',', array_keys(config('autotranslator.languages', [])));
        return [
            'language' => ['required', 'string', "in:en,{$supported}"],
        ];
    }
}
