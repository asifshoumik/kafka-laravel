<?php

namespace Asifs\KafkaQueueLaravel\Console\Commands;

use Asifs\KafkaQueueLaravel\KafkaQueue;
use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Log;

class KafkaConsumeCommand extends Command
{
    protected $signature = 'kafka:consume 
                            {topic : The Kafka topic to consume from}
                            {--timeout=60 : The number of seconds to wait for a job}
                            {--memory=128 : The memory limit in megabytes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--maxJobs=0 : The number of jobs to process before stopping}
                            {--maxTime=0 : The maximum number of seconds the worker should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--stopWhenEmpty : Stop when the queue is empty}';

    protected $description = 'Consume messages from a Kafka topic';

    public function handle(): int
    {
        $topic = $this->argument('topic');
        $timeout = (int) $this->option('timeout');
        $memory = (int) $this->option('memory');
        $sleep = (int) $this->option('sleep');
        $maxJobs = (int) $this->option('maxJobs');
        $maxTime = (int) $this->option('maxTime');
        $force = $this->option('force');
        $stopWhenEmpty = $this->option('stopWhenEmpty');

        $this->info("Starting Kafka consumer for topic: {$topic}");

        /** @var QueueManager $queueManager */
        $queueManager = app('queue');
        
        /** @var KafkaQueue $queue */
        $queue = $queueManager->connection('kafka');

        if (!$queue instanceof KafkaQueue) {
            $this->error('The kafka connection is not properly configured.');
            return 1;
        }

        $startTime = time();
        $jobsProcessed = 0;

        while (true) {
            // Check memory limit
            if ($this->memoryExceeded($memory)) {
                $this->info('Memory limit exceeded. Stopping consumer.');
                break;
            }

            // Check max jobs limit
            if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                $this->info("Max jobs limit ({$maxJobs}) reached. Stopping consumer.");
                break;
            }

            // Check max time limit
            if ($maxTime > 0 && (time() - $startTime) >= $maxTime) {
                $this->info("Max time limit ({$maxTime}s) reached. Stopping consumer.");
                break;
            }

            // Check maintenance mode
            if (!$force && app()->isDownForMaintenance()) {
                $this->info('Application is in maintenance mode. Stopping consumer.');
                break;
            }

            try {
                $job = $queue->pop($topic);

                if ($job) {
                    $this->info("Processing job: {$job->getName()}");
                    $job->fire();
                    $jobsProcessed++;
                    
                    Log::info('Kafka job processed', [
                        'topic' => $topic,
                        'job_id' => $job->getJobId(),
                        'job_name' => $job->getName(),
                        'attempts' => $job->attempts()
                    ]);
                } else {
                    if ($stopWhenEmpty) {
                        $this->info('No jobs available and stopWhenEmpty is enabled. Stopping consumer.');
                        break;
                    }
                    
                    $this->comment('No jobs available. Sleeping...');
                    sleep($sleep);
                }

            } catch (\Exception $e) {
                $this->error("Error processing job: {$e->getMessage()}");
                Log::error('Kafka consumer error', [
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                sleep($sleep);
            }
        }

        $this->info("Consumer stopped. Processed {$jobsProcessed} jobs in " . (time() - $startTime) . " seconds.");
        return 0;
    }

    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }
}
