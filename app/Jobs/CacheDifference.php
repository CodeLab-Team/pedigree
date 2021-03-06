<?php

namespace App\Jobs;

use App\Services\GitHub;
use App\Models\Difference;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CacheDifference implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var \App\Services\GitHub */
    protected $service;

    /** @var \App\Models\Repository */
    protected $baseRepository;

    /** @var \App\Models\Repository */
    protected $repository;

    /** @var string */
    protected $branch;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Repository  $base
     * @param  \App\Models\Repository  $repository
     * @return void
     */
    public function __construct($base, $repository)
    {
        $this->baseRepository = $base;
        $this->repository = $repository;
        $this->branch = $base->default_branch;

        $this->service = app(GitHub::class);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $difference = $this->service->repository($this->repository->owner->id, $this->repository->name)
                ->compare(
                    $this->branch,
                    "{$this->baseRepository->owner->id}:{$this->branch}"
                );

            Difference::updateOrCreate([
                'base_repository_id' => $this->baseRepository->id,
                'repository_id' => $this->repository->id,
                'status' => 'unknown',
            ], [
                'status' => $difference['status'] ?? 'unknown',
                'ahead_by' => $difference['ahead_by'] ?? 0,
                'behind_by' => $difference['behind_by'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Difference::updateOrCreate([
                'base_repository_id' => $this->baseRepository->id,
                'repository_id' => $this->repository->id,
                'status' => 'unknown',
            ], [
                'status' => 'error',
            ]);
        }
    }
}
