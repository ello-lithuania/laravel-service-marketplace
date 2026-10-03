<?php

namespace App\Http\Requests\Messages;

use App\Models\Conversation;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Žinutės siuntimas. Teisę (dalyvis, pokalbis dar atviras) tikrina ConversationPolicy::sendMessage –
 * atsisakius vartotojas mato priežastį (Response::deny), o ne bendrą „403".
 */
class SendMessageRequest extends FormRequest
{
    public const MAX_BODY = 5000;

    public function authorize(): Response
    {
        return Gate::inspect('sendMessage', $this->conversation());
    }

    /**
     * Tarpai pradžioje ir pabaigoje nereikalingi – „   " neturi praeiti kaip žinutė.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim($this->string('body')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('messages.attributes');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('messages.validation.body_required'),
        ];
    }

    public function conversation(): Conversation
    {
        /** @var Conversation */
        return $this->route('conversation');
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
