<?php

namespace Database\Seeders;

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectClinicalRule;
use App\Models\SuspectQuestion;
use App\Models\SuspectRuleSet;
use App\Support\Suspect\ScoringEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The draft suspect screening rule sets. Every weight, threshold, criterion
 * and pattern is provisional and waits on clinical sign-off, so each set is
 * seeded as a draft and production never serves it (the release gate needs
 * an approved, published set). Safe in every environment: no patient data,
 * and each set is keyed on its version, so it never overwrites a set that
 * already exists or an admin edit.
 *
 * - 2026.10-draft.1: the prototype questions and weights from the
 *   Suspect-Finding spec (high 40, medium 20, refer 40, no low threshold).
 * - 2026.10-draft.2: the decisions of 12 Oct 2026. Same questions, clinical
 *   rules and patterns; thresholds high 70, medium 40, low 20, refer 70;
 *   prolonged fever 15, previous TB 10, diabetes 8. Created after draft.1,
 *   so it has the higher id and the resolver serves it as the newest draft.
 *   Clinical sign-off approver for draft.2: Fakhar Khan, recorded as the
 *   clinical_signoff_reference when the set is approved in the admin.
 */
class SuspectRuleSetSeeder extends Seeder
{
    public const VERSION = '2026.10-draft.1';

    public const VERSION_2 = '2026.10-draft.2';

    /**
     * @var list<string>
     */
    private const SYMPTOM_KEYS = ['cough_current', 'sputum', 'hemoptysis', 'fever', 'night_sweats', 'weight_loss', 'appetite_loss', 'fatigue'];

    private const YES_NO_UNKNOWN = ['yes', 'no', 'unknown'];

    /**
     * Create each version only if it is missing, oldest first.
     */
    public function run(): void
    {
        $this->seed(self::VERSION, ['high_threshold' => 40, 'medium_threshold' => 20, 'refer_threshold' => 40], $this->scoreItems());
        $this->seed(self::VERSION_2, ['high_threshold' => 70, 'medium_threshold' => 40, 'low_threshold' => 20, 'refer_threshold' => 70], $this->scoreItemsDraftTwo());
    }

    /**
     * @param  array<string, int>  $thresholds
     * @param  list<array{0: string, 1: string, 2: string, 3: array<string, mixed>, 4: int}>  $scoreItems
     */
    private function seed(string $version, array $thresholds, array $scoreItems): void
    {
        if (SuspectRuleSet::query()->where('version', $version)->exists()) {
            return;
        }

        DB::transaction(function () use ($version, $thresholds, $scoreItems): void {
            $ruleSet = SuspectRuleSet::query()->create([
                'version' => $version,
                'status' => SuspectRuleSetStatus::Draft,
                'engine_version' => ScoringEngine::ENGINE_VERSION,
                ...$thresholds,
            ]);

            foreach ($this->questions() as $sort => $question) {
                $ruleSet->questions()->create([
                    ...$question,
                    'help' => $question['help'] ?? null,
                    'ask_when' => $question['ask_when'] ?? null,
                    'sort' => ($sort + 1) * 10,
                ]);
            }

            foreach ($scoreItems as $sort => [$component, $key, $label, $condition, $points]) {
                $ruleSet->scoreItems()->create([
                    'component' => $component,
                    'key' => $key,
                    'label' => $label,
                    'condition' => $condition,
                    'points' => $points,
                    'sort' => ($sort + 1) * 10,
                ]);
            }

            $ruleSet->clinicalRules()->create([
                'code' => 'HEMOPTYSIS_CLINICAL_REVIEW',
                'label' => 'Coughed up blood: clinical review alert',
                'condition' => self::eq('hemoptysis', 'yes'),
                'effect' => SuspectClinicalRule::EFFECT_CLINICAL_ALERT,
                'priority_floor' => SuspectClinicalRule::FLOOR_EVALUATION_REQUIRED,
                'reason_code' => 'HEMOPTYSIS_CLINICAL_REVIEW',
                'next_action' => 'Clinical review per escalation protocol',
                'criterion_reference' => null,
                'configured' => true,
                'sort' => 10,
            ]);

            $ruleSet->clinicalRules()->create([
                'code' => 'TESTING_CRITERION_MET',
                'label' => 'Testing criterion (placeholder until the national criterion is approved)',
                'condition' => null,
                'effect' => SuspectClinicalRule::EFFECT_REQUIRE_EVALUATION,
                'priority_floor' => SuspectClinicalRule::FLOOR_EVALUATION_REQUIRED,
                'reason_code' => 'TESTING_CRITERION_MET',
                'next_action' => 'Refer for diagnostic evaluation',
                'criterion_reference' => null,
                'configured' => false,
                'sort' => 20,
            ]);

            foreach ($this->riskPatterns() as [$code, $reason, $condition]) {
                $ruleSet->riskPatterns()->create([
                    'code' => $code,
                    'reason' => $reason,
                    'condition' => $condition,
                    'active' => false,
                ]);
            }
        });
    }

