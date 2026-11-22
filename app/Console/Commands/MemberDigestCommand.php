<?php

namespace App\Console\Commands;

use App\Http\Controllers\MemberController;
use App\Mail\MemberDigest;
use App\Models\MemberNotification;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Sends the weekly news email to the members who follow things and have not switched the email off. */
class MemberDigestCommand extends Command
{
    protected $signature = 'members:digest {--days=8 : only news from the last days} {--dry : show who would get an email, send nothing}';

    protected $description = 'Send each member one email with the new work in what they follow';

    public function handle(): int
    {
        if (! Notify::on()) {
            $this->info('Notifications are switched off in Site controls. Nothing sent.');

            return self::SUCCESS;
        }
        $news = MemberNotification::query()->where('type', 'new_work')->whereNull('emailed_at')
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))->orderBy('id')->get()->groupBy('user_id');

        $sent = 0;
        foreach ($news as $userId => $items) {
            $user = User::query()->find($userId);
            if (! $user || ! $user->isActive() || ! $user->hasVerifiedEmail() || ! MemberController::digestOn($user)) {
                continue;
            }
            $this->line($user->email.': '.$items->count().' item(s)');
            if ($this->option('dry')) {
                continue;
            }
            Mail::to($user->email)->send(new MemberDigest($user, $items));
            MemberNotification::query()->whereIn('id', $items->pluck('id'))->update(['emailed_at' => now()]);
            $sent++;
        }
        $this->info($this->option('dry') ? 'Dry run: nothing sent.' : "Sent $sent email(s).");

        return self::SUCCESS;
    }
}
