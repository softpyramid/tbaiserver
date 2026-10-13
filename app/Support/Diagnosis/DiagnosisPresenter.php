<?php

namespace App\Support\Diagnosis;

use App\Models\Diagnosis;
use App\Models\Team;
use App\Support\EntryDate;
use App\Support\ResourceResponse;

/**
 * Page props for diagnosis demo records. Only call it for a user who may see
 * the patient (PatientAccess): the report carries the decrypted free text.
 */
class DiagnosisPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function listItem(Diagnosis $diagnosis, Team $team): array
    {
        return [
            'id' => $diagnosis->uuid,
            'date' => EntryDate::dateTime($diagnosis->analysed_at),
            'testLabel' => $this->testLabel($diagnosis),
            'result' => $diagnosis->result,
            'resultLabel' => $this->resultLabel($diagnosis),
            'grade' => $diagnosis->grade,
            'simulated' => $diagnosis->simulated,
            'thumbnailUrl' => $diagnosis->thumbnail_path === null ? null : $this->url('patients.diagnoses.thumbnail', $diagnosis, $team),
            'href' => $this->url('patients.diagnoses.show', $diagnosis, $team),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(Diagnosis $diagnosis, Team $team): array
    {
        return [
            ...$this->listItem($diagnosis, $team),
            'imageUrl' => $this->url('patients.diagnoses.image', $diagnosis, $team),
            'findings' => array_map(fn (array $finding): array => [
                'x' => (float) $finding['x'],
                'y' => (float) $finding['y'],
                'w' => (float) $finding['w'],
                'h' => (float) $finding['h'],
                'label' => (string) $finding['label'],
            ], $diagnosis->findings),
            'sourceLabel' => ResourceResponse::line('entry.diagnosis_source_'.$diagnosis->image_source),
            'symptoms' => $diagnosis->symptoms,
            'notes' => $diagnosis->notes,
            'uploadedBy' => $diagnosis->creator->name,
            'engineLabel' => $diagnosis->simulated
                ? __('entry.diagnosis_engine_simulated')
                : $diagnosis->engine.' '.$diagnosis->engine_version,
        ];
    }

    public function testLabel(Diagnosis $diagnosis): string
    {
        return ResourceResponse::line('entry.diagnosis_test_'.$diagnosis->test_type);
    }

    public function resultLabel(Diagnosis $diagnosis): string
    {
        return ResourceResponse::line('entry.diagnosis_result_'.$diagnosis->result);
    }

    private function url(string $name, Diagnosis $diagnosis, Team $team): string
    {
        return route($name, [
            'current_team' => $team->slug,
            'patient' => $diagnosis->patient->uuid,
            'diagnosis' => $diagnosis->uuid,
        ]);
    }
}
