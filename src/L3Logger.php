<?php declare(strict_types=1);

namespace ESolution\LaravelLokiLogging;

use Monolog\Handler\HandlerInterface;

class L3Logger implements HandlerInterface
{
    /** @var resource */
    private $file;
    /** @var boolean */
    private $hasError;
    /** @var array */
    private $context;
    /** @var string */
    private $format;

    /**
     * @param string $format
     * @param array $context
     */
    public function __construct(string $format = '[{level_name}] {message}', array $context = [])
    {
        $this->format = config('l3.format');
        $this->context = config('l3.context');

        $file = storage_path(L3ServiceProvider::LOG_LOCATION);
        if (!file_exists($file)) {
            touch($file);
        }
        $this->file = fopen($file, 'a');
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * This handler is capable of handling every record
     * @param array $record
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        return true;
    }

    public function handle(array $record): bool
    {
        $this->hasError |= $record['level_name'] === 'ERROR';
        $message = $record['message'];
        $tags = $this->context;
        if (preg_match($this->format, "[TEST] " . $message, $matches)) {
            foreach ($tags as $tag => $value) {
                $cleanValue = preg_replace("/[\{|\}]/", "",  $value);
                if (isset($matches[$tag]) && !empty($matches[$tag])) {
                    $tags[$tag] = $matches[$tag];
                } elseif(isset($record[$cleanValue])) {
                    $tags[$tag] = $record[$cleanValue];
                } elseif(!preg_match("/\{/", $tag) && !preg_match("/\{/", $value)) {
                    $tags[$tag] = $value;
                } else {
                    unset($tags[$tag]);
                }
            }
        }
        $data = [];
        if (isset($record['context']['exception'])) {
            $exception = $record['context']['exception'];
            $data['trace'] = $exception->getTraceAsString();
            $data['message'] = $exception->getMessage();
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $tags['exception'] = sprintf("[ERROR] %s:%s", $data['file'], $data['line']);
            $message = $data['trace'];
        }

        fwrite($this->file, json_encode([
                'time' => now()->getPreciseTimestamp(),
                'tags' => $tags,
                'message' => $message
            ]) . "\n");
        return true;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function flush($force = false): void
    {
        if ($this->hasError || $force) {
            $persister = new L3Persister();
            $persister->handle();
        }
    }

    public function close(): void
    {
        fclose($this->file);
    }

    private function formatString(string $format, string $context): string
    {
        $message = $format;
        preg_match(
            sprintf('{%s}', $key),
            $message,
            $matches
        );
        if (isset($matches[0])) {
            $a = 1;
        }
        return '';
    }
}
