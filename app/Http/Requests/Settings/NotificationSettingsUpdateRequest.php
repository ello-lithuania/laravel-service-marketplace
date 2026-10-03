<?php

namespace App\Http\Requests\Settings;

use App\Support\NotificationSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pranešimų nustatymai: {"settings": {"new_requests": {"mail": true, "database": false}, …}}.
 */
class NotificationSettingsUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach (array_keys(NotificationSettings::GROUPS) as $group) {
            foreach (NotificationSettings::CHANNELS as $channel) {
                $rules["settings.{$group}.{$channel}"] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * Tik žinomos grupės ir kanalai – sujungti su esamais nustatymais (neatsiųsti laukai nekeičiami).
     *
     * @param  array<string, array<string, bool>>  $current
     * @return array<string, array<string, bool>>
     */
    public function mergedSettings(array $current): array
    {
        foreach ($current as $group => $channels) {
            foreach (array_keys($channels) as $channel) {
                if ($this->has("settings.{$group}.{$channel}")) {
                    $current[$group][$channel] = $this->boolean("settings.{$group}.{$channel}");
                }
            }
        }

        return $current;
    }
}
