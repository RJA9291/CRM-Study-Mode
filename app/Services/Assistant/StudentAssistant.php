<?php

namespace App\Services\Assistant;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\Knowledge\KnowledgeAnswerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Channel-agnostic brain behind the Telegram (and later WhatsApp) chat.
 */
class StudentAssistant
{
    public const HELP = "Saya pembantu CRM Study Mode. Cuba:\n"
        ."• /today — task & to-do hari ini (bernombor)\n"
        ."• /tasks — semua task belum siap\n"
        ."• /task Tajuk | 15/10 | Subjek — tambah task (tarikh & subjek pilihan)\n"
        ."• /todo Perkara — tambah to-do hari ini\n"
        ."• /done 2 — tanda siap item no. 2 dari /today\n"
        .'• Tanya apa-apa soalan — saya jawab berdasarkan folder knowledge college.';

    private const TODAY_PATTERNS = [
        'hari ini', 'hari ni', 'harini', 'arini', 'today', 'nak buat apa', 'perlu buat apa', 'kena buat apa',
        'current task', 'task sekarang', 'apa task',
    ];

    public function __construct(private readonly KnowledgeAnswerService $knowledge) {}

    public function reply(User $user, string $text): string
    {
        $text = trim($text);
        $normalized = Str::of($text)->lower()->squish()->toString();
        $command = Str::before(Str::before($normalized, ' '), '@');
        $argument = trim((string) Str::of($text)->squish()->after(' '));
        $argument = $argument === Str::of($text)->squish()->toString() ? '' : $argument;

        return match (true) {
            in_array($command, ['/start', '/help', 'help', 'menu'], true) => self::HELP,
            $command === '/today' => $this->today($user),
            $command === '/tasks' => $this->openTasks($user),
            $command === '/task' => $this->addTask($user, $argument),
            $command === '/todo' => $this->addTodo($user, $argument),
            $command === '/done' => $this->markDone($user, $argument),
            Str::contains($normalized, self::TODAY_PATTERNS) => $this->today($user),
            default => $this->knowledge->answer($text),
        };
    }

    public function today(User $user): string
    {
        $items = $this->todayItems($user);
        $lines = ['📅 Hari ini ('.today()->format('d/m/Y')."), {$user->name}:"];

        if ($items->isEmpty()) {
            $lines[] = 'Tiada task atau to-do tertunggak. 🎉';

            return implode("\n", $lines);
        }

        $sections = ['due' => "\n📌 Task perlu disiapkan:", 'progress' => "\n🔄 Sedang dibuat:", 'todo' => "\n✅ To-do:"];
        foreach ($sections as $group => $heading) {
            $groupItems = $items->filter(fn (array $item) => $item['group'] === $group);
            if ($groupItems->isEmpty()) {
                continue;
            }
            $lines[] = $heading;
            foreach ($groupItems as $number => $item) {
                $lines[] = "{$number}. ".($item['model'] instanceof Task ? $this->describe($item['model']) : $item['model']->title);
            }
        }

        $lines[] = "\nTanda siap: /done <nombor>";

        return implode("\n", $lines);
    }

    public function openTasks(User $user): string
    {
        $tasks = $user->tasks()->open()->orderByRaw('due_date is null')->orderBy('due_date')->limit(20)->get();

        if ($tasks->isEmpty()) {
            return 'Semua task dah siap. 🎉';
        }

        return "📋 Task belum siap:\n".$tasks->map(fn (Task $t) => '• '.$this->describe($t))->implode("\n");
    }

    private function addTask(User $user, string $argument): string
    {
        $parts = array_map('trim', explode('|', $argument));
        $title = $parts[0] ?? '';

        if ($title === '') {
            return "Format: /task Tajuk | tarikh | subjek\nContoh: /task Assignment Fizik | 15/10 | Fizik";
        }

        $due = null;
        if (filled($parts[1] ?? null)) {
            $due = $this->parseDate($parts[1]);
            if (! $due) {
                return "Tarikh \"{$parts[1]}\" tidak difahami. Guna 15/10, 15/10/2026, esok atau lusa.";
            }
        }

        $task = $user->tasks()->create([
            'title' => Str::limit($title, 255, ''),
            'subject' => filled($parts[2] ?? null) ? Str::limit($parts[2], 100, '') : null,
            'due_date' => $due,
        ]);

        return '✅ Task ditambah: '.$this->describe($task);
    }

