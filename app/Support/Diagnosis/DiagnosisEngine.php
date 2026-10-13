<?php

namespace App\Support\Diagnosis;

/**
 * Analyses one slide image for one test type.
 *
 * Plug-in point: implement this interface for a real engine, add a key for
 * it in AppServiceProvider::registerDiagnosisEngine(), and set
 * TBAI_DIAGNOSIS_ENGINE (config tbai.diagnosis.engine) to that key. The
 * controller, storage and pages only talk to this interface, so nothing
 * else changes. A real engine must set simulated to false in its result and
 * needs recorded clinical sign-off before it is switched on anywhere.
 */
interface DiagnosisEngine
{
    public function analyse(DiagnosisRequest $request): DiagnosisResult;
}
