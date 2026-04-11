<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ServerUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_users' => ['required', 'array', 'min:1'],
            'ssh_users.*.username' => ['required', 'string', 'max:255', 'distinct:strict'],
            'ssh_users.*.private_key' => ['nullable', 'string'],
            'ssh_execution_username' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $sshUsers = $this->input('ssh_users', []);
            $executionUsername = $this->input('ssh_execution_username');
            $usernames = collect($sshUsers)
                ->pluck('username')
                ->filter(static fn (mixed $username): bool => is_string($username) && $username !== '')
                ->all();

            if (! in_array($executionUsername, $usernames, true)) {
                $validator->errors()->add(
                    'ssh_execution_username',
                    'The execution SSH user must match one of the configured SSH users.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Normalize checkbox value into a proper boolean so the 'boolean' rule accepts it.
        if ($this->has('is_active')) {
            $value = $this->input('is_active');
            $this->merge([
                'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            ]);
        } else {
            $this->merge([
                'is_active' => false,
            ]);
        }
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'server name',
            'ip_address' => 'IP address',
            'ssh_port' => 'SSH port',
            'ssh_users' => 'SSH users',
            'ssh_users.*.username' => 'SSH username',
            'ssh_users.*.private_key' => 'SSH private key',
            'ssh_execution_username' => 'execution SSH user',
            'is_active' => 'active status',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name for the server.',
            'ip_address.required' => 'Please provide the server IP address.',
            'ip_address.ip' => 'Please provide a valid IP address.',
            'ssh_port.required' => 'Please specify the SSH port.',
            'ssh_port.integer' => 'SSH port must be a number.',
            'ssh_port.min' => 'SSH port must be at least 1.',
            'ssh_port.max' => 'SSH port cannot exceed 65535.',
            'ssh_users.required' => 'Please configure at least one SSH user.',
            'ssh_users.array' => 'SSH users must be sent as a valid list.',
            'ssh_users.min' => 'Please configure at least one SSH user.',
            'ssh_users.*.username.required' => 'Please provide the SSH username for each user entry.',
            'ssh_users.*.username.distinct' => 'SSH usernames cannot be duplicated.',
            'ssh_execution_username.required' => 'Please select which SSH user will execute commands.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
