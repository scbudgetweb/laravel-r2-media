<?php

namespace Scbudgetweb\R2Media\Support;

final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $successful,
        public string $message,
    ) {}
}
