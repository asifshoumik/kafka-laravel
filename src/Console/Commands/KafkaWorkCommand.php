<?php

namespace Asifshoumik\KafkaLaravel\Console\Commands;

use Asifshoumik\KafkaLaravel\KafkaQueue;
use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;

class KafkaWorkCommand extends Command
{
    protected $signature = 'kafka:work 
                            {--queue=default : The queue to process}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--tries=3 : Number of times to attempt a job before logging it failed}
                            {--force : Force the worker to run even in maintenance mode}
                            {--stopWhenEmpty : Stop when the queue is empty}';

    protected $description = 'Start processing jobs on the Kafka queue as a daemon';

    public function handle(): int
    {
        $queue = $this->option('queue');
        $timeout = (int) $this->option('timeout');
        $memory = (int) $this->option('memory');
        $sleep = (int) $this->option('sleep');
        $tries = (int) $this->option('tries');
        $force = $this->option('force');
        $stopWhenEmpty = $this->option('stopWhenEmpty');

        $options = new WorkerOptions(
            $memory,
            $timeout,
            $sleep,
            $tries,
            $force,
            $stopWhenEmpty
        );

        /** @var QueueManager $queueManager */
        $queueManager = app('queue');
        
        /** @var Worker $worker */
        $worker = app('queue.worker');

        $this->info("Starting Kafka worker for queue: {$queue}");

        $worker->daemon('kafka', $queue, $options);

        return 0;
    }
}
