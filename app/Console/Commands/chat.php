<?php

namespace App\Console\Commands;

use App\Ai\Tools\CurrentTime;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\Tool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('app:chat')]
#[Description('Command description')]
class chat extends Command
{
    protected $history = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            $userInput = text('What is on your mind?', required: true);

            $this->history[] = ['role' => 'user', 'content' => $userInput];

            while (true) {
                // Run the model and get the response from the API
                $response = spin(fn () => $this->runModel(), 'Processing your request...');

                $toolCalls = collect($response['choices'])
                    ->map(function ($choice) {
                        if (isset($choice['message']['tool_calls'])) {
                            return collect($choice['message']['tool_calls'])
                                ->map(fn ($toolCall) => [
                                    'tool_call_id' => $toolCall['id'],
                                    'file' => json_decode($toolCall['function']['arguments'])->file ?? null,
                                    'name' => $toolCall['function']['name'],
                                ]);
                        }
                    })
                    ->flatten(1)
                    ->filter()
                    ->toArray();

                $cleanResponse = collect($response['choices'])
                    ->map(fn ($choice) => collect($choice['message']))
                    ->toArray();

                // Store for clean response
                $this->history = [...$this->history, ...$cleanResponse];

                $this->info($this->history[count($this->history) - 1]['reasoning_content']);

                if (empty($toolCalls)) {
                    $this->info("\n \n🤖 Answers: \n");
                    $this->info($this->history[count($this->history) - 1]['content']);

                    break;
                } else {
                    // Handle tool calls here
                    foreach ($toolCalls as $toolCall) {
                        foreach ($this->tools() as $tool) {
                            if ($toolCall['name'] == $tool->definition()['function']['name']) {
                                $result = $tool->use(['file' => $toolCall['file'] ?? null]);

                                $updateHistory = [
                                    'role' => 'tool',
                                    'tool_call_id' => $toolCall['tool_call_id'],
                                    'content' => $result,
                                ];

                                $this->history[] = $updateHistory;
                            }
                        }
                    }
                }
            }
        }
    }

    private function runModel()
    {
        return Http::withToken(config('services.deepseek.key'))->post(config('services.deepseek.url'), [
            'model' => config('services.deepseek.model'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ...$this->history,
            ],
            'tools' => array_map(fn (Tool $tool) => $tool->definition(), $this->tools()),
        ])->throw()->json();
    }

    private function tools()
    {
        return [
            new ReadFile,
            new CurrentTime,
            new RevenueTool,
        ];
    }
}
