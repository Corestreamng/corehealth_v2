<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Encounter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExportMonthlyEncountersReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:monthly-encounters {year=2026}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a PDF report of monthly encounters for a given year';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '-1'); // Unlimited memory for large PDF generation
        ini_set('max_execution_time', 300); // Increase max execution time just in case

        $year = $this->argument('year');
        $this->info("Generating Monthly Encounters Report for {$year}...");

        $relations = [
            'doctor.staff_profile.clinic', 
            'patient.user', 
            'productOrServiceRequest.user'
        ];

        if (method_exists(Encounter::class, 'queue')) {
            $relations[] = 'queue.clinic';
            $relations[] = 'queue.receptionist.user';
        }

        $encounters = Encounter::with($relations)
        ->whereYear('created_at', $year)
        ->orderBy('created_at', 'asc')
        ->get();

        if ($encounters->isEmpty()) {
            $this->warn("No encounters found for the year {$year}.");
            return 1;
        }

        $totalClinics = 0;
        if (method_exists(Encounter::class, 'queue')) {
            $totalClinics = $encounters->pluck('queue.clinic_id')->unique()->filter()->count();
        }
        
        if ($totalClinics === 0) {
            $totalClinics = $encounters->pluck('doctor.staff_profile.clinic_id')->unique()->filter()->count();
        }

        $stats = [
            'total_encounters' => $encounters->count(),
            'total_patients' => $encounters->pluck('patient_id')->unique()->count(),
            'total_clinics' => $totalClinics,
            'total_doctors' => $encounters->pluck('doctor_id')->unique()->filter()->count(),
        ];

        // Group by month
        $grouped = $encounters->groupBy(function($encounter) {
            return Carbon::parse($encounter->created_at)->format('F');
        });

        // Fix for shared hosting environments where the 'public' directory might be missing or renamed
        if (!realpath(config('dompdf.public_path') ?: base_path('public'))) {
            config(['dompdf.public_path' => base_path()]);
        }

        $pdf = Pdf::loadView('reports.monthly_encounters_pdf', [
            'year' => $year,
            'stats' => $stats,
            'grouped' => $grouped
        ]);
        
        $pdf->setPaper('a4', 'landscape');

        $filename = "monthly_encounters_report_{$year}_" . time() . ".pdf";
        $directory = storage_path("app/reports");
        $path = $directory . '/' . $filename;
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save($path);

        $this->info("Report generated successfully: {$path}");
        return 0;
    }
}