    private function addTodo(User $user, string $argument): string
    {
        if ($argument === '') {
            return "Format: /todo Perkara\nContoh: /todo Beli kertas A4";
        }

        $todo = $user->todos()->create(['title' => Str::limit($argument, 255, ''), 'for_date' => today()]);

        return "✅ To-do ditambah: {$todo->title}";
    }

    private function markDone(User $user, string $argument): string
    {
        if (! ctype_digit($argument)) {
            return "Format: /done <nombor>\nNombor diambil dari senarai /today.";
        }

        $item = $this->todayItems($user)->get((int) $argument);
        if (! $item) {
            return "Tiada item no. {$argument} dalam senarai hari ini. Hantar /today untuk lihat nombor terkini.";
        }

        $model = $item['model'];
        if ($model instanceof Task) {
            $model->update(['status' => TaskStatus::Done]);

            return "🎉 Task siap: {$model->title}";
        }

        $model->update(['is_done' => true]);

        return "🎉 To-do siap: {$model->title}";
    }

    /**
     * The numbered list shown by /today; /done resolves numbers against the same list.
     *
     * @return Collection<int, array{group: string, model: Task|Todo}>
     */
    private function todayItems(User $user): Collection
    {
        $today = today();

        $due = $user->tasks()->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(1))
            ->orderBy('due_date')->orderBy('id')
            ->get();

        $inProgress = $user->tasks()->where('status', TaskStatus::InProgress->value)
            ->whereNotIn('id', $due->modelKeys())
            ->orderBy('id')
            ->get();

        $todos = $user->todos()
            ->where(fn ($q) => $q->whereDate('for_date', $today)->orWhereNull('for_date'))
            ->where('is_done', false)
            ->orderBy('id')
            ->get();

        $items = collect()
            ->concat($due->map(fn (Task $t) => ['group' => 'due', 'model' => $t]))
            ->concat($inProgress->map(fn (Task $t) => ['group' => 'progress', 'model' => $t]))
            ->concat($todos->map(fn (Todo $t) => ['group' => 'todo', 'model' => $t]))
            ->values();

        return $items->keyBy(fn ($item, int $index) => $index + 1);
    }

    private function parseDate(string $input): ?Carbon
    {
        $value = Str::of($input)->lower()->squish()->toString();

        $relative = [
            'hari ini' => 0, 'harini' => 0, 'today' => 0,
            'esok' => 1, 'besok' => 1, 'tomorrow' => 1,
            'lusa' => 2,
        ];
        if (array_key_exists($value, $relative)) {
            return today()->addDays($relative[$value]);
        }

        if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})$~', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]) : null;
        }

        if (! preg_match('~^(\d{1,2})[/.-](\d{1,2})(?:[/.-](\d{2}|\d{4}))?$~', $value, $m)) {
            return null;
        }

        [$day, $month] = [(int) $m[1], (int) $m[2]];
        $year = isset($m[3]) ? (int) (strlen($m[3]) === 2 ? '20'.$m[3] : $m[3]) : today()->year;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = Carbon::create($year, $month, $day);

        if (! isset($m[3]) && $date->lt(today())) {
            $date->addYear();
        }

        return $date;
    }

    private function describe(Task $task): string
    {
        $parts = [$task->title];
        if ($task->subject) {
            $parts[] = "[{$task->subject}]";
        }
        if ($task->due_date) {
            $parts[] = $task->isOverdue()
                ? '— LEWAT (due '.$task->due_date->format('d/m').')'
                : '— due '.$task->due_date->format('d/m');
        }

        return implode(' ', $parts);
    }
}
