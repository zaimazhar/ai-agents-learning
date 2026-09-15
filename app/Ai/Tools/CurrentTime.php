<?php

namespace App\Ai\Tools;

class CurrentTime implements Tool
{
    public function definition(): array
    {
        return [
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
        ];
    }

    public function use(array $arguments = [])
    {
        return now()->toDateTimeString();
    }
}
