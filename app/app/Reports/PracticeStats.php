<?php

namespace App\Reports;

use App\Models\Appointment;
use App\Models\AssistantRequest;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** The numbers a dentist runs her practice on, for one period. */
class PracticeStats
{
    public const PERIODS = ['30j' => '30 derniers jours', 'mois' => 'Ce mois-ci', '3m' => '3 derniers mois', '12m' => '12 derniers mois'];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(public string $period = '30j')
    {
        $now = CarbonImmutable::now();
        $this->to = $now;
        $this->from = match ($period) {
            'mois' => $now->startOfMonth(),
            '3m' => $now->subMonths(3)->startOfDay(),
            '12m' => $now->subYear()->startOfDay(),
            default => $now->subDays(30)->startOfDay(),
        };
    }

    public function money(): array
    {
        $invoices = Document::where('type', 'invoice')->where('status', 'validated');
        $billed = (clone $invoices)->whereBetween('issued_on', [$this->from->toDateString(), $this->to->toDateString()]);

        return [
            'collected' => (int) Payment::whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])->sum('amount_cents'),
            'billed' => (int) (clone $billed)->sum('total_cents'),
            'invoices' => (clone $billed)->count(),
            'outstanding' => (int) (clone $invoices)->selectRaw('SUM(total_cents - paid_cents) as s')->value('s'),
            'outstandingCount' => (clone $invoices)->whereColumn('paid_cents', '<', 'total_cents')->count(),
        ];
    }

    /** Booked minutes over open minutes, past days of the period only. */
    public function occupancy(): array
    {
        $dr = Practitioner::firstOrFail();
        $hours = $dr->workingHours->groupBy('weekday');
        $offs = $dr->timeOffs()->where('ends_at', '>', $this->from)->get();
        $byWeekday = array_fill(1, 7, ['open' => 0, 'booked' => 0]);

        $booked = Appointment::where('practitioner_id', $dr->id)->whereIn('status', ['done', 'in_care', 'arrived', 'confirmed', 'pending', 'no_show'])
            ->whereBetween('starts_at', [$this->from, $this->to])->get(['starts_at', 'ends_at']);
        foreach ($booked as $a) {
            $byWeekday[$a->starts_at->isoWeekday()]['booked'] += $a->starts_at->diffInMinutes($a->ends_at);
        }
        for ($d = $this->from; $d <= $this->to; $d = $d->addDay()) {
            foreach ($hours[$d->isoWeekday()] ?? [] as $h) {
                $open = $d->setTimeFromTimeString($h->opens_at);
                $close = $d->setTimeFromTimeString($h->closes_at);
                $closed = $offs->contains(fn ($o) => $o->starts_at <= $open && $o->ends_at >= $close);
                $byWeekday[$d->isoWeekday()]['open'] += $closed ? 0 : $open->diffInMinutes($close);
            }
        }
        $open = array_sum(array_column($byWeekday, 'open'));
        $used = array_sum(array_column($byWeekday, 'booked'));

        return [
            'rate' => $open ? $used / $open : null,
            'byWeekday' => collect($byWeekday)->filter(fn ($w) => $w['open'] > 0)->map(fn ($w) => min(1, $w['booked'] / $w['open'])),
        ];
    }

    public function patients(): array
    {
        $inPeriod = Appointment::whereBetween('starts_at', [$this->from, $this->to]);
        $done = (clone $inPeriod)->where('status', 'done')->count();
        $noShow = (clone $inPeriod)->where('status', 'no_show')->count();
        $firstVisits = Appointment::selectRaw('patient_id, MIN(starts_at) as first')->groupBy('patient_id')
            ->havingBetween('first', [$this->from, $this->to])->get()->count();
        $lead = Appointment::whereBetween('created_at', [$this->from, $this->to])->where('status', '!=', 'cancelled')->get(['created_at', 'starts_at'])
            ->map(fn ($a) => max(0, $a->created_at->diffInHours($a->starts_at, false)) / 24);

        return [
            'noShowRate' => ($done + $noShow) ? $noShow / ($done + $noShow) : null,
            'new' => $firstVisits,
            'leadDays' => $lead->isEmpty() ? null : $lead->avg(),
            'total' => Patient::count(),
        ];
    }

    public function assistant(): array
    {
        $conversations = Conversation::whereBetween('created_at', [$this->from, $this->to])->count();
        $booked = AssistantRequest::where('type', 'booking')->whereBetween('created_at', [$this->from, $this->to])->distinct('conversation_id')->count('conversation_id');

        return ['conversations' => $conversations, 'booked' => $booked, 'rate' => $conversations ? $booked / $conversations : null];
    }

    /** @return Collection<int, array{label:string,value:int}> */
    public function sources(): Collection
    {
        $labels = Appointment::SOURCES;

        return Appointment::whereBetween('starts_at', [$this->from, $this->to])->where('status', '!=', 'cancelled')
            ->selectRaw('source, COUNT(*) as n')->groupBy('source')->orderByDesc('n')->get()
            ->map(fn ($r) => ['label' => $labels[$r->source] ?? $r->source, 'value' => (int) $r->n]);
    }

    public function topServices(): Collection
    {
        $names = Service::pluck('name', 'id');

        return Appointment::whereBetween('starts_at', [$this->from, $this->to])->where('status', '!=', 'cancelled')
            ->selectRaw('service_id, COUNT(*) as n')->groupBy('service_id')->orderByDesc('n')->take(6)->get()
            ->map(fn ($r) => ['label' => $names[$r->service_id] ?? '?', 'value' => (int) $r->n]);
    }

    /** Collected per week, last 12 weeks, whatever the period: the trend needs a fixed window. */
    public function weekly(): Collection
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(11);
        $rows = Payment::where('paid_on', '>=', $start->toDateString())->get(['paid_on', 'amount_cents']);

        return collect(range(0, 11))->map(function ($i) use ($start, $rows) {
            $w = $start->addWeeks($i);
            $in = $rows->filter(fn ($p) => $p->paid_on >= $w && $p->paid_on < $w->addWeek());

            return ['week' => $w, 'value' => (int) $in->sum('amount_cents'), 'count' => $in->count()];
        });
    }
}
