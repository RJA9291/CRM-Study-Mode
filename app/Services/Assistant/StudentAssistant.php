<?php

namespace App\Services\Assistant;

use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\Knowledge\KnowledgeAnswerService;
use Illuminate\Support\Str;

/**
 * Channel-agnostic brain behind the Telegram (and later WhatsApp) chat.
 */
class StudentAssistant
{
    public const HELP = "Saya pembantu CRM Study Mode. Cuba:\n"
        ."• /today — task & to-do hari ini\n"
        ."• /tasks — semua task belum siap\n"
        .'• Tanya apa-apa soalan — saya jawab berdasarkan folder knowledge college.';

    private const TODAY_PATTERNS = [
        'hari ini', 'hari ni', 'harini', 'arini', 'today', 'nak buat apa', 'perlu buat apa', 'kena buat apa',
        'current task', 'task sekarang', 'apa task',
    ];

    public function __construct(private readonly KnowledgeAnswerService $knowledge) {}

    public function reply(User $user, string $text): string
    {
        $normalized = Str::of($text)->lower()->squish()->toString();
        $command = Str::before(Str::before($normalized, ' '), '@');

        return match (true) {
            in_array($command, ['/start', '/help', 'help', 'menu'], true) => self::HELP,
            $command === '/today' => $this->today($user),
            $command === '/tasks' => $this->openTasks($user),
            Str::contains($normalized, self::TODAY_PATTERNS) => $this->today($user),
            default => $this->knowledge->answer($text),
        };
    }

    public function today(User $user): string
    {
        $today = today();

        $due = $user->tasks()->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(1))
            ->orderBy('due_date')
            ->get();

        $inProgress = $user->tasks()->where('status', 'in_progress')
            ->whereNotIn('id', $due->modelKeys())
            ->get();

        $todos = $user->todos()
            ->where(fn ($q) => $q->whereDate('for_date', $today)->orWhereNull('for_date'))
            ->where('is_done', false)
            ->get();

        $lines = ["📅 Hari ini ({$today->format('d/m/Y')}), {$user->name}:"];

        if ($due->isEmpty() && $inProgress->isEmpty() && $todos->isEmpty()) {
            $lines[] = 'Tiada task atau to-do tertunggak. 🎉';

            return implode("\n", $lines);
        }

        if ($due->isNotEmpty()) {
            $lines[] = "\n📌 Task perlu disiapkan:";
            foreach ($due as $task) {
                $lines[] = '• '.$this->describe($task);
            }
        }

        if ($inProgress->isNotEmpty()) {
            $lines[] = "\n🔄 Sedang dibuat:";
            foreach ($inProgress as $task) {
                $lines[] = '• '.$this->describe($task);
            }
        }

        if ($todos->isNotEmpty()) {
            $lines[] = "\n✅ To-do:";
            foreach ($todos as $todo) {
                /** @var Todo $todo */
                $lines[] = '• '.$todo->title;
            }
        }

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
