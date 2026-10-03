<?php

namespace App\Enums;

/**
 * The six response categories from the command center discovery note.
 * Suggested actions are the sentences in that note, unchanged.
 */
enum DriverCategory: string
{
    case DiagnosticBottlenecks = 'diagnostic_bottlenecks';
    case UnusualPositivity = 'unusual_positivity';
    case MissingPatients = 'missing_patients';
    case FollowUpRisk = 'follow_up_risk';
    case TestingGaps = 'testing_gaps';
    case ResourceAllocation = 'resource_allocation';

    public function label(): string
    {
        return match ($this) {
            self::DiagnosticBottlenecks => __('entry.driver_diagnostic_bottlenecks'),
            self::UnusualPositivity => __('entry.driver_unusual_positivity'),
            self::MissingPatients => __('entry.driver_missing_patients'),
            self::FollowUpRisk => __('entry.driver_follow_up_risk'),
            self::TestingGaps => __('entry.driver_testing_gaps'),
            self::ResourceAllocation => __('entry.driver_resource_allocation'),
        };
    }

    public function formula(): string
    {
        return match ($this) {
            self::DiagnosticBottlenecks => __('entry.formula_diagnostic_bottlenecks'),
            self::UnusualPositivity => __('entry.formula_unusual_positivity'),
            self::MissingPatients => __('entry.formula_missing_patients'),
            self::FollowUpRisk => __('entry.formula_follow_up_risk'),
            self::TestingGaps => __('entry.formula_testing_gaps'),
            self::ResourceAllocation => __('entry.formula_resource_allocation'),
        };
    }

    public function signalLabel(): string
    {
        return match ($this) {
            self::DiagnosticBottlenecks => __('entry.column_tat_provisional'),
            self::UnusualPositivity => __('entry.positivity_rate'),
            self::MissingPatients => __('entry.column_treatment_gap'),
            self::FollowUpRisk => __('entry.lost_to_follow_up'),
            self::TestingGaps => __('entry.testing_coverage'),
            self::ResourceAllocation => __('entry.suspects'),
        };
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return match ($this) {
            self::DiagnosticBottlenecks => [
                __('entry.action_increase_testing_capacity'),
                __('entry.action_reduce_specimen_transport_delays'),
            ],
            self::UnusualPositivity => [
                __('entry.action_targeted_screening'),
                __('entry.action_intensify_contact_investigation'),
            ],
            self::MissingPatients => [
                __('entry.action_investigate_treatment_gap'),
                __('entry.action_link_diagnosis_to_treatment'),
            ],
            self::FollowUpRisk => [
                __('entry.action_trace_interrupted_patients'),
                __('entry.action_strengthen_adherence_support'),
            ],
            self::TestingGaps => [
                __('entry.action_improve_referral_completion'),
                __('entry.action_expand_diagnostic_access'),
            ],
            self::ResourceAllocation => [
                __('entry.action_prioritise_high_burden_bmus'),
                __('entry.action_allocate_genexpert_and_staff'),
            ],
        };
    }
}
