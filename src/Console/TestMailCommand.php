<?php

namespace Newsman\LaravelNewsmanSmtp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'newsman:test {to : Email recipient} {--subject=NewsMAN Test}';
    protected $description = 'Trimite un test prin mailer-ul newsman';

    public function handle(): int
    {
        $to = $this->argument('to');
        $subject = $this->option('subject');

        Mail::mailer('newsman')->raw('Hello from NewsMAN driver 👋', function($m) use ($to, $subject) {
            $m->to($to)->subject($subject);
        });

        $this->info("Trimis test către {$to} prin mailer-ul newsman.");
        return self::SUCCESS;
    }
}
