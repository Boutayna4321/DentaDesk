<?php

namespace App\Reports;

use App\Billing\Money;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Payment;
use App\Models\Practitioner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** The numbers a dentist runs her practice on, for one period, compared with the one before. */
class PracticeStats
{
    public const PERIODS = ['30j' => '30 derniers jours', 'mois' => 'Ce mois-ci', '3m' => '3 derniers mois', '12m' => '12 derniers mois'];

    private const DAYS = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(public string $period = '30j', ?CarbonImmutable $from = null, ?CarbonImmutable $to = null)
    {
        $now = CarbonImmutable::now();
        $this->to = $to ?? $now;
        $this->from = $from ?? match ($period) {
            'mois' => $now->startOfMonth(),
            '3m' => $now->subMonths(3)->startOfDay(),
            '12m' => $now->subYear()->startOfDay(),
            default => $now->subDays(30)->startOfDay(),
        };
    }

    /** The same length of time, just before. */
    public function previous(): self
    {
        $length = $this->from->diffInSeconds($this->to);

        return new self($this->period, $this->from->subSeconds($length), $this->from);
    }

    /** Every headline number, as plain values, so two periods can be compared. */
    public function headline(): array
    {
        $collected = (int) Payment::whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])->sum('amount_cents');
        $billedQ = Document::where('type', 'invoice')->where('status', 'validated')->whereBetween('issued_on', [$this->from->toDateString(), $this->to->toDateString()]);
        $billed = (int) (clone $billedQ)->sum('total_cents');
        $invoices = (clone $billedQ)->count();
        $visits = Appointment::whereBetween('starts_at', [$this->from, $this->to]);
        $done = (clone $visits)->where('status', 'done')->count();
        $noShow = (clone $visits)->where('status', 'no_show')->count();
        $conversations = Conversation::whereBetween('created_at', [$this->from, $this->to])->count();
        $assistantBookings = Appointment::where('source', 'assistant')->whereBetween('created_at', [$this->from, $this->to])->count();

        return [
            'collected' => $collected,
            'billed' => $billed,
            'invoices' => $invoices,
            'ticket' => $invoices ? intdiv($billed, $invoices) : 0,
            'visits' => $done,
            'occupancy' => $this->occupancy()['rate'],
            'noShowRate' => ($done + $noShow) ? $noShow / ($done + $noShow) : null,
            'newPatients' => $this->newPatients($this->from, $this->to),
            'assistantBookings' => $assistantBookings,
            'conversations' => $conversations,
        ];
    }

    public function outstanding(): array
    {
        $open = Document::where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents');

        return ['amount' => (int) (clone $open)->selectRaw('SUM(total_cents - paid_cents) as s')->value('s'), 'count' => (clone $open)->count()];
    }

    /**
     * Booked minutes over open minutes: overall, per weekday, and per weekday × hour
     * (the heatmap of when the chair is busy).
     */
    public function occupancy(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from ??= $this->from;
        $to ??= $this->to;
        $dr = Practitioner::firstOrFail();
        $hours = $dr->workingHours->groupBy('weekday');
        $offs = $dr->timeOffs()->where('ends_at', '>', $from)->where('starts_at', '<', $to)->get();
        $open = $booked = [];

        for ($d = $from->startOfDay(); $d <= $to; $d = $d->addDay()) {
            foreach ($hours[$d->isoWeekday()] ?? [] as $h) {
                $start = $d->setTimeFromTimeString($h->opens_at);
                $end = $d->setTimeFromTimeString($h->closes_at);
                if ($offs->contains(fn ($o) => $o->starts_at <= $start && $o->ends_at >= $end)) {
                    continue;
                }
                $this->spread($open, $start, $end);
            }
        }
        $appointments = Appointment::where('practitioner_id', $dr->id)->whereNotIn('status', ['cancelled'])
            ->whereBetween('starts_at', [$from, $to])->get(['starts_at', 'ends_at']);
        foreach ($appointments as $a) {
            $this->spread($booked, $a->starts_at->toImmutable(), $a->ends_at->toImmutable());
        }

        $sum = fn (array $m) => array_sum(array_map('array_sum', $m));
        $byDay = collect($open)->map(fn ($hoursOpen, $wd) => array_sum($booked[$wd] ?? []) / max(1, array_sum($hoursOpen)))->sortKeys();
        $heat = [];
        foreach ($open as $wd => $perHour) {
            foreach ($perHour as $hour => $minutes) {
                $heat[$wd][$hour] = $minutes ? min(1, ($booked[$wd][$hour] ?? 0) / $minutes) : null;
            }
        }

        return ['rate' => $sum($open) ? min(1, $sum($booked) / $sum($open)) : null, 'byWeekday' => $byDay, 'heat' => $heat];
    }

    /** Adds the minutes of [start, end) to $grid[weekday][hour]. */
    private function spread(array &$grid, CarbonImmutable $start, CarbonImmutable $end): void
    {
        for ($t = $start; $t < $end; $t = $t->addHour()->startOfHour()) {
            $slotEnd = min($end, $t->addHour()->startOfHour());
            $grid[$t->isoWeekday()][(int) $t->format('G')] = ($grid[$t->isoWeekday()][(int) $t->format('G')] ?? 0) + $t->diffInMinutes($slotEnd);
        }
    }

    private function newPatients(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Appointment::selectRaw('patient_id, MIN(starts_at) as first')->groupBy('patient_id')->havingBetween('first', [$from, $to])->get()->count();
    }

    /** Twelve weeks, whatever the period: the trend needs a fixed window. */
    public function weekly(): Collection
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(11);
        $payments = Payment::where('paid_on', '>=', $start->toDateString())->get(['paid_on', 'amount_cents']);
        $invoices = Document::where('type', 'invoice')->where('status', 'validated')->where('issued_on', '>=', $start->toDateString())->get(['issued_on', 'total_cents']);
        $visits = Appointment::where('starts_at', '>=', $start)->whereIn('status', ['done', 'no_show'])->get(['starts_at', 'status']);
        $byAssistant = Appointment::where('source', 'assistant')->where('created_at', '>=', $start)->get(['created_at']);

        return collect(range(0, 11))->map(function ($i) use ($start, $payments, $invoices, $visits, $byAssistant) {
            $w = $start->addWeeks($i);
            $end = $w->addWeek();
            $in = fn ($rows, $col) => $rows->filter(fn ($r) => $r->{$col} >= $w && $r->{$col} < $end);
            $v = $in($visits, 'starts_at');
            $inv = $in($invoices, 'issued_on');
            $noShow = $v->where('status', 'no_show')->count();

            return [
                'label' => $w->translatedFormat('j M'),
                'collected' => (int) $in($payments, 'paid_on')->sum('amount_cents'),
                'billed' => (int) $inv->sum('total_cents'),
                'ticket' => $inv->count() ? intdiv((int) $inv->sum('total_cents'), $inv->count()) : 0,
                'assistant' => $in($byAssistant, 'created_at')->count(),
                'visits' => $v->where('status', 'done')->count(),
                'noShowRate' => $v->count() ? $noShow / $v->count() : 0,
                'occupancy' => $this->occupancy($w, min($end->subSecond(), CarbonImmutable::now()))['rate'] ?? 0,
                'newPatients' => $this->newPatients($w, $end),
                'partial' => $end > CarbonImmutable::now(),
            ];
        });
    }

    /** @return Collection<int, array{label:string,value:int}> */
    public function sources(): Collection
    {
        $labels = ['assistant' => 'Assistant', 'web' => 'Site web', 'phone' => 'Téléphone'];

        return collect($labels)->map(fn ($label, $key) => ['label' => $label, 'value' => Appointment::whereBetween('starts_at', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')->where('source', $key)->count()])->values();
    }

    public function paymentMethods(): Collection
    {
        $rows = Payment::whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('method, SUM(amount_cents) as s')->groupBy('method')->pluck('s', 'method');

        return collect(Payment::METHODS)->map(fn ($label, $key) => ['label' => $label, 'value' => (int) ($rows[$key] ?? 0)])->values();
    }

    /** What brings the money in: invoiced amount per act. */
    public function revenueByAct(): Collection
    {
        return DocumentLine::query()
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('documents.type', 'invoice')->where('documents.status', 'validated')
            ->whereBetween('documents.issued_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('document_lines.label, SUM(document_lines.amount_cents) as total, SUM(document_lines.quantity) as n')
            ->groupBy('document_lines.label')->orderByDesc('total')->take(7)->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->total, 'count' => (int) $r->n]);
    }

    /** Three to five plain sentences: what a dentist would want to be told. */
    public function insights(array $now, array $before, array $occupancy, Collection $sources, Collection $acts, array $outstanding): array
    {
        $out = [];
        $delta = fn ($a, $b) => $b ? ($a - $b) / $b : null;

        $d = $delta($now['collected'], $before['collected']);
        $out[] = ['tone' => $d === null ? 'neutral' : ($d >= 0 ? 'good' : 'bad'),
            'text' => 'Vous avez encaissé '.Money::format($now['collected']).($d === null ? '.' : ', soit '.sprintf('%+d', round($d * 100)).' % par rapport à la période précédente.')];

        if ($occupancy['byWeekday']->isNotEmpty()) {
            $best = $occupancy['byWeekday']->sortDesc()->keys()->first();
            $worst = $occupancy['byWeekday']->sort()->keys()->first();
            $quiet = collect($occupancy['heat'][$worst] ?? [])->filter(fn ($v) => $v !== null)->sort()->keys()->first();
            $out[] = ['tone' => 'neutral', 'text' => ucfirst(self::DAYS[$best]).' est votre jour le plus rempli ('.round($occupancy['byWeekday'][$best] * 100).' %). Le '
                .self::DAYS[$worst].' est le plus calme'.($quiet !== null ? ', surtout vers '.$quiet.' h : un bon moment pour les urgences et les contrôles.' : '.')];
        }

        $total = max(1, $sources->sum('value'));
        $assistant = $sources->firstWhere('label', 'Assistant')['value'] ?? 0;
        if ($assistant) {
            $out[] = ['tone' => 'good', 'text' => round($assistant / $total * 100).' % des rendez-vous ont été pris par l’assistant, sans un appel au secrétariat.'];
        }

        if ($acts->isNotEmpty()) {
            $top = $acts->first();
            $out[] = ['tone' => 'neutral', 'text' => '« '.$top['label'].' » rapporte le plus : '.Money::format($top['value']).' sur '.$top['count'].' acte(s).'];
        }

        if ($now['noShowRate'] !== null && $now['noShowRate'] > 0.08) {
            $out[] = ['tone' => 'bad', 'text' => round($now['noShowRate'] * 100).' % des patients ne sont pas venus. Les rappels WhatsApp la veille réduisent ce chiffre.'];
        } elseif ($outstanding['count'] > 0) {
            $out[] = ['tone' => 'bad', 'text' => $outstanding['count'].' facture(s) attendent encore '.Money::format($outstanding['amount']).'.'];
        }

        return $out;
    }
}
