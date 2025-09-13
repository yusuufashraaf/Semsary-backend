<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\resetPasswordEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class sendResetPassJob implements ShouldQueue
{
    use Queueable;

    protected string $token;
    protected string $email;
    /**
     * Create a new job instance.
     */
    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;

    }

    /**
     * Execute the job.
     */
    public function handle(): void

    {
        $user = User::where('email', $this->email)->first();

        if ($user) {
            $user->notify(new resetPasswordEmail($this->token));
        }
    }
}
