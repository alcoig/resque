<?php

namespace Resque\Component\Core;

use Predis\ClientInterface;
use Resque\Component\Job\Model\JobInterface;
use Resque\Component\Queue\Model\OriginQueueAwareInterface;
use Resque\Component\Queue\Model\QueueInterface;
use Resque\Component\Worker\Model\WorkerInterface;
use Resque\Component\Job\Failure\FailureInterface;

/**
 * Default redis backend for storing failed jobs.
 */
class RedisFailure implements FailureInterface, RedisAwareInterface
{
    /**
     * @var ClientInterface A redis client.
     */
    protected $redis;

    public function __construct(ClientInterface $redis)
    {
        $this->setRedisClient($redis);
    }

    /**
     * {@inheritDoc}
     */
    public function setRedisClient(ClientInterface $redis)
    {
        $this->redis = $redis;

        return $this;
    }

    public function save(JobInterface $job, \Exception $exception, WorkerInterface $worker)
    {
        $queue = ($job instanceof OriginQueueAwareInterface) ? $job->getOriginQueue() : null;

        $this->redis->rpush(
            'failed',
            json_encode(
                array(
                    'failed_at' => date('c'),
                    'payload' => $job,
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                    'backtrace' => explode("\n", $exception->getTraceAsString()),
                    'worker' => $worker->getId(),
                    'queue' => ($queue instanceof QueueInterface) ? $queue->getName() : null,
                )
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return $this->redis->llen('failed');
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->redis->del('failed');
    }
}