    /**
     * @return list<array{key: string, section: string, prompt: string, options: list<string>, help?: string, ask_when?: array<string, mixed>}>
     */
    private function questions(): array
    {
        $symptoms = SuspectQuestion::SECTION_SYMPTOMS;
        $history = SuspectQuestion::SECTION_EXPOSURE_HISTORY;

        return [
            ['key' => 'age_band', 'section' => SuspectQuestion::SECTION_PROFILE, 'prompt' => 'Age band', 'options' => ['0_14', '15_24', '25_44', '45_64', '65_plus', 'unknown']],
            ['key' => 'sex', 'section' => SuspectQuestion::SECTION_PROFILE, 'prompt' => 'Sex', 'options' => ['female', 'male', 'other', 'unknown']],
            ['key' => 'cough_current', 'section' => $symptoms, 'prompt' => 'Do you have a cough now?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'cough_duration', 'section' => $symptoms, 'prompt' => 'How long have you had the cough?', 'options' => ['lt_2_weeks', 'gte_2_weeks', 'unknown'], 'ask_when' => self::eq('cough_current', 'yes')],
            ['key' => 'sputum', 'section' => $symptoms, 'prompt' => 'Do you bring up phlegm (sputum)?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'hemoptysis', 'section' => $symptoms, 'prompt' => 'Have you coughed up blood?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'fever', 'section' => $symptoms, 'prompt' => 'Do you have a fever?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'fever_gte_2_weeks', 'section' => $symptoms, 'prompt' => 'Has the fever lasted 2 weeks or more?', 'options' => self::YES_NO_UNKNOWN, 'ask_when' => self::eq('fever', 'yes')],
            ['key' => 'night_sweats', 'section' => $symptoms, 'prompt' => 'Do you have night sweats?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'weight_loss', 'section' => $symptoms, 'prompt' => 'Have you lost weight without trying?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'appetite_loss', 'section' => $symptoms, 'prompt' => 'Have you lost your appetite?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'fatigue', 'section' => $symptoms, 'prompt' => 'Do you feel unusually tired or weak?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'close_contact', 'section' => $history, 'prompt' => 'Have you been in close contact with a person diagnosed with TB?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'household_contact', 'section' => $history, 'prompt' => 'Does that person live in your household?', 'options' => self::YES_NO_UNKNOWN, 'ask_when' => self::eq('close_contact', 'yes')],
            ['key' => 'previous_tb', 'section' => $history, 'prompt' => 'Have you had TB before?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'previous_tb_treatment', 'section' => $history, 'prompt' => 'Did you take TB treatment then?', 'options' => self::YES_NO_UNKNOWN, 'ask_when' => self::eq('previous_tb', 'yes')],
            ['key' => 'hiv', 'section' => $history, 'prompt' => 'Have you been told you have HIV?', 'options' => ['yes', 'no', 'unknown', 'not_disclosed']],
            ['key' => 'diabetes', 'section' => $history, 'prompt' => 'Have you been told you have diabetes?', 'options' => self::YES_NO_UNKNOWN],
            ['key' => 'undernutrition', 'section' => $history, 'prompt' => 'Has a health worker recorded undernutrition?', 'options' => self::YES_NO_UNKNOWN],
        ];
    }

    /**
     * Prototype weights from the spec. All provisional.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: array<string, mixed>, 4: int}>
     */
    private function scoreItems(): array
    {
        return [
            ['S', 'cough_current', 'Current cough', self::eq('cough_current', 'yes'), 8],
            ['S', 'cough_gte_2_weeks', 'Cough lasting 2 weeks or more', self::all(self::eq('cough_current', 'yes'), self::eq('cough_duration', 'gte_2_weeks')), 10],
            ['S', 'sputum', 'Sputum production', self::eq('sputum', 'yes'), 5],
            ['S', 'fever_gte_2_weeks', 'Fever lasting 2 weeks or more', self::all(self::eq('fever', 'yes'), self::eq('fever_gte_2_weeks', 'yes')), 8],
            ['S', 'night_sweats', 'Night sweats', self::eq('night_sweats', 'yes'), 8],
            ['S', 'weight_loss', 'Unexplained weight loss', self::eq('weight_loss', 'yes'), 10],
            ['S', 'appetite_loss', 'Loss of appetite', self::eq('appetite_loss', 'yes'), 4],
            ['S', 'fatigue', 'Unusual fatigue or weakness', self::eq('fatigue', 'yes'), 3],
            ['E', 'close_contact', 'Close contact with a person diagnosed with TB', self::eq('close_contact', 'yes'), 15],
            ['E', 'household_contact', 'Household contact', self::all(self::eq('close_contact', 'yes'), self::eq('household_contact', 'yes')), 5],
            ['H', 'previous_tb', 'Previous TB diagnosis', self::eq('previous_tb', 'yes'), 8],
            ['H', 'hiv', 'Known HIV', self::eq('hiv', 'yes'), 10],
            ['H', 'diabetes', 'Known diabetes', self::eq('diabetes', 'yes'), 4],
            ['H', 'undernutrition', 'Documented undernutrition', self::eq('undernutrition', 'yes'), 4],
        ];
    }

    /**
     * Draft.2 weights: the draft.1 items with prolonged fever 15 (relabelled),
     * previous TB 10 and diabetes 8. All provisional.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: array<string, mixed>, 4: int}>
     */
    private function scoreItemsDraftTwo(): array
    {
        $changes = [
            'fever_gte_2_weeks' => ['Prolonged fever (2 weeks or more)', 15],
            'previous_tb' => [null, 10],
            'diabetes' => [null, 8],
        ];

        return array_map(function (array $item) use ($changes): array {
            [$label, $points] = $changes[$item[1]] ?? [null, null];

            return [$item[0], $item[1], $label ?? $item[2], $item[3], $points ?? $item[4]];
        }, $this->scoreItems());
    }

    /**
     * Starter patterns. All inactive until clinically approved.
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function riskPatterns(): array
    {
        return [
            ['PERSISTENT_COUGH_WITH_TB_CONTACT', 'Cough 2 weeks or more and known TB contact', self::all(self::eq('cough_duration', 'gte_2_weeks'), self::eq('close_contact', 'yes'))],
            ['WEIGHT_LOSS_AND_FEVER_WITHOUT_COUGH', 'Weight loss and fever without cough', self::all(self::eq('weight_loss', 'yes'), self::eq('fever', 'yes'), self::eq('cough_current', 'no'))],
            ['PREVIOUS_TB_WITH_NEW_SYMPTOMS', 'Previous TB with new symptoms', self::all(self::eq('previous_tb', 'yes'), ['count_yes' => [self::SYMPTOM_KEYS, 1]])],
            ['MULTIPLE_CONCURRENT_TB_RISK_FACTORS', 'Three or more symptoms with a history risk factor', self::all(['count_yes' => [self::SYMPTOM_KEYS, 3]], ['count_yes' => [['hiv', 'diabetes', 'undernutrition', 'previous_tb'], 1]])],
        ];
    }

    /**
     * @return array{eq: array{0: string, 1: string}}
     */
    private static function eq(string $key, string $value): array
    {
        return ['eq' => [$key, $value]];
    }

    /**
     * @param  array<string, mixed>  ...$conditions
     * @return array{all: list<array<string, mixed>>}
     */
    private static function all(array ...$conditions): array
    {
        return ['all' => array_values($conditions)];
    }
}
