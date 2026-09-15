<?php

namespace App\Ai\Tools;

interface Tool
{
    public function definition(): array;

    public function use(array $arguments = []);
}
