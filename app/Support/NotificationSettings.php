<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * users.notification_settings skaitymas su numatytosiomis reikšmėmis (struktūra – docs/DB_SCHEMA.md → users).
 *
 * JSON: {"grupė": {"mail": bool, "database": bool}, …}. NULL ar trūkstamas raktas = true,
 * todėl naujam vartotojui nieko saugoti nereikia, o naujai grupei – migruoti senų įrašų.
 */
final class NotificationSettings
{
    public const CHANNELS = ['mail', 'database'];

    /**
     * Grupės: kam skirta (rolės) ir kaip vadinasi nustatymų puslapyje.
     *
     * @var array<string, array{label: string, description: string, roles: list<UserRole>}>
     */
    public const GROUPS = [
        'new_requests' => [
            'label' => 'Naujos užklausos',
            'description' => 'Kai jūsų srityje ir zonoje paskelbiama nauja užklausa.',
            'roles' => [UserRole::Provider],
        ],
        'offer_updates' => [
            'label' => 'Mano pasiūlymai',
            'description' => 'Kai klientas priima ar atmeta jūsų pasiūlymą, užklausa atšaukiama ar pasibaigia.',
            'roles' => [UserRole::Provider],
        ],
        'new_offers' => [
            'label' => 'Nauji pasiūlymai',
            'description' => 'Kai teikėjas atsiunčia pasiūlymą jūsų užklausai.',
            'roles' => [UserRole::Client],
        ],
        'request_updates' => [
            'label' => 'Mano užklausos',
            'description' => 'Kai administratorius patvirtina ar atmeta jūsų užklausą.',
            'roles' => [UserRole::Client],
        ],
        'messages' => [
            'label' => 'Žinutės',
            'description' => 'Kai gaunate naują žinutę.',
            'roles' => [UserRole::Client, UserRole::Provider],
        ],
    ];

    /**
     * @param  array<string, array<string, bool>>  $settings  pilnas nustatymų masyvas (su numatytosiomis reikšmėmis)
     */
    private function __construct(private readonly array $settings) {}

    public static function for(User $user): self
    {
        return self::fromArray($user->notification_settings);
    }

    /**
     * Sujungia išsaugotus nustatymus su numatytaisiais. Nežinomi raktai ir ne bool reikšmės ignoruojami.
     *
     * @param  array<array-key, mixed>|null  $stored
     */
    public static function fromArray(?array $stored): self
    {
        $settings = self::defaults();

        foreach ($settings as $group => $channels) {
            foreach (array_keys($channels) as $channel) {
                $value = $stored[$group][$channel] ?? null;

                if (is_bool($value)) {
                    $settings[$group][$channel] = $value;
                }
            }
        }

        return new self($settings);
    }

    /**
     * Viskas įjungta.
     *
     * @return array<string, array<string, bool>>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (): array => array_fill_keys(self::CHANNELS, true),
            self::GROUPS,
        );
    }

    /**
     * Grupės, kurias mato ši rolė (nustatymų puslapiui).
     *
     * @return list<string>
     */
    public static function groupsFor(UserRole $role): array
    {
        return array_keys(array_filter(
            self::GROUPS,
            fn (array $group): bool => in_array($role, $group['roles'], true),
        ));
    }

    public function wants(string $group, string $channel): bool
    {
        return $this->settings[$group][$channel] ?? false;
    }

    /**
     * Kanalai, kuriais siųsti šios grupės pranešimą – tiesiai Notification::via() rezultatas.
     *
     * @return list<string>
     */
    public function channels(string $group): array
    {
        return array_values(array_filter(self::CHANNELS, fn (string $channel): bool => $this->wants($group, $channel)));
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
