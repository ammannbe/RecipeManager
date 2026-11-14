<?php

namespace App\Http\Requests;

use App\Http\Controllers\PushSubscriptionController;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url:https', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! PushSubscriptionController::isPushService($value)) {
                    $fail(__('Unknown push service.'));
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ];
    }
}
