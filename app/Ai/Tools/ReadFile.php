<?php

namespace App\Ai\Tools;

use Exception;

class ReadFile implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'read_file',
                'strict' => true,
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
        ];
    }

    public function use(array $arguments = [])
    {
        $filePath = $arguments['file'] ?? null;

        if (! file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        return file_get_contents($filePath);
    }
}
