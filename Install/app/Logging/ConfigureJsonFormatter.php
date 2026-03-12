<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;

class ConfigureJsonFormatter
{
    public function __invoke(IlluminateLogger $logger, mixed $config = null): void
    {
        $includeStacktraces = (bool) config('ops.structured_include_stacktraces', false);
        $maxDepth = max(1, (int) config('ops.structured_formatter_max_depth', 6));
        $maxItems = max(1, (int) config('ops.structured_formatter_max_items', 250));

        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $formatter = new JsonFormatter(
                batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
                appendNewline: true,
                ignoreEmptyContextAndExtra: false,
                includeStacktraces: $includeStacktraces,
            );

            if (method_exists($formatter, 'setMaxNormalizeDepth')) {
                $formatter->setMaxNormalizeDepth($maxDepth);
            }

            if (method_exists($formatter, 'setMaxNormalizeItemCount')) {
                $formatter->setMaxNormalizeItemCount($maxItems);
            }

            $handler->setFormatter($formatter);
        }
    }
}
