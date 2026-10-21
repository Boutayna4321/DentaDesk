<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['patient_id', 'tooth', 'state', 'surfaces', 'note'])]
class ToothRecord extends Model
{
    /** Whole-tooth states. "present" with no surface finding is a healthy tooth and is not stored. */
    public const STATES = [
        'present' => 'Présente', 'missing' => 'Absente', 'implant' => 'Implant', 'crown' => 'Couronne',
        'root_canal' => 'Dévitalisée', 'to_extract' => 'À extraire',
    ];

    /** Surfaces, in French charting: mésiale, occlusale (incisive for front teeth), distale, vestibulaire, linguale (palatine above). */
    public const SURFACES = ['M', 'O', 'D', 'V', 'L'];

    public const FINDINGS = ['caries' => 'Carie', 'filling' => 'Obturation'];

    private const POSITION = [1 => 'incisive centrale', 'incisive latérale', 'canine', '1re prémolaire', '2e prémolaire', '1re molaire', '2e molaire', 'dent de sagesse'];

    private const QUADRANT = [1 => 'supérieure droite', 'supérieure gauche', 'inférieure gauche', 'inférieure droite'];

    public static function name(int $fdi): string
    {
        return ucfirst(self::POSITION[$fdi % 10].' '.self::QUADRANT[intdiv($fdi, 10)]);
    }

    /** "Couronne · carie O, D · obturation M" */
    public static function describe(string $state, array $surfaces): string
    {
        $parts = $state !== 'present' ? [self::STATES[$state]] : [];
        foreach (self::FINDINGS as $key => $label) {
            $on = array_keys(array_filter($surfaces, fn ($v) => $v === $key));
            if ($on) {
                $parts[] = mb_strtolower($label).' '.implode(', ', $on);
            }
        }

        return $parts ? ucfirst(implode(' · ', $parts)) : 'Saine';
    }

    protected function casts(): array
    {
        return ['surfaces' => 'array'];
    }
}
