<?php

namespace App\Logging;

class PiiRedactionTap
{
    /**
     * Menambahkan PiiRedactionProcessor ke logger Monolog.
     *
     * @param  \Illuminate\Log\Logger|\Monolog\Logger  $logger
     */
    public function __invoke($logger): void
    {
        $monolog = method_exists($logger, 'getLogger') ? $logger->getLogger() : $logger;

        if (method_exists($monolog, 'pushProcessor')) {
            $monolog->pushProcessor(new PiiRedactionProcessor);
        }
    }
}
