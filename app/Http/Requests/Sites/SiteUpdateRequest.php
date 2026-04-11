<?php

declare(strict_types=1);

namespace App\Http\Requests\Sites;

use Illuminate\Foundation\Http\FormRequest;

final class SiteUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ssl' => ['nullable', 'string', 'in:le,self,inherit,custom,off'],
            'wildcard' => ['boolean'],
            'php_version' => ['nullable', 'string', 'in:5.6,7.0,7.2,7.3,7.4,8.0,8.1,8.2,8.3,8.4,8.5,latest'],
            'proxy_cache' => ['nullable', 'string', 'in:on,off'],
            'proxy_cache_max_size' => ['nullable', 'string', 'max:20', 'regex:/^\d+[kmgKMG]$/'],
            'proxy_cache_max_time' => ['nullable', 'string', 'max:20', 'regex:/^\d+[smhSMH]$/'],
            'add_alias_domains' => ['nullable', 'string', 'max:500'],
            'delete_alias_domains' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Map validated input into the options array expected by EasyEngineCommandBuilder::buildUpdateSite().
     *
     * @return array<string, mixed>
     */
    public function toEngineOptions(): array
    {
        $data = $this->validated();

        return array_filter([
            'ssl' => $data['ssl'] ?? null,
            'wildcard' => ! empty($data['wildcard']) ? true : null,
            'php_version' => $data['php_version'] ?? null,
            'proxy_cache' => $data['proxy_cache'] ?? null,
            'proxy_cache_max_size' => $data['proxy_cache_max_size'] ?? null,
            'proxy_cache_max_time' => $data['proxy_cache_max_time'] ?? null,
            'add_alias_domains' => $data['add_alias_domains'] ?? null,
            'delete_alias_domains' => $data['delete_alias_domains'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
