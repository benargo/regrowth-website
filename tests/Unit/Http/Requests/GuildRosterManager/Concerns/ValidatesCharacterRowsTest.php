<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager\Concerns;

use App\Contracts\Http\Requests\GuildRosterManager\ValidatesGrmGuids;
use App\Http\Requests\GuildRosterManager\Concerns\ValidatesCharacterRows;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

#[Group('characters')]
#[Group('validation')]
class ValidatesCharacterRowsTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_accepts_a_complete_character_row(): void
    {
        $this->assertSame([], $this->errorsFor([$this->row()])->toArray());
    }

    #[Test]
    public function it_requires_every_character_key_of_a_row(): void
    {
        $errors = $this->errorsFor([[]]);

        $this->assertSame(
            ['rows.0.guid', 'rows.0.name', 'rows.0.rankIndex', 'rows.0.level', 'rows.0.class', 'rows.0.race', 'rows.0.history', 'rows.0.epoch'],
            $errors->keys(),
        );
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_rejects_an_invalid_value(string $key, mixed $value, string $message): void
    {
        $errors = $this->errorsFor([$this->row([$key => $value])]);

        $this->assertSame(["rows.0.{$key}" => [$message]], $errors->toArray());
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'lowercase guid' => ['guid', 'Player-1-0a', 'The rows.0.guid field format is invalid.'],
            'non-player guid' => ['guid', 'Creature-1-0A', 'The rows.0.guid field format is invalid.'],
            'name over 64 characters' => ['name', str_repeat('a', 65), 'The rows.0.name field must not be greater than 64 characters.'],
            'rank index 10' => ['rankIndex', 10, 'The rows.0.rankIndex field must not be greater than 9.'],
            'negative rank index' => ['rankIndex', -1, 'The rows.0.rankIndex field must be at least 0.'],
            'level 9' => ['level', 9, 'The rows.0.level field must be at least 10.'],
            'level 81' => ['level', 81, 'The rows.0.level field must not be greater than 80.'],
            'class over 32 characters' => ['class', str_repeat('a', 33), 'The rows.0.class field must not be greater than 32 characters.'],
            'race over 32 characters' => ['race', str_repeat('a', 33), 'The rows.0.race field must not be greater than 32 characters.'],
            'history not an array' => ['history', 'joined', 'The rows.0.history field must be an array.'],
            'negative epoch' => ['epoch', -1, 'The rows.0.epoch field must be at least 0.'],
        ];
    }

    #[Test]
    #[TestWith([10])]
    #[TestWith([80])]
    public function it_accepts_a_level_at_either_bound(int $level): void
    {
        $this->assertSame([], $this->errorsFor([$this->row(['level' => $level])])->toArray());
    }

    #[Test]
    public function it_rejects_more_than_20_history_events(): void
    {
        $history = array_fill(0, 21, ['type' => 'joined', 'date' => '2026-01-02']);

        $errors = $this->errorsFor([$this->row(['history' => $history])]);

        $this->assertSame(['rows.0.history' => ['The rows.0.history field must not have more than 20 items.']], $errors->toArray());
    }

    #[Test]
    #[DataProvider('invalidHistoryEvents')]
    public function it_rejects_an_invalid_history_event_with_its_custom_message(mixed $event, string $key, string $message): void
    {
        $errors = $this->errorsFor([$this->row(['history' => [$event]])]);

        $this->assertSame([$message], $errors->get($key));
    }

    /**
     * @return array<string, array{mixed, string, string}>
     */
    public static function invalidHistoryEvents(): array
    {
        return [
            'not an object' => ['joined', 'rows.0.history.0', 'The history entry must be an object.'],
            'missing type' => [['date' => '2026-01-02'], 'rows.0.history.0.type', 'The type must be joined or left.'],
            'unknown type' => [['type' => 'kicked', 'date' => '2026-01-02'], 'rows.0.history.0.type', 'The type must be joined or left.'],
            'missing date' => [['type' => 'joined'], 'rows.0.history.0.date', 'The date must be a Y-m-d date.'],
            'date with a time' => [['type' => 'joined', 'date' => '2026-01-02 10:00:00'], 'rows.0.history.0.date', 'The date must be a Y-m-d date.'],
            'impossible date' => [['type' => 'joined', 'date' => '2026-02-31'], 'rows.0.history.0.date', 'The date must be a Y-m-d date.'],
        ];
    }

    #[Test]
    public function it_rejects_two_rows_with_the_same_guid(): void
    {
        $errors = $this->errorsFor([$this->row(), $this->row()]);

        $this->assertSame(['rows.0.guid', 'rows.1.guid'], $errors->keys());
        $this->assertSame(['The rows.0.guid field has a duplicate value.'], $errors->get('rows.0.guid'));
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return [
            'guid' => 'Player-1-0A',
            'name' => 'Somebody',
            'rankIndex' => 5,
            'level' => 60,
            'class' => 'WARRIOR',
            'race' => 'Orc',
            'history' => [
                ['type' => 'joined', 'date' => '2026-01-02'],
                ['type' => 'left', 'date' => '2026-03-04'],
            ],
            'epoch' => 1700000000,
            ...$overrides,
        ];
    }

    /**
     * Validate the rows against the trait's character rules, applied to each `rows.*` entry, and its messages.
     *
     * @param  array<int, mixed>  $rows
     */
    private function errorsFor(array $rows): MessageBag
    {
        $host = new class implements ValidatesGrmGuids
        {
            use ValidatesCharacterRows;
        };

        $rules = [];

        foreach (new ReflectionMethod($host, 'characterRules')->invoke($host) as $attribute => $rule) {
            $rules["rows.*.{$attribute}"] = $rule;
        }

        return Validator::make(['rows' => $rows], $rules, $host->messages())->errors();
    }
}
