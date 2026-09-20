<?php

namespace Tests\Unit\Http\Requests\Dashboard;

use App\Http\Requests\Dashboard\UploadGrmDataRequest;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('grm-upload')]
class UploadGrmDataRequestTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rules ====================

    #[Test]
    public function rules_grm_data_is_required_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('grm_data', $rules);
        $this->assertContains('required', $rules['grm_data']);
        $this->assertContains('string', $rules['grm_data']);
    }

    #[Test]
    public function rules_game_version_id_is_required_integer_and_must_exist(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('game_version_id', $rules);
        $this->assertContains('required', $rules['game_version_id']);
        $this->assertContains('integer', $rules['game_version_id']);
        $this->assertTrue(collect($rules['game_version_id'])->contains(fn ($rule) => $rule instanceof Exists));
    }

    // ==================== messages ====================

    #[Test]
    public function messages_provides_custom_text_for_each_rule(): void
    {
        $messages = $this->makeRequest()->messages();

        $this->assertSame('GRM data is required.', $messages['grm_data.required']);
        $this->assertSame('GRM data must be a string.', $messages['grm_data.string']);
        $this->assertSame('A game version is required.', $messages['game_version_id.required']);
        $this->assertSame('The selected game version is invalid.', $messages['game_version_id.integer']);
        $this->assertSame('The selected game version does not exist.', $messages['game_version_id.exists']);
    }

    // ==================== withValidator ====================

    #[Test]
    #[Group('validation')]
    public function with_validator_passes_for_valid_comma_delimited_csv(): void
    {
        $validator = $this->validate($this->csvInput("Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nBob,Officer,80,0,Main,"));

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_passes_for_valid_semicolon_delimited_csv(): void
    {
        $validator = $this->validate($this->csvInput("Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nBob;Officer;80;0;Main;"));

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_fails_when_csv_has_no_data_rows(): void
    {
        $validator = $this->validate($this->csvInput('Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts'));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'CSV must contain a header row and at least one data row.',
            implode(' ', $validator->errors()->get('grm_data'))
        );
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_fails_when_no_recognised_delimiter_is_present(): void
    {
        $validator = $this->validate($this->csvInput("Name|Rank|Level\nBob|Officer|80"));

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'CSV must use comma or semicolon as delimiter.',
            implode(' ', $validator->errors()->get('grm_data'))
        );
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_fails_when_required_headers_are_missing(): void
    {
        $validator = $this->validate($this->csvInput("Name,Rank,Level\nBob,Officer,80"));

        $this->assertTrue($validator->fails());
        $errors = implode(' ', $validator->errors()->get('grm_data'));
        $this->assertStringContainsString('Missing required headers:', $errors);
        $this->assertStringContainsString('Last Online (Days)', $errors);
        $this->assertStringContainsString('Main/Alt', $errors);
        $this->assertStringContainsString('Player Alts', $errors);
    }

    #[Test]
    #[Group('validation')]
    public function with_validator_skips_csv_checks_when_base_rules_already_failed(): void
    {
        $validator = $this->validate([
            'grm_data' => '',
            'game_version_id' => GameVersion::factory()->create()->id,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayNotHasKey(0, array_filter(
            $validator->errors()->get('grm_data'),
            fn ($message) => str_contains($message, 'delimiter') || str_contains($message, 'headers')
        ));
    }

    // ==================== getDelimiter ====================

    #[Test]
    public function get_delimiter_defaults_to_comma_before_validation(): void
    {
        $request = $this->makeRequest($this->csvInput("Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nBob,Officer,80,0,Main,"));

        $this->assertSame(',', $request->getDelimiter());
    }

    #[Test]
    public function get_delimiter_returns_semicolon_after_detecting_semicolon_delimited_csv(): void
    {
        $request = $this->makeRequest($this->csvInput("Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nBob;Officer;80;0;Main;"));

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);
        $validator->errors();

        $this->assertSame(';', $request->getDelimiter());
    }

    // ==================== getParsedCsvData ====================

    #[Test]
    public function get_parsed_csv_data_returns_delimiter_headers_and_rows(): void
    {
        $request = $this->makeRequest($this->csvInput(
            "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nBob,Officer,80,0,Main,\nAlice,Member,79,2,Alt,Bob"
        ));

        $parsed = $request->getParsedCsvData();

        $this->assertSame(',', $parsed['delimiter']);
        $this->assertSame(['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'], $parsed['headers']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('Bob', $parsed['rows'][0]['Name']);
        $this->assertSame('Officer', $parsed['rows'][0]['Rank']);
        $this->assertSame('Alice', $parsed['rows'][1]['Name']);
    }

    #[Test]
    public function get_parsed_csv_data_skips_blank_lines_between_rows(): void
    {
        $request = $this->makeRequest($this->csvInput(
            "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nBob,Officer,80,0,Main,\n\nAlice,Member,79,2,Alt,Bob"
        ));

        $parsed = $request->getParsedCsvData();

        $this->assertCount(2, $parsed['rows']);
    }

    #[Test]
    public function get_parsed_csv_data_fills_missing_trailing_values_with_empty_string(): void
    {
        $request = $this->makeRequest($this->csvInput(
            "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nBob,Officer"
        ));

        $parsed = $request->getParsedCsvData();

        $this->assertSame('', $parsed['rows'][0]['Level']);
        $this->assertSame('', $parsed['rows'][0]['Player Alts']);
    }

    // ==================== helpers ====================

    private function csvInput(string $grmData): array
    {
        return [
            'grm_data' => $grmData,
            'game_version_id' => GameVersion::factory()->create()->id,
        ];
    }

    private function makeRequest(?array $params = null): UploadGrmDataRequest
    {
        return UploadGrmDataRequest::create('/', 'POST', $params ?? []);
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($data);

        $validator = Validator::make($data, $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}
