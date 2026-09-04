<?php

namespace App\Console\Commands;

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
                        // Handle each individual tool call here
                        if ($toolCall['name'] == 'get_current_time') {
                            $updateHistory = [
                                'role' => 'tool',
                                'tool_call_id' => $toolCall['tool_call_id'],
                                'content' => now()->toDateTimeString(),
                            ];

                            $this->history[] = $updateHistory;
                        } elseif ($toolCall['name'] == 'read_file') {
                            $updateHistory = [
                                'role' => 'tool',
                                'tool_call_id' => $toolCall['tool_call_id'],
                                'content' => file_get_contents(
                                    base_path($toolCall['file'])
                                ),
                            ];

                            $this->history[] = $updateHistory;
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
            'tools' => $this->tools(),
        ])->throw()->json();
    }

    private function tools()
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'description' => 'Get the current time from user input, if no country is specified then just use the Malaysian time.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'country' => [
                                'type' => 'string',
                                'description' => 'The country for which to get the current time.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_file',
                    'description' => 'Read the contents of a file from user input',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'file' => [
                                'type' => 'string',
                                'description' => 'The file to read.',
                            ],
                        ],
                    ],
                    'required' => ['file'],
                ],
            ],
        ];
    }
}
