<?php

namespace Resque\Failure;

use Predis\ClientInterface;
use Resque\Job\JobInterface;
use Resque\WorkerInterface;
use Resque\QueueInterface;

/**
 * Default redis backend for storing failed jobs.
 */
class Redis implements FailureInterface
{
    /**
     * @var ClientInterface A redis client.
     */
    protected $redis;

    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
    }

    public function save(JobInterface $job, \Exception $exception, QueueInterface $queue, WorkerInterface $worker)
    {
        $this->redis->rpush(
            'failed',
            json_encode(
                array(
                    'failed_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                    'payload' => $job,
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                    'backtrace' => explode("\n", $exception->getTraceAsString()),
                    'worker' => $worker->getId(),
                    'queue' => $queue->getName(),
                )
            )
        );
    }

    public function count()
    {
        return $this->redis->llen('failed');
    }

    public function clear()
    {
        $this->redis->del('failed');
    }
}
