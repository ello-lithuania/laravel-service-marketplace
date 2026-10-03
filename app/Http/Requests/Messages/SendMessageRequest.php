<?php

namespace App\Http\Requests\Messages;

use App\Concerns\AttachmentValidationRules;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Žinutės siuntimas: tekstas ir (arba) priedai. Teisę (dalyvis, pokalbis dar atviras) tikrina
 * ConversationPolicy::sendMessage – atsisakius vartotojas mato priežastį (Response::deny), o ne bendrą „403".
 */
class SendMessageRequest extends FormRequest
{
    use AttachmentValidationRules;

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
        $rules = [
            // Tekstas privalomas, jei nėra priedų (galima išsiųsti vien nuotrauką)
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:'.self::MAX_BODY],
            'attachments' => ['nullable', 'array', 'max:'.Message::MAX_ATTACHMENTS],
            'attachments.*' => ['file'],
        ];

        // Kiekvienam failui – taisyklės pagal jo tikrą tipą (nuotrauka ar PDF)
        foreach (Arr::wrap($this->file('attachments')) as $index => $file) {
            $rules["attachments.{$index}"] = $this->attachmentRules($file);
        }

        return $rules;
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
            'body.required_without' => __('messages.validation.body_required'),
            'attachments.max' => __('messages.validation.attachments_max', ['max' => Message::MAX_ATTACHMENTS]),
            ...$this->attachmentMessages('attachments.*'),
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

    /**
     * @return list<UploadedFile>
     */
    public function attachments(): array
    {
        // Validacija („attachments.*" => file) jau užtikrino, kad kiekvienas elementas – failas
        return array_values(Arr::wrap($this->file('attachments')));
    }
}
