<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:chat')]
#[Description('Command description')]
class chat extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $request = Http::withToken(config('services.deepseek.key'))->post(config('services.deepseek.url'), [
            'model' => config('services.deepseek.model'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        ])->throw()->json();

        dump($request['choices'][0]['message']['content']);
    }
}
