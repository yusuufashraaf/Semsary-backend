<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\emailOTP;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class sendOTPJOB implements ShouldQueue
{
    use Queueable;
    public User $user;
    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user =$user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->user->notify(new emailOTP($this->user));
    }
}
