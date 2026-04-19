<?php

namespace QuillBytes\PayrollEngine\Contracts;

interface ClientPolicyPreset
{
    public function supports(string $clientCode): bool;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;
}
