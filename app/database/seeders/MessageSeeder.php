<?php

namespace Database\Seeders;

use App\Models\InternalMessage;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $dr = User::where('role', 'dentist')->firstOrFail();
        $desk = User::where('role', 'secretary')->firstOrFail();
        $patient = fn (string $name) => Patient::whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) = ?", [$name])->value('id');

        $lines = [
            [$desk, $dr, 'Bonjour docteur, 10 rendez-vous aujourd’hui, Salma Bennani à 10h30 pour la 2e séance.', null, 150],
            [$dr, $desk, 'Merci Khadija. Prépare la radio de Salma s’il te plaît.', 'Salma Bennani', 140],
            [$desk, $dr, 'Omar El Fassi est arrivé, première visite. Il a rempli la fiche.', 'Omar El Fassi', 25],
            [$dr, $desk, 'Ok, je le prends après Salma.', null, 22],
            [$desk, $dr, 'Youssef Amrani a réservé une urgence par l’assistant pour cet après-midi. Je confirme ?', 'Youssef Amrani', 8],
        ];
        foreach ($lines as [$from, $to, $body, $who, $minutesAgo]) {
            InternalMessage::create([
                'sender_id' => $from->id, 'recipient_id' => $to->id, 'body' => $body, 'patient_id' => $who ? $patient($who) : null,
                'read_at' => $minutesAgo > 10 ? now()->subMinutes($minutesAgo - 1) : null,
            ])->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->saveQuietly();
        }
    }
}
