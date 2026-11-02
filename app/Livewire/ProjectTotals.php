<?php

namespace App\Livewire;

use App\Accounting\Access;
use App\Accounting\Reports;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProjectTotals extends Component
{
    #[Locked]
    public int $projectId;

    public function render()
    {
        Access::project(auth()->user(), $this->projectId);

        return view('livewire.project-totals', ['summary' => app(Reports::class)->summary($this->projectId)]);
    }
}
