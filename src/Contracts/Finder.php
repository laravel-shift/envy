<?php

declare(strict_types=1);

namespace Worksome\Envy\Contracts;

interface Finder
{
    /**
     * @return array<int, string>
     */
    public function configFilePaths(): array;

    /**
     * @return array<int, string>
     */
    public function environmentFilePaths(): array;
}
