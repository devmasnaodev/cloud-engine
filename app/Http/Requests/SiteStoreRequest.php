<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SiteStoreRequest extends FormRequest
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
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'domain' => ['required', 'string', 'max:253'],
            'type' => ['required', 'in:wp,php,html'],
            'php_version' => ['nullable', 'string', 'in:5.6,7.0,7.2,7.3,7.4,8.0,8.1,8.2,8.3,8.4,8.5,latest'],
            'title' => ['nullable', 'string', 'max:255'],
            'public_dir' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/]+$/'],
            'ssl' => ['nullable', 'in:le,self,none'],
            'cache' => ['boolean'],
            'skip_install' => ['boolean'],
            'skip_content' => ['boolean'],
            'locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'admin_user' => ['nullable', 'string', 'max:60'],
            'admin_pass' => ['nullable', 'string', 'min:8', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'mu' => ['nullable', 'in:subdir,subdom'],
            'local_db' => ['boolean'],
            'alias_domains' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Map validated input into the options array expected by EasyEngineCommandBuilder.
     *
     * @return array<string, mixed>
     */
    public function toEngineOptions(): array
    {
        $data = $this->validated();
        $type = $data['type'];

        // HTML sites only support: ssl, wildcard, public-dir, alias-domains
        // PHP/WP support: php_version, cache, title, skip_install, skip_content, locale, etc.
        $isPhpOrWp = $type === 'php' || $type === 'wp';

        return array_filter([
            'type' => $type,
            'php_version' => $isPhpOrWp ? ($data['php_version'] ?? null) : null,
            'title' => $data['title'] ?? null,
            'public_dir' => $data['public_dir'] ?? null,
            'ssl' => $data['ssl'] ?? null,
            'cache' => $isPhpOrWp && ($data['cache'] ?? false) ? true : null,
            'skip_install' => ($data['skip_install'] ?? false) ? true : null,
            'skip_content' => ($data['skip_content'] ?? false) ? true : null,
            'locale' => $data['locale'] ?? null,
            'admin_user' => $data['admin_user'] ?? null,
            'admin_pass' => $data['admin_pass'] ?? null,
            'admin_email' => $data['admin_email'] ?? null,
            'mu' => $data['mu'] ?? null,
            'local_db' => ($data['local_db'] ?? false) ? true : null,
            'alias_domains' => $data['alias_domains'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
