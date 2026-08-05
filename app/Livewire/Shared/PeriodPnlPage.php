<?php

namespace App\Livewire\Shared;

use App\Support\PeriodPnl;
use App\Support\PeriodPnlExcelExport;
use App\Support\StaffPortal;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodPnlPage extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);

        $tz = 'Asia/Dhaka';
        $this->fromDate = Carbon::now($tz)->startOfMonth()->toDateString();
        $this->toDate = Carbon::now($tz)->toDateString();
    }

    public function updatedFromDate(): void
    {
        $this->errorMessage = '';
    }

    public function updatedToDate(): void
    {
        $this->errorMessage = '';
    }

    public function exportExcel(): ?StreamedResponse
    {
        abort_unless(StaffPortal::canAccessMoney(), 403);
        $this->errorMessage = '';

        try {
            $this->validate([
                'fromDate' => 'required|date',
                'toDate' => 'required|date|after_or_equal:fromDate',
            ]);

            return PeriodPnlExcelExport::download(
                PeriodPnl::forRange($this->fromDate, $this->toDate)
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not export P&L.';

            return null;
        }
    }

    public function render()
    {
        $report = null;
        if ($this->fromDate !== '' && $this->toDate !== '') {
            try {
                $report = PeriodPnl::forRange($this->fromDate, $this->toDate);
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage() ?: 'Could not build period P&L.';
            }
        }

        return view('livewire.shared.period-pnl-page', [
            'report' => $report,
        ])->layout('layouts.private.app', ['title' => 'Period P&L']);
    }
}
