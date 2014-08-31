<?php

namespace Resque\Tests;

use Resque\Job;
use Resque\Resque;
use Resque\Foreman;
use Resque\Queue;
use Resque\Worker;

class WorkerTest extends ResqueTestCase
{
	public function testGetWorkerById()
	{
        return $this->markTestSkipped();

        $worker = new Worker('*');
		$worker->setLogger(new Resque_Log());
		$worker->registerWorker();

		$newWorker = Worker::find((string)$worker);
		$this->assertEquals((string)$worker, (string)$newWorker);
	}

	public function testPausedWorkerDoesNotPickUpJobs()
	{
        return $this->markTestSkipped();

        $worker = new Worker('*');
		$worker->setLogger(new Resque_Log());
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$worker->work(0);
		$this->assertEquals(0, Resque_Stat::get('processed'));
	}

	public function testResumedWorkerPicksUpJobs()
	{
        return $this->markTestSkipped();

        $worker = new Worker('*');
		$worker->setLogger(new Resque_Log());
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$this->assertEquals(0, Resque_Stat::get('processed'));
		$worker->unPauseProcessing();
		$worker->work(0);
		$this->assertEquals(1, Resque_Stat::get('processed'));
	}

	public function testWorkerCanWorkOverMultipleQueues()
	{
        $queueOne = new Queue('queue1');
        $queueTwo = new Queue('queue2');

		$worker = new Worker(
            array(
                $queueOne,
                $queueTwo,
		    )
        );

        $jobOne = new Job('Test_Job_1');
        $jobTwo = new Job('Test_Job_2');

        $queueOne->enqueue($jobOne);
        $queueTwo->enqueue($jobTwo);

		$job = $worker->reserve();
		$this->assertEquals($queueOne, $job->queue);

		$job = $worker->reserve();
		$this->assertEquals($queueTwo, $job->queue);
	}

	public function testWorkerWorksQueuesInSpecifiedOrder()
	{
        $queueHigh = new Queue('high');
        $queueMedium = new Queue('medium');
        $queueLow = new Queue('low');

		$worker = new Worker(
            array(
                $queueHigh,
                $queueMedium,
                $queueLow,
            )
        );

		// Queue the jobs in a different order
        $queueLow->enqueue(new Job('Test_Job_1'));
        $queueHigh->enqueue(new Job('Test_Job_2'));
        $queueMedium->enqueue(new Job('Test_Job_3'));

		// Now check we get the jobs back in the right queue order
		$job = $worker->reserve();
		$this->assertSame($queueHigh, $job->queue);
		$job = $worker->reserve();
		$this->assertSame($queueMedium, $job->queue);
		$job = $worker->reserve();
		$this->assertSame($queueLow, $job->queue);
	}

	public function testWildcardQueueWorkerWorksAllQueues()
	{
        return $this->markTestSkipped();

        $worker = new Worker('*');

		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerDoesNotWorkOnUnknownQueues()
	{
        $queueOne = new Queue('queue1');
        $queueTwo = new Queue('queue2');

        $queueTwo->enqueue(new Job('Test_Job'));

		$worker = new Worker($queueOne);
		$this->assertNull($worker->reserve());
	}

	public function testWorkerClearsItsStatusWhenNotWorking()
	{
        return $this->markTestIncomplete();

		Resque::enqueue('jobs', 'Test_Job');
		$worker = new Worker('jobs');
		$worker->setLogger(new Resque_Log());
		$job = $worker->reserve();
		$worker->workingOn($job);
		$worker->doneWorking();
		$this->assertEquals(array(), $worker->job());
	}

	public function testWorkerRecordsWhatItIsWorkingOn()
	{
        return $this->markTestIncomplete();

        $worker = new Worker('jobs');
		$worker->setLogger(new Resque_Log());
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new Resque_Job('jobs', $payload);
		$worker->workingOn($job);

		$job = $worker->job();
		$this->assertEquals('jobs', $job['queue']);
		if(!isset($job['run_at'])) {
			$this->fail('Job does not have run_at time');
		}
		$this->assertEquals($payload, $job['payload']);
	}

	public function testWorkerErasesItsStatsWhenShutdown()
	{
        $queue = new Queue('jobs');

        $queue->enqueue(new Job('Resque\Tests\Job\Simple'));
        $queue->enqueue(new Job('Invalid_Job'));

		$worker = new Worker($queue);

		$worker->work();
		$worker->work();

		$this->assertEquals(0, $worker->getStat('processed'));
		$this->assertEquals(0, $worker->getStat('failed'));
	}

	public function testWorkerFailsUncompletedJobsOnExit()
	{
        return $this->markTestIncomplete();

        $worker = new Worker('jobs');
		$worker->setLogger(new Resque_Log());
		$worker->registerWorker();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new Resque_Job('jobs', $payload);

		$worker->workingOn($job);
		$worker->unregisterWorker();

		$this->assertEquals(1, Resque_Stat::get('failed'));
	}

    public function testBlockingListPop()
    {
        return $this->markTestIncomplete();

        $worker = new Worker('jobs');
		$worker->setLogger(new Resque_Log());
        $worker->registerWorker();

        Resque::enqueue('jobs', 'Test_Job_1');
        Resque::enqueue('jobs', 'Test_Job_2');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Test_Job_' . $i, $job->payload['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }
}
