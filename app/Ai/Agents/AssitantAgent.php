<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class AssitantAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'resolution' => $schema->string()->required(),
            'ticket_closed' => $schema->boolean()->required(),
        ];
    }

    public function tools(): array
    {
        return [];
    }

    public function structuredOutput(): array
    {
        return [];
    }

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }
}
