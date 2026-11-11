<?php

namespace App\Models;

use App\Enums\DestinationClass;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'iso2',
        'name',
        'destination_class',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination_class' => DestinationClass::class,
        ];
    }

    /**
     * A country the operator has not classified is `unknown`, never guessed.
     */
    public static function classFor(?string $iso2): DestinationClass
    {
        if ($iso2 === null || $iso2 === '') {
            return DestinationClass::Unknown;
        }

        $class = self::query()->where('iso2', strtoupper($iso2))->value('destination_class');

        return $class instanceof DestinationClass
            ? $class
            : DestinationClass::tryFrom((string) $class) ?? DestinationClass::Unknown;
    }

    public function setIso2Attribute(string $value): void
    {
        $this->attributes['iso2'] = strtoupper(trim($value));
    }
}
