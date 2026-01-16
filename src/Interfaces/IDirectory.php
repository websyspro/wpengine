<?php

namespace Websyspro\WpEngine\Interfaces;

interface IDirectory
{
    public function getName(): string;

    /** @return array<IDirectory|IFile> */
    public function getChildren(): array;
}
